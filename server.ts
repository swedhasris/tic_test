import express from "express";
import { createServer as createViteServer } from "vite";
import path from "path";
import { fileURLToPath } from "url";
import cron from "node-cron";
import mysql from 'mysql2/promise';
import { GoogleGenAI } from "@google/genai";
import { config as loadEnv } from "dotenv";
import multer from "multer";
import fs from "fs";

// SQLite will be imported dynamically when needed

// Load environment variables from .env file
loadEnv();

// Log API key status at startup (masked for security)
const geminiKey = process.env.GEMINI_API_KEY;
console.log(`[Kiru AI] GEMINI_API_KEY: ${geminiKey && geminiKey !== "MY_GEMINI_API_KEY" && geminiKey !== "your_gemini_api_key_here" ? "✓ Loaded" : "✗ NOT SET — Kiru AI will not work"}`);

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Database configuration
const dbConfig = {
  host: process.env.MYSQL_HOST || 'localhost',
  port: parseInt(process.env.MYSQL_PORT || '3306'),
  user: process.env.MYSQL_USER || 'root',
  password: process.env.MYSQL_PASSWORD || '',
  database: process.env.MYSQL_DATABASE || 'connectit_db',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelay: 0
};

let pool: mysql.Pool;
let sqliteDb: any = null;
let useSQLite = false;

async function getSQLiteDb() {
  if (!sqliteDb) {
    const { open } = await import('sqlite');
    const sqlite3Module = await import('sqlite3');
    const sqlite3 = sqlite3Module.default || sqlite3Module;
    sqliteDb = await open({
      filename: './timesheet.sqlite',
      driver: sqlite3.Database
    });
    // Create tables if not exist
    await sqliteDb.exec(`
      CREATE TABLE IF NOT EXISTS timesheets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id TEXT NOT NULL,
        week_start TEXT NOT NULL,
        week_end TEXT NOT NULL,
        status TEXT DEFAULT 'Draft',
        total_hours REAL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        submitted_at DATETIME
      );
      CREATE INDEX IF NOT EXISTS idx_user_week ON timesheets(user_id, week_start);
      CREATE TABLE IF NOT EXISTS time_cards (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timesheet_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        entry_date TEXT NOT NULL,
        task TEXT,
        hours_worked REAL DEFAULT 0.00,
        description TEXT,
        short_description TEXT,
        start_time TEXT,
        end_time TEXT,
        deduct REAL DEFAULT 0.00,
        work_type TEXT,
        billable TEXT,
        status TEXT DEFAULT 'Draft',
        elapsed_seconds INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
      );
      CREATE INDEX IF NOT EXISTS idx_tc_timesheet ON time_cards(timesheet_id);
      CREATE INDEX IF NOT EXISTS idx_tc_user_date ON time_cards(user_id, entry_date);
    `);
    console.log('[SQLite] Timesheet database initialized');
  }
  return sqliteDb;
}

function getPool(): mysql.Pool {
  if (!pool) {
    pool = mysql.createPool(dbConfig);
    console.log(`[MySQL] Connection pool created: ${dbConfig.host}:${dbConfig.port}/${dbConfig.database}`);
  }
  return pool;
}

async function initDatabase(): Promise<void> {
  try {
    // Connect without database to create it if needed
    const tempConfig = { ...dbConfig };
    delete (tempConfig as any).database;
    const tempConnection = await mysql.createConnection(tempConfig);
    await tempConnection.execute(`CREATE DATABASE IF NOT EXISTS ${dbConfig.database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
    await tempConnection.end();
    console.log(`[MySQL] Database '${dbConfig.database}' ensured`);
  } catch (error: any) {
    console.error('[MySQL] Database init failed:', error.message);
    console.log('[SQLite] Will use SQLite fallback for timesheets');
    useSQLite = true;
    await getSQLiteDb();
  }
}

async function testConnection(): Promise<boolean> {
  if (useSQLite) return true;
  try {
    const connection = await getPool().getConnection();
    await connection.query('SELECT 1');
    connection.release();
    console.log('[MySQL] Connection test successful');
    return true;
  } catch (error) {
    console.error('[MySQL] Connection test failed:', error);
    console.log('[SQLite] Falling back to SQLite for timesheets');
    useSQLite = true;
    await getSQLiteDb();
    return true;
  }
}

async function query(sql: string, values?: any[]): Promise<any[]> {
  if (useSQLite) {
    const db = await getSQLiteDb();
    return await db.all(sql, values || []);
  }
  const [rows] = await getPool().execute(sql, values);
  return rows as any[];
}

async function execute(sql: string, values?: any[]): Promise<any> {
  if (useSQLite) {
    const db = await getSQLiteDb();
    const result = await db.run(sql, values || []);
    return { insertId: result.lastID, affectedRows: result.changes };
  }
  const [result] = await getPool().execute(sql, values);
  return result as mysql.ResultSetHeader;
}

function formatDate(date: Date | string | null): string | null {
  if (!date) return null;
  const d = typeof date === 'string' ? new Date(date) : date;
  if (isNaN(d.getTime())) return null;
  return d.toISOString().slice(0, 19).replace('T', ' ');
}

async function generateTicketNumber(): Promise<string> {
  const prefix = 'INC';
  const random = Math.floor(1000000 + Math.random() * 9000000);
  return `${prefix}${random}`;
}

// SLA Escalation Engine
async function escalateStaleTickets() {
  console.log(`[SLA Engine] Checking tickets...`);
  const now = new Date();
  const nowStr = formatDate(now);

  try {
    // Get all non-closed tickets
    const tickets = await query(
      "SELECT * FROM tickets WHERE status NOT IN ('Resolved', 'Closed', 'Canceled')"
    );

    console.log(`[SLA Engine] Fetched ${tickets.length} tickets.`);

    for (const ticket of tickets) {
      if (ticket.status === 'On Hold' || ticket.status === 'Waiting for Customer') continue;

      const updates: any = {};
      const historyEntries: any[] = [];

      // Response SLA Check
      if (ticket.response_deadline && !ticket.first_response_at &&
        ticket.response_sla_status !== 'Breached' && ticket.response_sla_status !== 'Completed') {
        const deadline = new Date(ticket.response_deadline).getTime();
        const diff = deadline - now.getTime();

        if (diff <= 0) {
          updates.response_sla_status = 'Breached';
          historyEntries.push({
            action: "Response SLA BREACHED",
            timestamp: now.toISOString(),
            user: "SLA Engine"
          });
        } else if (diff < (deadline - new Date(ticket.created_at).getTime()) * 0.2) {
          if (ticket.response_sla_status !== 'At Risk') {
            updates.response_sla_status = 'At Risk';
          }
        }
      }

      // Resolution SLA Check
      if (ticket.resolution_deadline && !ticket.resolved_at &&
        ticket.resolution_sla_status !== 'Breached' && ticket.resolution_sla_status !== 'Completed') {
        const deadline = new Date(ticket.resolution_deadline).getTime();
        const diff = deadline - now.getTime();

        if (diff <= 0) {
          updates.resolution_sla_status = 'Breached';
          updates.priority = '1 - Critical';
          historyEntries.push({
            action: "Resolution SLA BREACHED: Ticket escalated to Critical",
            timestamp: now.toISOString(),
            user: "SLA Engine"
          });
        } else if (diff < (deadline - new Date(ticket.created_at).getTime()) * 0.2) {
          if (ticket.resolution_sla_status !== 'At Risk') {
            updates.resolution_sla_status = 'At Risk';
          }
        }
      }

      if (Object.keys(updates).length > 0) {
        const fields = Object.keys(updates).map(key => `${key} = ?`).join(', ');
        await execute(`UPDATE tickets SET ${fields}, updated_at = ? WHERE id = ?`, [...Object.values(updates), formatDate(new Date()), ticket.id]);

        // Add history entries
        for (const entry of historyEntries) {
          await execute(
            "INSERT INTO ticket_history (ticket_id, action, user, timestamp, details) VALUES (?, ?, ?, ?, ?)",
            [ticket.id, entry.action, entry.user, entry.timestamp, JSON.stringify(entry)]
          );
        }
      }
    }
  } catch (error: any) {
    console.error(`[SLA Engine] Error: ${error.message}`);
  }
}

// Schedule SLA check to run every 15 minutes
cron.schedule("*/15 * * * *", () => {
  escalateStaleTickets();
});

async function startServer() {
  const app = express();
  const PORT = 3000;

  app.use(express.json());

  // Initialize database connection
  await initDatabase();
  await testConnection();

  // Auto-create timesheet tables if they don't exist
  try {
    await execute(`
      CREATE TABLE IF NOT EXISTS timesheets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(128) NOT NULL,
        week_start DATE NOT NULL,
        week_end DATE NOT NULL,
        status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
        total_hours DECIMAL(10, 2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        submitted_at TIMESTAMP NULL,
        INDEX idx_user_week (user_id, week_start),
        INDEX idx_status (status)
      ) ENGINE=InnoDB
    `);
    await execute(`
      CREATE TABLE IF NOT EXISTS time_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        timesheet_id INT NOT NULL,
        user_id VARCHAR(128) NOT NULL,
        entry_date DATE NOT NULL,
        task VARCHAR(255),
        hours_worked DECIMAL(10, 2) DEFAULT 0.00,
        description TEXT,
        short_description VARCHAR(255),
        start_time VARCHAR(20),
        end_time VARCHAR(20),
        deduct DECIMAL(10, 2) DEFAULT 0.00,
        work_type VARCHAR(50),
        billable VARCHAR(50),
        status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
        elapsed_seconds INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_timesheet_id (timesheet_id),
        INDEX idx_user_date (user_id, entry_date)
      ) ENGINE=InnoDB
    `);
    console.log('[MySQL] Timesheet tables initialized');
  } catch (e: any) {
    console.error('[MySQL] Failed to initialize timesheet tables:', e.message);
  }

  // API Routes
  app.get("/api/health", (req, res) => {
    res.json({ status: "ok", database: "mysql" });
  });

  app.get("/api/db-test", async (req, res) => {
    try {
      const result = await query("SELECT COUNT(*) as count FROM tickets");
      res.json({
        status: "connected",
        database: dbConfig.database,
        host: dbConfig.host,
        count: result[0]?.count || 0
      });
    } catch (error: any) {
      console.error("[Diagnostic] DB Test failed:", error.message);
      res.status(500).json({
        status: "error",
        error: error.message,
        database: dbConfig.database,
        host: dbConfig.host
      });
    }
  });

  // Ticket Endpoints
  app.get("/api/tickets/all", async (req, res) => {
    try {
      const tickets = await query("SELECT * FROM tickets ORDER BY created_at DESC");
      res.json(tickets.map(t => ({ id: t.id.toString(), ...t })));
    } catch (error: any) {
      console.error("Error fetching tickets:", error);
      res.status(500).json({ error: "Failed to fetch tickets" });
    }
  });

  app.get("/api/tickets/open", async (req, res) => {
    try {
      const tickets = await query(
        "SELECT * FROM tickets WHERE status NOT IN ('Resolved', 'Closed', 'Canceled') ORDER BY created_at DESC"
      );
      res.json(tickets.map(t => ({ id: t.id.toString(), ...t })));
    } catch (error: any) {
      console.error("Error fetching open tickets:", error);
      res.status(500).json({ error: "Failed to fetch open tickets" });
    }
  });

  app.get("/api/tickets/assigned/:userId", async (req, res) => {
    try {
      const tickets = await query(
        "SELECT * FROM tickets WHERE assigned_to = ? ORDER BY created_at DESC",
        [req.params.userId]
      );
      res.json(tickets.map(t => ({ id: t.id.toString(), ...t })));
    } catch (error: any) {
      console.error("Error fetching assigned tickets:", error);
      res.status(500).json({ error: "Failed to fetch assigned tickets" });
    }
  });

  app.get("/api/tickets/unassigned", async (req, res) => {
    try {
      const tickets = await query(
        "SELECT * FROM tickets WHERE assigned_to IS NULL OR assigned_to = '' ORDER BY created_at DESC"
      );
      res.json(tickets.map(t => ({ id: t.id.toString(), ...t })));
    } catch (error: any) {
      console.error("Error fetching unassigned tickets:", error);
      res.status(500).json({ error: "Failed to fetch unassigned tickets" });
    }
  });

  app.get("/api/tickets/resolved", async (req, res) => {
    try {
      const tickets = await query(
        "SELECT * FROM tickets WHERE status IN ('Resolved', 'Closed') ORDER BY resolved_at DESC"
      );
      res.json(tickets.map(t => ({ id: t.id.toString(), ...t })));
    } catch (error: any) {
      console.error("Error fetching resolved tickets:", error);
      res.status(500).json({ error: "Failed to fetch resolved tickets" });
    }
  });

  app.get("/api/tickets/:id", async (req, res) => {
    try {
      const tickets = await query("SELECT * FROM tickets WHERE id = ?", [req.params.id]);
      if (tickets.length === 0) {
        return res.status(404).json({ error: "Ticket not found" });
      }

      const ticket = tickets[0];

      // Get comments
      const comments = await query("SELECT * FROM comments WHERE ticket_id = ? ORDER BY created_at ASC", [ticket.id]);

      // Get history
      const history = await query("SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY timestamp DESC", [ticket.id]);

      res.json({
        id: ticket.id.toString(),
        ...ticket,
        comments: comments.map(c => ({ id: c.id.toString(), ...c })),
        history: history.map(h => ({ id: h.id.toString(), ...h }))
      });
    } catch (error: any) {
      console.error("Error fetching ticket:", error);
      res.status(500).json({ error: "Failed to fetch ticket" });
    }
  });

  app.post("/api/tickets/create", async (req, res) => {
    try {
      console.log("Creating ticket with data:", JSON.stringify(req.body));

      // Generate ticket number
      const ticketNumber = await generateTicketNumber();

      // Workflow Automation: Auto-assignment based on category
      let assignmentGroup = req.body.assignmentGroup;
      if (!assignmentGroup) {
        switch (req.body.category) {
          case "Network": assignmentGroup = "Network Team"; break;
          case "Hardware": assignmentGroup = "Hardware Support"; break;
          case "Software": assignmentGroup = "App Support"; break;
          case "Database": assignmentGroup = "DBA Team"; break;
          default: assignmentGroup = "Service Desk";
        }
      }

      const ticketData = {
        ticket_number: ticketNumber,
        caller: req.body.caller || "System",
        category: req.body.category || "Inquiry / Help",
        title: req.body.title,
        description: req.body.description,
        status: "New",
        priority: req.body.priority || "4 - Low",
        impact: req.body.impact || "3 - Low",
        urgency: req.body.urgency || "3 - Low",
        channel: req.body.channel || "Self-service",
        assignment_group: assignmentGroup,
        assigned_to: req.body.assignedTo || null,
        assigned_to_name: req.body.assignedToName || null,
        created_by: req.body.createdBy || req.body.caller || "System",
        created_by_name: req.body.createdByName || req.body.caller || "System",
        service: req.body.service || null,
        service_offering: req.body.serviceOffering || null,
        cmdb_item: req.body.cmdbItem || null,
        subcategory: req.body.subcategory || null
      };

      // Insert ticket
      const fields = Object.keys(ticketData).filter(k => ticketData[k] !== null);
      const placeholders = fields.map(() => '?').join(', ');
      const values = fields.map(k => ticketData[k]);

      const result = await execute(
        `INSERT INTO tickets (${fields.join(', ')}) VALUES (${placeholders})`,
        values
      );

      const ticketId = result.insertId;

      // Add creation history
      await execute(
        "INSERT INTO ticket_history (ticket_id, action, user, details) VALUES (?, ?, ?, ?)",
        [ticketId, "Ticket Created via API", req.body.caller || "System", JSON.stringify(ticketData)]
      );

      // Workflow Automation: Notify Manager for High Priority
      if (req.body.priority === "1 - Critical" || req.body.priority === "2 - High") {
        await execute(
          "INSERT INTO ticket_history (ticket_id, action, user, details) VALUES (?, ?, ?, ?)",
          [ticketId, "Manager Notified (High Priority)", "System Automation", "High priority ticket created"]
        );
      }

      // Return created ticket
      const tickets = await query("SELECT * FROM tickets WHERE id = ?", [ticketId]);
      res.json({ id: ticketId.toString(), ...tickets[0] });

    } catch (error: any) {
      console.error("Error creating ticket:", error);
      res.status(500).json({ error: "Failed to create ticket: " + error.message });
    }
  });

  app.put("/api/tickets/:id", async (req, res) => {
    try {
      const { id } = req.params;

      // Get current ticket
      const tickets = await query("SELECT * FROM tickets WHERE id = ?", [id]);
      if (tickets.length === 0) {
        return res.status(404).json({ error: "Ticket not found" });
      }
      const ticket = tickets[0];

      // Calculate points if the ticket is being resolved
      let points = 0;
      if ((req.body.status === "Resolved" || req.body.status === "Closed") && !ticket.resolved_at) {
        if (ticket.resolution_deadline) {
          const deadline = new Date(ticket.resolution_deadline).getTime();
          const resolvedAt = new Date().getTime();
          const createdAt = new Date(ticket.created_at).getTime();

          if (resolvedAt < deadline) {
            // Award points based on speed: (Time Saved / Total SLA Time) * 100
            const totalSla = deadline - createdAt;
            const timeSaved = deadline - resolvedAt;
            points = Math.round((timeSaved / totalSla) * 100);
            if (points < 10) points = 10;
          } else {
            points = 5;
          }
        }
      }

      const updateData: any = {
        ...req.body,
        points: ticket.points + points,
        updated_at: formatDate(new Date())
      };

      if (req.body.status === "Resolved" || req.body.status === "Closed") {
        updateData.resolved_at = formatDate(new Date());
      }

      // Build update query
      const fields = Object.keys(updateData).filter(k => k !== 'id' && updateData[k] !== undefined);
      const setClause = fields.map(k => `${k} = ?`).join(', ');
      const values = [...fields.map(k => updateData[k]), id];

      await execute(`UPDATE tickets SET ${setClause} WHERE id = ?`, values);

      // Add history entry for status change
      if (req.body.status && req.body.status !== ticket.status) {
        await execute(
          "INSERT INTO ticket_history (ticket_id, action, user, details) VALUES (?, ?, ?, ?)",
          [id, `Status changed to ${req.body.status}`, req.body.updatedBy || "System", JSON.stringify({ oldStatus: ticket.status, newStatus: req.body.status })]
        );
      }

      // Return updated ticket
      const updatedTickets = await query("SELECT * FROM tickets WHERE id = ?", [id]);
      res.json({ id: id.toString(), ...updatedTickets[0], pointsAwarded: points });

    } catch (error: any) {
      console.error("Error updating ticket:", error);
      res.status(500).json({ error: "Failed to update ticket" });
    }
  });

  app.delete("/api/tickets/:id", async (req, res) => {
    try {
      const { id } = req.params;
      await execute("DELETE FROM tickets WHERE id = ?", [id]);
      res.json({ message: "Ticket deleted successfully" });
    } catch (error: any) {
      console.error("Error deleting ticket:", error);
      res.status(500).json({ error: "Failed to delete ticket" });
    }
  });

  // Manual trigger for testing escalation
  app.post("/api/tickets/trigger-escalation", async (req, res) => {
    await escalateStaleTickets();
    res.json({ message: "Escalation check triggered manually" });
  });

  // Leaderboard Endpoint
  app.get("/api/leaderboard/daily", async (req, res) => {
    try {
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      const rows = await query(
        `SELECT assigned_to, assigned_to_name, 
                SUM(points) as total_points, 
                COUNT(*) as resolved_count
         FROM tickets 
         WHERE status IN ('Resolved', 'Closed') 
           AND resolved_at >= ?
           AND assigned_to IS NOT NULL
         GROUP BY assigned_to, assigned_to_name
         ORDER BY total_points DESC`,
        [formatDate(today)]
      );

      const leaderboard = rows.map(row => ({
        id: row.assigned_to,
        name: row.assigned_to_name || row.assigned_to,
        points: row.total_points || 0,
        resolvedCount: row.resolved_count || 0
      }));

      res.json(leaderboard);
    } catch (error: any) {
      console.error("Leaderboard Error:", error);
      res.status(500).json({ error: "Failed to fetch leaderboard" });
    }
  });

  // User Endpoints
  app.get("/api/users", async (req, res) => {
    try {
      const users = await query("SELECT id, uid, name, email, role, phone, is_active, created_at FROM users ORDER BY name");
      res.json(users.map(u => ({ id: u.id.toString(), ...u })));
    } catch (error: any) {
      console.error("Error fetching users:", error);
      res.status(500).json({ error: "Failed to fetch users" });
    }
  });

  app.get("/api/users/:uid", async (req, res) => {
    try {
      const users = await query("SELECT id, uid, name, email, role, phone, is_active, created_at FROM users WHERE uid = ?", [req.params.uid]);
      if (users.length === 0) {
        return res.status(404).json({ error: "User not found" });
      }
      res.json({ id: users[0].id.toString(), ...users[0] });
    } catch (error: any) {
      console.error("Error fetching user:", error);
      res.status(500).json({ error: "Failed to fetch user" });
    }
  });

  app.post("/api/users", async (req, res) => {
    try {
      const { uid, name, email, role, phone, password_hash } = req.body;

      const result = await execute(
        "INSERT INTO users (uid, name, email, role, phone, password_hash) VALUES (?, ?, ?, ?, ?, ?)",
        [uid, name, email, role || 'user', phone, password_hash]
      );

      const users = await query("SELECT * FROM users WHERE id = ?", [result.insertId]);
      res.json({ id: result.insertId.toString(), ...users[0] });
    } catch (error: any) {
      console.error("Error creating user:", error);
      res.status(500).json({ error: "Failed to create user: " + error.message });
    }
  });

  app.put("/api/users/:uid", async (req, res) => {
    try {
      const { name, email, role, phone, is_active } = req.body;

      await execute(
        "UPDATE users SET name = ?, email = ?, role = ?, phone = ?, is_active = ? WHERE uid = ?",
        [name, email, role, phone, is_active, req.params.uid]
      );

      const users = await query("SELECT * FROM users WHERE uid = ?", [req.params.uid]);
      res.json({ id: users[0].id.toString(), ...users[0] });
    } catch (error: any) {
      console.error("Error updating user:", error);
      res.status(500).json({ error: "Failed to update user" });
    }
  });

  // Authentication Endpoints
  app.post("/api/auth/login", async (req, res) => {
    try {
      const { email, password } = req.body;

      // Simple hash function (same as frontend)
      function simpleHash(str: string): string {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
          const char = str.charCodeAt(i);
          hash = ((hash << 5) - hash) + char;
          hash = hash & hash;
        }
        return 'h_' + Math.abs(hash).toString(36) + '_' + str.length;
      }

      const users = await query("SELECT * FROM users WHERE email = ? AND is_active = TRUE", [email.toLowerCase().trim()]);

      if (users.length === 0) {
        return res.status(401).json({ error: "Invalid email or password" });
      }

      const user = users[0];

      if (user.password_hash && user.password_hash !== simpleHash(password)) {
        return res.status(401).json({ error: "Invalid email or password" });
      }

      // Update last login
      await execute("UPDATE users SET last_login = ? WHERE id = ?", [formatDate(new Date()), user.id]);

      res.json({
        id: user.id.toString(),
        uid: user.uid,
        name: user.name,
        email: user.email,
        role: user.role,
        phone: user.phone
      });
    } catch (error: any) {
      console.error("Login error:", error);
      res.status(500).json({ error: "Login failed" });
    }
  });

  // Comments Endpoint
  app.post("/api/tickets/:id/comments", async (req, res) => {
    try {
      const { id } = req.params;
      const { user_id, user_name, message, is_internal } = req.body;

      const result = await execute(
        "INSERT INTO comments (ticket_id, user_id, user_name, message, is_internal) VALUES (?, ?, ?, ?, ?)",
        [id, user_id, user_name, message, is_internal ? 1 : 0]
      );

      const comments = await query("SELECT * FROM comments WHERE id = ?", [result.insertId]);
      res.json({ id: result.insertId.toString(), ...comments[0] });
    } catch (error: any) {
      console.error("Error adding comment:", error);
      res.status(500).json({ error: "Failed to add comment" });
    }
  });

  // Timesheet Endpoints
  app.get("/api/timesheets", async (req, res) => {
    try {
      const { user_id, week_start, status } = req.query;
      let sql = "SELECT * FROM timesheets WHERE 1=1";
      const values = [];

      if (user_id) {
        sql += " AND user_id = ?";
        values.push(user_id);
      }
      if (week_start) {
        sql += " AND week_start = ?";
        values.push(week_start);
      }
      if (status) {
        sql += " AND status = ?";
        values.push(status);
      }

      const rows = await query(sql, values);
      res.json(rows.map(r => ({ id: r.id.toString(), ...r })));
    } catch (error: any) {
      console.error("Error fetching timesheets:", error);
      res.status(500).json({ error: "Failed to fetch timesheets" });
    }
  });

  app.get("/api/timesheets/all", async (req, res) => {
    try {
      const rows = await query("SELECT * FROM timesheets ORDER BY updated_at DESC");
      res.json(rows.map(r => ({ id: r.id.toString(), ...r })));
    } catch (error: any) {
      console.error("Error fetching all timesheets:", error);
      res.status(500).json({ error: "Failed to fetch all timesheets" });
    }
  });

  app.post("/api/timesheets/get-or-create", async (req, res) => {
    try {
      const { user_id, week_start, week_end } = req.body;

      const existing = await query(
        "SELECT * FROM timesheets WHERE user_id = ? AND week_start = ?",
        [user_id, week_start]
      );

      if (existing.length > 0) {
        return res.json({ id: existing[0].id.toString(), ...existing[0] });
      }

      const result = await execute(
        "INSERT INTO timesheets (user_id, week_start, week_end, status) VALUES (?, ?, ?, 'Draft')",
        [user_id, week_start, week_end]
      );

      const created = await query("SELECT * FROM timesheets WHERE id = ?", [result.insertId]);
      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error("Error get-or-create timesheet:", error);
      res.status(500).json({ error: "Failed to manage timesheet" });
    }
  });

  app.put("/api/timesheets/:id", async (req, res) => {
    try {
      const { id } = req.params;
      const fields = Object.keys(req.body).filter(k => k !== 'id');
      const setClause = fields.map(k => `${k} = ?`).join(', ');
      const values = [...fields.map(k => req.body[k]), id];

      if (req.body.status === 'Submitted') {
        const now = formatDate(new Date());
        await execute(`UPDATE timesheets SET ${setClause}, submitted_at = ? WHERE id = ?`, [...values.slice(0, -1), now, id]);
      } else {
        await execute(`UPDATE timesheets SET ${setClause} WHERE id = ?`, values);
      }

      const updated = await query("SELECT * FROM timesheets WHERE id = ?", [id]);

      // Sync status to time cards if changed
      if (req.body.status) {
        await execute("UPDATE time_cards SET status = ? WHERE timesheet_id = ?", [req.body.status, id]);
      }

      res.json({ id: id.toString(), ...updated[0] });
    } catch (error: any) {
      console.error("Error updating timesheet:", error);
      res.status(500).json({ error: "Failed to update timesheet" });
    }
  });

  // Time Card Endpoints
  app.get("/api/time-cards", async (req, res) => {
    try {
      const { timesheet_id, user_id, start_date, end_date } = req.query;
      let sql = "SELECT * FROM time_cards WHERE 1=1";
      const values = [];

      if (timesheet_id) {
        sql += " AND timesheet_id = ?";
        values.push(timesheet_id);
      }
      if (user_id) {
        sql += " AND user_id = ?";
        values.push(user_id);
      }
      if (start_date && end_date) {
        sql += " AND entry_date BETWEEN ? AND ?";
        values.push(start_date, end_date);
      }

      const rows = await query(sql, values);
      res.json(rows.map(r => ({ id: r.id.toString(), ...r })));
    } catch (error: any) {
      console.error("Error fetching time cards:", error);
      res.status(500).json({ error: "Failed to fetch time cards" });
    }
  });

  app.post("/api/time-cards", async (req, res) => {
    try {
      const fields = Object.keys(req.body);
      const placeholders = fields.map(() => '?').join(', ');
      const values = fields.map(k => req.body[k]);

      const result = await execute(
        `INSERT INTO time_cards (${fields.join(', ')}) VALUES (${placeholders})`,
        values
      );

      const created = await query("SELECT * FROM time_cards WHERE id = ?", [result.insertId]);

      // Update timesheet total hours
      if (req.body.timesheet_id) {
        const cards = await query("SELECT SUM(hours_worked) as total FROM time_cards WHERE timesheet_id = ?", [req.body.timesheet_id]);
        await execute("UPDATE timesheets SET total_hours = ? WHERE id = ?", [cards[0].total || 0, req.body.timesheet_id]);
      }

      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error("Error creating time card:", error);
      res.status(500).json({ error: "Failed to create time card" });
    }
  });

  app.put("/api/time-cards/:id", async (req, res) => {
    try {
      const { id } = req.params;
      const fields = Object.keys(req.body).filter(k => k !== 'id');
      const setClause = fields.map(k => `${k} = ?`).join(', ');
      const values = [...fields.map(k => req.body[k]), id];

      await execute(`UPDATE time_cards SET ${setClause} WHERE id = ?`, values);

      const updated = await query("SELECT * FROM time_cards WHERE id = ?", [id]);

      // Update timesheet total hours
      if (updated[0].timesheet_id) {
        const cards = await query("SELECT SUM(hours_worked) as total FROM time_cards WHERE timesheet_id = ?", [updated[0].timesheet_id]);
        await execute("UPDATE timesheets SET total_hours = ? WHERE id = ?", [cards[0].total || 0, updated[0].timesheet_id]);
      }

      res.json({ id: id.toString(), ...updated[0] });
    } catch (error: any) {
      console.error("Error updating time card:", error);
      res.status(500).json({ error: "Failed to update time card" });
    }
  });

  app.delete("/api/time-cards/:id", async (req, res) => {
    try {
      const { id } = req.params;
      const card = await query("SELECT timesheet_id FROM time_cards WHERE id = ?", [id]);

      await execute("DELETE FROM time_cards WHERE id = ?", [id]);

      if (card.length > 0 && card[0].timesheet_id) {
        const cards = await query("SELECT SUM(hours_worked) as total FROM time_cards WHERE timesheet_id = ?", [card[0].timesheet_id]);
        await execute("UPDATE timesheets SET total_hours = ? WHERE id = ?", [cards[0].total || 0, card[0].timesheet_id]);
      }

      res.json({ message: "Time card deleted successfully" });
    } catch (error: any) {
      console.error("Error deleting time card:", error);
      res.status(500).json({ error: "Failed to delete time card" });
    }
  });

  // ═══ WORK SESSIONS TABLE ═══
  try {
    if (useSQLite) {
      const db = await getSQLiteDb();
      await db.exec(`
        CREATE TABLE IF NOT EXISTS work_sessions (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id TEXT NOT NULL,
          user_name TEXT,
          ticket_id TEXT,
          ticket_number TEXT,
          start_time DATETIME NOT NULL,
          stop_time DATETIME,
          duration INTEGER DEFAULT 0,
          start_context TEXT,
          stop_context TEXT,
          ai_notes_start TEXT,
          ai_notes_stop TEXT,
          status TEXT DEFAULT 'active',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
      `);
    } else {
      await execute(`
        CREATE TABLE IF NOT EXISTS work_sessions (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id VARCHAR(128) NOT NULL,
          user_name VARCHAR(255),
          ticket_id VARCHAR(128),
          ticket_number VARCHAR(50),
          start_time TIMESTAMP NOT NULL,
          stop_time TIMESTAMP NULL,
          duration INT DEFAULT 0,
          start_context TEXT,
          stop_context TEXT,
          ai_notes_start TEXT,
          ai_notes_stop TEXT,
          status ENUM('active', 'completed') DEFAULT 'active',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_ws_user (user_id),
          INDEX idx_ws_ticket (ticket_id),
          INDEX idx_ws_status (status)
        ) ENGINE=InnoDB
      `);
    }
    console.log('[DB] Work sessions table initialized');
  } catch (e: any) {
    console.error('[DB] Work sessions table init failed:', e.message);
  }

  // ═══ AI Work Analysis Endpoint ═══
  app.post("/api/ai/analyze-work", async (req, res) => {
    try {
      const { context, ticketNumber, ticketTitle, action, elapsedTime } = req.body;

      if (!ticketNumber) {
        return res.status(400).json({ error: "Missing ticket number" });
      }

      const apiKey = process.env.GEMINI_API_KEY;
      if (!apiKey || apiKey === "MY_GEMINI_API_KEY" || apiKey === "your_gemini_api_key_here") {
        // Return intelligent fallback when API key is not configured
        const fallback = generateSmartFallback(ticketNumber, ticketTitle, action, elapsedTime, context);
        return res.json(fallback);
      }

      let pageContext: any = {};
      try { pageContext = JSON.parse(context || '{}'); } catch { }

      const actionStr = action === 'start' ? 'STARTING work on' : 'STOPPING work on';
      const durationStr = elapsedTime ? `\nTotal time worked: ${Math.floor(elapsedTime / 60)} minutes ${elapsedTime % 60} seconds` : '';

      const prompt = `You are an IT service management work notes assistant. Generate a concise, professional work note for a technician who is ${actionStr} incident ${ticketNumber}.

Ticket: ${ticketNumber} - ${ticketTitle || 'Incident'}${durationStr}

Page context the technician is viewing:
- Page type: ${pageContext.pageType || 'unknown'}
- Current URL: ${pageContext.url || 'unknown'}
- Visible headings: ${(pageContext.headings || []).join(', ')}
- Form data visible: ${JSON.stringify(pageContext.formData || {}).substring(0, 300)}
- Status indicators: ${(pageContext.badges || []).join(', ')}

Generate a JSON response with these fields:
- "summary": A 1-2 sentence professional work note using action verbs (Investigated, Updated, Reviewed, Configured, Troubleshooted, Analyzed, Implemented, Documented, Verified, Resolved). Be specific about what was done.
- "activityType": One of "ticket_resolution", "configuration", "investigation", "documentation", "communication", "development", "testing"
- "confidence": A number 0-1 indicating how confident you are
- "actionVerb": The primary action verb used
- "detectedActivities": An array of detected activities like ["Reviewed ticket details", "Checked SLA status"]

Respond ONLY with valid JSON.`;

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({
        model: "gemini-2.5-flash",
        contents: prompt,
      });

      const raw = (result.text || "").replace(/```json\s*/g, "").replace(/```/g, "").trim();
      let analysis: any;
      try {
        analysis = JSON.parse(raw);
      } catch {
        analysis = generateSmartFallback(ticketNumber, ticketTitle, action, elapsedTime, context);
      }

      res.json(analysis);
    } catch (error: any) {
      console.error("[AI Work Analysis] Error:", error.message);
      const fallback = generateSmartFallback(
        req.body.ticketNumber, req.body.ticketTitle,
        req.body.action, req.body.elapsedTime, req.body.context
      );
      res.json(fallback);
    }
  });

  // Smart fallback note generation (no AI needed)
  function generateSmartFallback(
    ticketNumber: string, ticketTitle: string,
    action: string, elapsedTime?: number, contextStr?: string
  ) {
    let pageContext: any = {};
    try { pageContext = JSON.parse(contextStr || '{}'); } catch { }

    const startVerbs = [
      'Initiated investigation of', 'Began troubleshooting',
      'Started working on', 'Commenced review of',
      'Opened and assessed', 'Started analysis of'
    ];
    const stopVerbs = [
      'Completed work session for', 'Finished investigation of',
      'Concluded troubleshooting session for', 'Wrapped up review of',
      'Paused work on', 'Saved progress on'
    ];

    const verbs = action === 'start' ? startVerbs : stopVerbs;
    const verb = verbs[Math.floor(Math.random() * verbs.length)];
    const durationStr = elapsedTime
      ? `. Duration: ${Math.floor(elapsedTime / 60)}m ${elapsedTime % 60}s`
      : '';

    // Detect activity from page context
    const activities: string[] = [];
    const pt = pageContext.pageType || '';
    if (pt === 'ticket_detail') activities.push('Reviewed ticket details');
    if (pageContext.formData && Object.keys(pageContext.formData).length > 0) {
      activities.push('Examined form fields and configuration');
    }
    if ((pageContext.badges || []).some((b: string) => b.includes('SLA'))) {
      activities.push('Checked SLA compliance status');
    }
    if (activities.length === 0) activities.push('Worked on incident');

    const activityTypes: Record<string, string> = {
      'ticket_detail': 'ticket_resolution',
      'settings': 'configuration',
      'reports': 'documentation',
      'knowledge_base': 'investigation'
    };

    return {
      summary: `${verb} incident ${ticketNumber}: ${ticketTitle || 'Service request'}${durationStr}`,
      activityType: activityTypes[pt] || 'ticket_resolution',
      confidence: 0.7,
      actionVerb: verb.split(' ')[0],
      detectedActivities: activities
    };
  }

  // ═══ Work Sessions CRUD ═══
  app.post("/api/work-sessions", async (req, res) => {
    try {
      const { user_id, user_name, ticket_id, ticket_number, start_time, stop_time, duration, start_context, stop_context, ai_notes_start, ai_notes_stop, status } = req.body;

      const result = await execute(
        `INSERT INTO work_sessions (user_id, user_name, ticket_id, ticket_number, start_time, stop_time, duration, start_context, stop_context, ai_notes_start, ai_notes_stop, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [user_id, user_name, ticket_id, ticket_number, start_time, stop_time || null, duration || 0, start_context || null, stop_context || null, ai_notes_start || null, ai_notes_stop || null, status || 'active']
      );

      const created = await query("SELECT * FROM work_sessions WHERE id = ?", [result.insertId]);
      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error("Error creating work session:", error);
      res.status(500).json({ error: "Failed to create work session" });
    }
  });

  app.put("/api/work-sessions/:id", async (req, res) => {
    try {
      const { id } = req.params;
      const fields = Object.keys(req.body).filter(k => k !== 'id');
      const setClause = fields.map(k => `${k} = ?`).join(', ');
      const values = [...fields.map(k => req.body[k]), id];

      await execute(`UPDATE work_sessions SET ${setClause} WHERE id = ?`, values);
      const updated = await query("SELECT * FROM work_sessions WHERE id = ?", [id]);
      res.json({ id: id.toString(), ...updated[0] });
    } catch (error: any) {
      console.error("Error updating work session:", error);
      res.status(500).json({ error: "Failed to update work session" });
    }
  });

  app.get("/api/work-sessions", async (req, res) => {
    try {
      const { user_id, ticket_id, status: wsStatus } = req.query;
      let sql = "SELECT * FROM work_sessions WHERE 1=1";
      const values: any[] = [];

      if (user_id) { sql += " AND user_id = ?"; values.push(user_id); }
      if (ticket_id) { sql += " AND ticket_id = ?"; values.push(ticket_id); }
      if (wsStatus) { sql += " AND status = ?"; values.push(wsStatus); }

      sql += " ORDER BY created_at DESC";
      const rows = await query(sql, values);
      res.json(rows.map(r => ({ id: r.id?.toString(), ...r })));
    } catch (error: any) {
      console.error("Error fetching work sessions:", error);
      res.status(500).json({ error: "Failed to fetch work sessions" });
    }
  });

  // ═══ WORK NOTES TABLE INIT ═══
  try {
    if (useSQLite) {
      const db = await getSQLiteDb();
      await db.exec(`
        CREATE TABLE IF NOT EXISTS work_notes (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id TEXT NOT NULL,
          user_name TEXT,
          ticket_id TEXT,
          ticket_number TEXT,
          session_id TEXT,
          note_type TEXT NOT NULL,
          screenshot_url TEXT,
          screenshot_filename TEXT,
          screenshot_format TEXT,
          screenshot_size_kb INTEGER,
          ai_note TEXT,
          duration_seconds INTEGER,
          duration_display TEXT,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_wn_user ON work_notes(user_id);
        CREATE INDEX IF NOT EXISTS idx_wn_ticket ON work_notes(ticket_id);
        CREATE INDEX IF NOT EXISTS idx_wn_session ON work_notes(session_id);
      `);
    } else {
      await execute(`
        CREATE TABLE IF NOT EXISTS work_notes (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id VARCHAR(128) NOT NULL,
          user_name VARCHAR(255),
          ticket_id VARCHAR(128),
          ticket_number VARCHAR(50),
          session_id VARCHAR(128),
          note_type ENUM('start','stop') NOT NULL,
          screenshot_url TEXT,
          screenshot_filename VARCHAR(255),
          screenshot_format VARCHAR(10),
          screenshot_size_kb INT,
          ai_note TEXT,
          duration_seconds INT,
          duration_display VARCHAR(20),
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_wn_user (user_id),
          INDEX idx_wn_ticket (ticket_id),
          INDEX idx_wn_session (session_id)
        ) ENGINE=InnoDB
      `);
    }
    console.log('[DB] Work notes table initialized');
  } catch (e: any) {
    console.error('[DB] Work notes table init failed:', e.message);
  }

  // ═══ MESSAGE HISTORY TABLE INIT ═══
  try {
    if (useSQLite) {
      const db = await getSQLiteDb();
      await db.exec(`
        CREATE TABLE IF NOT EXISTS message_history (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id TEXT NOT NULL,
          user_name TEXT,
          message_type TEXT NOT NULL,
          recipient TEXT,
          message_content TEXT,
          sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_mh_user ON message_history(user_id);
        CREATE INDEX IF NOT EXISTS idx_mh_type ON message_history(message_type);
      `);
    } else {
      await execute(`
        CREATE TABLE IF NOT EXISTS message_history (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id VARCHAR(128) NOT NULL,
          user_name VARCHAR(255),
          message_type ENUM('email','whatsapp') NOT NULL,
          recipient VARCHAR(255),
          message_content TEXT,
          sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_mh_user (user_id),
          INDEX idx_mh_type (message_type)
        ) ENGINE=InnoDB
      `);
    }
    console.log('[DB] Message history table initialized');
  } catch (e: any) {
    console.error('[DB] Message history table init failed:', e.message);
  }

  // ═══ ACTIVITY TRACKER TABLES INIT ═══
  try {
    if (useSQLite) {
      const db = await getSQLiteDb();
      await db.exec(`
        CREATE TABLE IF NOT EXISTS activity_sessions (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          session_id TEXT NOT NULL UNIQUE,
          user_id TEXT NOT NULL,
          user_name TEXT,
          start_time DATETIME NOT NULL,
          stop_time DATETIME,
          duration INTEGER DEFAULT 0,
          summary TEXT,
          status TEXT DEFAULT 'active',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_as_user ON activity_sessions(user_id);
        CREATE INDEX IF NOT EXISTS idx_as_session ON activity_sessions(session_id);

        CREATE TABLE IF NOT EXISTS activity_entries (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          session_id TEXT,
          user_id TEXT NOT NULL,
          screenshot_url TEXT,
          screenshot_filename TEXT,
          screenshot_format TEXT,
          screenshot_size_kb INTEGER,
          activity_label TEXT,
          description TEXT,
          confidence REAL DEFAULT 0,
          captured_at DATETIME,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_ae_session ON activity_entries(session_id);
        CREATE INDEX IF NOT EXISTS idx_ae_user ON activity_entries(user_id);
      `);
    } else {
      await execute(`
        CREATE TABLE IF NOT EXISTS activity_sessions (
          id INT AUTO_INCREMENT PRIMARY KEY,
          session_id VARCHAR(128) NOT NULL UNIQUE,
          user_id VARCHAR(128) NOT NULL,
          user_name VARCHAR(255),
          start_time TIMESTAMP NOT NULL,
          stop_time TIMESTAMP NULL,
          duration INT DEFAULT 0,
          summary TEXT,
          status VARCHAR(20) DEFAULT 'active',
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_as_user (user_id),
          INDEX idx_as_session (session_id)
        ) ENGINE=InnoDB
      `);
      await execute(`
        CREATE TABLE IF NOT EXISTS activity_entries (
          id INT AUTO_INCREMENT PRIMARY KEY,
          session_id VARCHAR(128),
          user_id VARCHAR(128) NOT NULL,
          screenshot_url TEXT,
          screenshot_filename VARCHAR(255),
          screenshot_format VARCHAR(10),
          screenshot_size_kb INT,
          activity_label VARCHAR(100),
          description TEXT,
          confidence DECIMAL(4,3) DEFAULT 0,
          captured_at TIMESTAMP NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_ae_session (session_id),
          INDEX idx_ae_user (user_id)
        ) ENGINE=InnoDB
      `);
    }
    console.log('[DB] Activity tracker tables initialized');
  } catch (e: any) {
    console.error('[DB] Activity tracker tables init failed:', e.message);
  }

  // ═══ AI ANALYZE ACTIVITY ═══
  app.post('/api/ai/analyze-activity', async (req: any, res: any) => {
    try {
      const {
        timestamp, previous_activity, userId,
        appName, pageUrl, pageTitle, pageType, ticketNumber,
        headings, formData, recentClicks,
        recentKeys, idleSeconds, scrollDepth,
        badges, visibleText,
      } = req.body;

      const apiKey = process.env.GEMINI_API_KEY;
      if (!apiKey || apiKey === 'MY_GEMINI_API_KEY' || apiKey === 'your_gemini_api_key_here') {
        return res.json(activityFallback(previous_activity, pageUrl, pageType, idleSeconds, appName, ticketNumber));
      }

      const app_    = appName || 'Connect IT';
      const prevStr = previous_activity ? `\nPrevious activity: ${previous_activity}` : '';
      const idleStr = idleSeconds > 60 ? `\nUser has been idle for ${idleSeconds} seconds — likely away from keyboard.` : '';
      const tickStr = ticketNumber ? `\nActive ticket: ${ticketNumber}` : '';
      const clickStr = recentClicks?.length ? `\nRecent clicks: ${recentClicks.join(' → ')}` : '';
      const keyStr  = recentKeys > 0 ? `\nKeystrokes since last snapshot: ${recentKeys}` : '';
      const headStr = headings?.length ? `\nPage headings: ${headings.join(' | ')}` : '';
      const formStr = formData && Object.keys(formData).length
        ? `\nActive form fields: ${Object.entries(formData).map(([k,v]) => `${k}="${v}"`).join(', ')}`
        : '';
      const badgeStr = badges?.length ? `\nStatus badges visible: ${badges.join(', ')}` : '';
      const textStr  = visibleText ? `\nVisible content snippet: ${visibleText}` : '';

      const prompt = `You are an AI work activity analyzer monitoring a user inside "${app_}", an IT service management application.

Application: ${app_}
Current page: ${pageType || pageUrl}
Page title: ${pageTitle || 'unknown'}
Scroll depth: ${scrollDepth || 0}%${tickStr}${prevStr}${idleStr}${clickStr}${keyStr}${headStr}${formStr}${badgeStr}${textStr}

Based on this context, determine what the user is doing. Choose ONE activity label from:
Ticket Work, Timesheet Entry, Documentation, Dashboard Review, Reports Analysis,
Settings Configuration, Knowledge Base, Calendar Review, Idle, General Work

IMPORTANT RULES:
- ALWAYS mention the app name "${app_}" in the description
- ALWAYS mention the specific page (${pageType}) in the description
- If a ticket number is present (${ticketNumber || 'none'}), mention it by name
- If idle > 60s, label as "Idle" and say user is away
- Be specific: mention what form fields are being filled, what was clicked, what headings are visible
- Use action verbs: "Reviewing...", "Updating...", "Working on...", "Navigating to..."
- 1-2 sentences max, professional tone
- Do NOT say "User is actively working in the application" — be specific

Example good descriptions:
- "Reviewing incident INC0012345 in Connect IT's Ticket Detail page, checking SLA status and resolution notes."
- "Updating timesheet entries in Connect IT's Weekly Timesheet, logging 3 hours for ticket work."
- "Browsing the Knowledge Base in Connect IT, reading articles related to network troubleshooting."

Respond ONLY with valid JSON:
{"activity": "Ticket Work", "description": "Reviewing incident INC0012345 in Connect IT...", "confidence": 0.92}`;

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({ model: 'gemini-2.5-flash', contents: prompt });
      const raw = (result.text || '').replace(/```json\s*/g, '').replace(/```/g, '').trim();

      let parsed: any;
      try { parsed = JSON.parse(raw); }
      catch { parsed = activityFallback(previous_activity, pageUrl, pageType, idleSeconds, appName, ticketNumber); }

      res.json({
        activity:    parsed.activity    || 'General Work',
        description: parsed.description || `Working in ${app_} on ${pageType || 'the application'}.`,
        confidence:  parsed.confidence  ?? 0.7,
      });
    } catch (error: any) {
      console.error('[AI Analyze Activity] Error:', error.message);
      res.json(activityFallback(
        req.body.previous_activity, req.body.pageUrl, req.body.pageType,
        req.body.idleSeconds, req.body.appName, req.body.ticketNumber
      ));
    }
  });

  function activityFallback(
    previousActivity?: string, pageUrl?: string, pageType?: string,
    idleSeconds?: number, appName?: string, ticketNumber?: string
  ): object {
    const app_ = appName || 'Connect IT';
    const page = pageType || 'the application';

    if (idleSeconds && idleSeconds > 60) {
      return { activity: 'Idle', description: `User has been idle for ${idleSeconds} seconds in ${app_}.`, confidence: 0.95 };
    }

    const pt = pageType || pageUrl || '';
    const ticket = ticketNumber ? ` on ${ticketNumber}` : '';

    const map: Record<string, [string, string]> = {
      'Ticket Detail':   ['Ticket Work',        `Reviewing ticket details${ticket} in ${app_}'s Ticket Detail page.`],
      'Ticket List':     ['Ticket Work',        `Browsing the ticket list in ${app_}, reviewing open incidents.`],
      'Timesheet':       ['Timesheet Entry',    `Updating timesheet records in ${app_}'s Timesheet module.`],
      'Weekly Timesheet':['Timesheet Entry',    `Logging work hours in ${app_}'s Weekly Timesheet view.`],
      'Dashboard':       ['Dashboard Review',   `Reviewing the incident dashboard in ${app_}.`],
      'Reports':         ['Reports Analysis',   `Analyzing reports and metrics in ${app_}'s Reports section.`],
      'Knowledge Base':  ['Knowledge Base',     `Browsing knowledge base articles in ${app_}.`],
      'Calendar':        ['Calendar Review',    `Reviewing scheduled events in ${app_}'s Calendar.`],
      'Settings':        ['Settings Configuration', `Configuring system settings in ${app_}.`],
      'CMDB':            ['General Work',       `Managing configuration items in ${app_}'s CMDB.`],
      'Problem Management': ['General Work',    `Working on problem management tasks in ${app_}.`],
      'Change Management':  ['General Work',    `Reviewing change requests in ${app_}.`],
    };

    for (const [k, [act, desc]] of Object.entries(map)) {
      if (pt.includes(k)) return { activity: act, description: desc, confidence: 0.75 };
    }

    return { activity: 'General Work', description: `Working in ${app_} on the ${page} page.`, confidence: 0.6 };
  }

  // ═══ AI GENERATE SUMMARY ═══
  app.post('/api/ai/generate-summary', async (req: any, res: any) => {
    try {
      const { session_data, duration_seconds } = req.body;
      if (!session_data || !Array.isArray(session_data) || session_data.length === 0) {
        return res.json({ summary: 'Session completed. User was actively working.' });
      }

      const apiKey = process.env.GEMINI_API_KEY;
      if (!apiKey || apiKey === 'MY_GEMINI_API_KEY' || apiKey === 'your_gemini_api_key_here') {
        const activities = [...new Set(session_data.map((e: any) => e.activity))].join(', ');
        return res.json({ summary: `User worked on: ${activities}. Session duration: ${Math.floor((duration_seconds || 0) / 60)} minutes.` });
      }

      const activityList = session_data.map((e: any) =>
        `[${new Date(e.timestamp).toLocaleTimeString()}] ${e.activity}: ${e.description}`
      ).join('\n');

      const durationStr = duration_seconds
        ? `${Math.floor(duration_seconds / 3600)}h ${Math.floor((duration_seconds % 3600) / 60)}m`
        : 'unknown';

      const prompt = `You are an AI work session summarizer. Generate a concise professional summary of a user's work session.

Session duration: ${durationStr}
Activity log:
${activityList}

Write a 2-3 sentence professional summary describing:
1. What the user primarily worked on
2. Any task transitions or variety
3. Overall productivity assessment

Be specific, professional, and use past tense. Do NOT use bullet points.
Respond ONLY with JSON: {"summary": "your summary here"}`;

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({ model: 'gemini-2.5-flash', contents: prompt });
      const raw = (result.text || '').replace(/```json\s*/g, '').replace(/```/g, '').trim();

      let summary = 'Session completed successfully.';
      try { summary = JSON.parse(raw).summary || summary; } catch { summary = raw.length < 500 ? raw : summary; }

      res.json({ summary });
    } catch (error: any) {
      console.error('[AI Generate Summary] Error:', error.message);
      res.json({ summary: 'Session completed. User was actively working during this period.' });
    }
  });

  // ═══ ACTIVITY SESSIONS CRUD ═══
  app.post('/api/activity-sessions', async (req: any, res: any) => {
    try {
      const { session_id, user_id, user_name, start_time, status } = req.body;
      if (!user_id || !session_id) return res.status(400).json({ error: 'Missing user_id or session_id' });
      const result = await execute(
        `INSERT INTO activity_sessions (session_id, user_id, user_name, start_time, status) VALUES (?, ?, ?, ?, ?)`,
        [session_id, user_id, user_name || null, start_time || new Date().toISOString(), status || 'active']
      );
      const created = await query('SELECT * FROM activity_sessions WHERE id = ?', [result.insertId]);
      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error('[Activity Sessions] Create failed:', error.message);
      res.status(500).json({ error: 'Failed to create activity session' });
    }
  });

  app.put('/api/activity-sessions/:id', async (req: any, res: any) => {
    try {
      const { id } = req.params;
      const fields = Object.keys(req.body).filter(k => k !== 'id');
      const setClause = fields.map(k => `${k} = ?`).join(', ');
      const values = [...fields.map(k => req.body[k]), id];
      await execute(`UPDATE activity_sessions SET ${setClause} WHERE id = ?`, values);
      const updated = await query('SELECT * FROM activity_sessions WHERE id = ?', [id]);
      res.json({ id: id.toString(), ...updated[0] });
    } catch (error: any) {
      console.error('[Activity Sessions] Update failed:', error.message);
      res.status(500).json({ error: 'Failed to update activity session' });
    }
  });

  app.get('/api/activity-sessions', async (req: any, res: any) => {
    try {
      const { user_id, status: s, limit = '20' } = req.query;
      let sql = 'SELECT * FROM activity_sessions WHERE 1=1';
      const values: any[] = [];
      if (user_id) { sql += ' AND user_id = ?'; values.push(user_id); }
      if (s) { sql += ' AND status = ?'; values.push(s); }
      sql += ' ORDER BY created_at DESC LIMIT ?';
      values.push(parseInt(limit as string) || 20);
      const rows = await query(sql, values);
      res.json(rows.map((r: any) => ({ id: r.id?.toString(), ...r })));
    } catch (error: any) {
      res.status(500).json({ error: 'Failed to fetch activity sessions' });
    }
  });

  // ═══ ACTIVITY ENTRIES CRUD ═══
  app.post('/api/activity-entries', async (req: any, res: any) => {
    try {
      const { session_id, user_id, screenshot_url, screenshot_filename, screenshot_format,
              screenshot_size_kb, activity_label, description, confidence, captured_at } = req.body;
      if (!user_id) return res.status(400).json({ error: 'Missing user_id' });
      const result = await execute(
        `INSERT INTO activity_entries (session_id, user_id, screenshot_url, screenshot_filename, screenshot_format, screenshot_size_kb, activity_label, description, confidence, captured_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [session_id || null, user_id, screenshot_url || null, screenshot_filename || null,
         screenshot_format || null, screenshot_size_kb || null, activity_label || null,
         description || null, confidence || 0, captured_at || null]
      );
      const created = await query('SELECT * FROM activity_entries WHERE id = ?', [result.insertId]);
      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error('[Activity Entries] Create failed:', error.message);
      res.status(500).json({ error: 'Failed to save activity entry' });
    }
  });

  app.get('/api/activity-entries', async (req: any, res: any) => {
    try {
      const { user_id, session_id, limit = '100' } = req.query;
      let sql = 'SELECT * FROM activity_entries WHERE 1=1';
      const values: any[] = [];
      if (user_id) { sql += ' AND user_id = ?'; values.push(user_id); }
      if (session_id) { sql += ' AND session_id = ?'; values.push(session_id); }
      sql += ' ORDER BY captured_at ASC LIMIT ?';
      values.push(parseInt(limit as string) || 100);
      const rows = await query(sql, values);
      res.json(rows.map((r: any) => ({ id: r.id?.toString(), ...r })));
    } catch (error: any) {
      res.status(500).json({ error: 'Failed to fetch activity entries' });
    }
  });

  // ═══ SCREENSHOT UPLOAD ═══
  // Ensure uploads directory exists
  const uploadsDir = path.join(__dirname, 'public', 'uploads', 'screenshots');
  if (!fs.existsSync(uploadsDir)) {
    fs.mkdirSync(uploadsDir, { recursive: true });
  }

  const screenshotStorage = multer.diskStorage({
    destination: (_req, _file, cb) => cb(null, uploadsDir),
    filename: (_req, file, cb) => {
      // Preserve the original filename (timesheet_start_<ts>.png / timesheet_stop_<ts>.jpeg)
      // Sanitise to prevent path traversal
      const safe = file.originalname.replace(/[^a-zA-Z0-9._-]/g, '_');
      cb(null, safe);
    },
  });

  const screenshotUpload = multer({
    storage: screenshotStorage,
    limits: { fileSize: 15 * 1024 * 1024 }, // 15 MB max
    fileFilter: (_req, file, cb) => {
      // STRICT: only PNG and JPEG accepted
      const allowed = ['image/png', 'image/jpeg', 'image/jpg'];
      if (allowed.includes(file.mimetype)) {
        cb(null, true);
      } else {
        cb(new Error(`Invalid file type: ${file.mimetype}. Only PNG and JPEG are accepted.`));
      }
    },
  });

  app.post('/api/upload-screenshot', screenshotUpload.single('screenshot'), (req: any, res: any) => {
    try {
      if (!req.file) {
        return res.status(400).json({ error: 'No screenshot file received' });
      }
      // Determine format from MIME
      const format = req.file.mimetype === 'image/png' ? 'PNG' : 'JPEG';
      const sizeKB  = Math.round(req.file.size / 1024);
      const imageUrl = `/uploads/screenshots/${req.file.filename}`;

      console.log(`[Upload] Screenshot saved: ${req.file.filename} (${format}, ${sizeKB}KB)`);
      res.json({
        image_url: imageUrl,
        filename: req.file.filename,
        format,
        size_kb: sizeKB,
      });    } catch (error: any) {
      console.error('[Upload] Screenshot upload failed:', error.message);
      res.status(500).json({ error: 'Screenshot upload failed' });
    }
  });

  // Serve uploaded screenshots statically
  app.use('/uploads', express.static(path.join(__dirname, 'public', 'uploads')));

  // ═══ AI GENERATE NOTES (for Work Notes Chat) ═══
  app.post('/api/ai/generate-notes', async (req: any, res: any) => {
    try {
      const {
        context,        // 'start' | 'stop'
        ticketNumber,
        ticketTitle,
        userId,
        userName,
        durationSeconds,
        pageUrl,
        pageTitle,
      } = req.body;

      const apiKey = process.env.GEMINI_API_KEY;

      // Smart fallback when no API key
      if (!apiKey || apiKey === 'MY_GEMINI_API_KEY' || apiKey === 'your_gemini_api_key_here') {
        const note = generateWorkNoteFallback(context, ticketNumber, ticketTitle, durationSeconds);
        return res.json({ note });
      }

      const actionStr = context === 'start' ? 'starting' : 'stopping';
      const durationStr = durationSeconds
        ? `\nSession duration: ${Math.floor(durationSeconds / 3600)}h ${Math.floor((durationSeconds % 3600) / 60)}m ${durationSeconds % 60}s`
        : '';
      const ticketStr = ticketNumber ? `\nTicket: ${ticketNumber}${ticketTitle ? ` — ${ticketTitle}` : ''}` : '';

      const prompt = `You are an IT service management work notes assistant. Generate a concise, professional 1-2 sentence work note for a technician who is ${actionStr} a work session.

Technician: ${userName || 'Technician'}${ticketStr}${durationStr}
Current page: ${pageUrl || 'timesheet'}
Page title: ${pageTitle || 'Timesheet'}

Rules:
- Use action-based language: "Started working on...", "Continued development of...", "Reviewed...", "Completed..."
- Be specific and professional
- 1-2 sentences maximum
- Detect activity type from context (coding, support, documentation, etc.)
- For stop context, mention what was accomplished or the duration

Respond with ONLY a JSON object: {"note": "your note here"}`;

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({
        model: 'gemini-2.5-flash',
        contents: prompt,
      });

      const raw = (result.text || '').replace(/```json\s*/g, '').replace(/```/g, '').trim();
      let note: string;
      try {
        const parsed = JSON.parse(raw);
        note = parsed.note || generateWorkNoteFallback(context, ticketNumber, ticketTitle, durationSeconds);
      } catch {
        // If AI returned plain text instead of JSON, use it directly
        note = raw.length > 10 && raw.length < 500
          ? raw
          : generateWorkNoteFallback(context, ticketNumber, ticketTitle, durationSeconds);
      }

      res.json({ note });
    } catch (error: any) {
      console.error('[AI Generate Notes] Error:', error.message);
      const note = generateWorkNoteFallback(
        req.body.context, req.body.ticketNumber,
        req.body.ticketTitle, req.body.durationSeconds
      );
      res.json({ note });
    }
  });

  function generateWorkNoteFallback(
    context: string,
    ticketNumber?: string,
    ticketTitle?: string,
    durationSeconds?: number
  ): string {
    const ticket = ticketNumber ? ` for ${ticketNumber}${ticketTitle ? `: ${ticketTitle}` : ''}` : '';
    const duration = durationSeconds
      ? ` Duration: ${Math.floor(durationSeconds / 3600)}h ${Math.floor((durationSeconds % 3600) / 60)}m.`
      : '';

    if (context === 'start') {
      const verbs = ['Started working on', 'Initiated work session', 'Began investigation of', 'Commenced work on'];
      const verb = verbs[Math.floor(Math.random() * verbs.length)];
      return `${verb} timesheet entry${ticket}. Session tracking initiated.`;
    } else {
      const verbs = ['Completed work session', 'Concluded work session', 'Finished work session', 'Wrapped up session'];
      const verb = verbs[Math.floor(Math.random() * verbs.length)];
      return `${verb}${ticket}.${duration} Progress saved.`;
    }
  }

  // ═══ WORK NOTES CRUD ═══
  app.post('/api/work-notes', async (req: any, res: any) => {
    try {
      const {
        user_id, user_name, ticket_id, ticket_number,
        session_id, note_type, screenshot_url,
        screenshot_filename, screenshot_format, screenshot_size_kb,
        ai_note, duration_seconds, duration_display,
      } = req.body;

      if (!user_id || !note_type) {
        return res.status(400).json({ error: 'Missing required fields: user_id, note_type' });
      }
      if (!['start', 'stop'].includes(note_type)) {
        return res.status(400).json({ error: 'note_type must be "start" or "stop"' });
      }

      const result = await execute(
        `INSERT INTO work_notes
          (user_id, user_name, ticket_id, ticket_number, session_id, note_type,
           screenshot_url, screenshot_filename, screenshot_format, screenshot_size_kb,
           ai_note, duration_seconds, duration_display)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
        [
          user_id,
          user_name || null,
          ticket_id || null,
          ticket_number || null,
          session_id || null,
          note_type,
          screenshot_url || null,
          screenshot_filename || null,
          screenshot_format || null,
          screenshot_size_kb || null,
          ai_note || null,
          duration_seconds || null,
          duration_display || null,
        ]
      );

      const created = await query('SELECT * FROM work_notes WHERE id = ?', [result.insertId]);
      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error('[Work Notes] Create failed:', error.message);
      res.status(500).json({ error: 'Failed to save work note' });
    }
  });

  app.get('/api/work-notes', async (req: any, res: any) => {
    try {
      const { user_id, ticket_id, session_id, limit = '50' } = req.query;

      let sql = 'SELECT * FROM work_notes WHERE 1=1';
      const values: any[] = [];

      if (user_id) { sql += ' AND user_id = ?'; values.push(user_id); }
      if (ticket_id) { sql += ' AND ticket_id = ?'; values.push(ticket_id); }
      if (session_id) { sql += ' AND session_id = ?'; values.push(session_id); }

      sql += ' ORDER BY created_at DESC LIMIT ?';
      values.push(parseInt(limit as string) || 50);

      const rows = await query(sql, values);
      // Return in chronological order for chat display
      res.json(rows.reverse().map((r: any) => ({ id: r.id?.toString(), ...r })));
    } catch (error: any) {
      console.error('[Work Notes] Fetch failed:', error.message);
      res.status(500).json({ error: 'Failed to fetch work notes' });
    }
  });

  // ═══ MESSAGE HISTORY CRUD ═══
  app.post('/api/message-history', async (req: any, res: any) => {
    try {
      const { user_id, user_name, message_type, recipient, message_content } = req.body;
      if (!user_id || !message_type) {
        return res.status(400).json({ error: 'Missing required fields: user_id, message_type' });
      }
      const result = await execute(
        `INSERT INTO message_history (user_id, user_name, message_type, recipient, message_content) VALUES (?, ?, ?, ?, ?)`,
        [user_id, user_name || null, message_type, recipient || null, message_content || null]
      );
      const created = await query('SELECT * FROM message_history WHERE id = ?', [result.insertId]);
      res.json({ id: result.insertId.toString(), ...created[0] });
    } catch (error: any) {
      console.error('[Message History] Save failed:', error.message);
      res.status(500).json({ error: 'Failed to save message history' });
    }
  });

  app.get('/api/message-history', async (req: any, res: any) => {
    try {
      const { user_id, message_type, limit = '100' } = req.query;
      let sql = 'SELECT * FROM message_history WHERE 1=1';
      const values: any[] = [];
      if (user_id) { sql += ' AND user_id = ?'; values.push(user_id); }
      if (message_type) { sql += ' AND message_type = ?'; values.push(message_type); }
      sql += ' ORDER BY sent_at DESC LIMIT ?';
      values.push(parseInt(limit as string) || 100);
      const rows = await query(sql, values);
      res.json(rows.map((r: any) => ({ id: r.id?.toString(), ...r })));
    } catch (error: any) {
      console.error('[Message History] Fetch failed:', error.message);
      res.status(500).json({ error: 'Failed to fetch message history' });
    }
  });

  // AI Classify Endpoint
  app.post("/api/ai/classify", async (req, res) => {
    try {
      const { text } = req.body;
      if (!text || typeof text !== "string") {
        return res.status(400).json({ error: "Missing text to classify" });
      }

      const apiKey = process.env.GEMINI_API_KEY;
      if (!apiKey || apiKey === "MY_GEMINI_API_KEY") {
        return res.status(500).json({ error: "Gemini API key not configured" });
      }

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({
        model: "gemini-2.5-flash",
        contents: `Analyze the following IT issue and classify it.\nIssue: "${text}"\n\nRespond ONLY with a valid JSON object with "category" and "priority" keys.\nCategory must be one of: "Network", "Software", "Hardware", "Database", "Inquiry / Help".\nPriority must be one of: "Low", "Medium", "High", "Critical".\nExample: {"category": "Network", "priority": "High"}`,
      });

      const raw = (result.text || "").replace(/```json\s*/g, "").replace(/```/g, "").trim();
      let classification: any = { category: "Inquiry / Help", priority: "Medium" };
      try { classification = JSON.parse(raw); } catch { }

      res.json(classification);
    } catch (error: any) {
      console.error("[AI Classify] Error:", error.message);
      res.status(500).json({ error: "AI classification failed", detail: error.message });
    }
  });

  // AI Suggest Endpoint
  app.post("/api/ai/suggest", async (req, res) => {
    try {
      const { text } = req.body;
      if (!text || typeof text !== "string") {
        return res.status(400).json({ error: "Missing text for suggestion" });
      }

      const apiKey = process.env.GEMINI_API_KEY;
      if (!apiKey || apiKey === "MY_GEMINI_API_KEY") {
        return res.status(500).json({ error: "Gemini API key not configured" });
      }

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({
        model: "gemini-2.5-flash",
        contents: "A user is experiencing an IT issue. Provide a short, direct suggested solution to help them fix it before creating a ticket. Keep it under 3 sentences and be friendly.\n\nIssue: \"" + text + "\"",
      });

      const suggestion = result.text || "Please create a ticket and our team will assist you shortly.";
      res.json({ suggestion });
    } catch (error: any) {
      console.error("[AI Suggest] Error:", error.message);
      res.status(500).json({ error: "AI suggestion failed", detail: error.message });
    }
  });

  // AI Chat Endpoint
  app.post("/api/ai/chat", async (req, res) => {
    try {
      const { message, history } = req.body;
      if (!message || typeof message !== "string") {
        return res.status(400).json({ error: "Invalid message" });
      }

      const apiKey = process.env.GEMINI_API_KEY;
      if (!apiKey || apiKey === "MY_GEMINI_API_KEY") {
        return res.status(500).json({
          error: "Gemini API key not configured.",
          detail: "API key missing or placeholder"
        });
      }

      const ai = new GoogleGenAI({ apiKey });
      const result = await ai.models.generateContent({
        model: "gemini-1.5-flash",
        contents: `You are Kiru, a friendly and intelligent IT service management assistant.
Personality: Warm, professional, and helpful.
Capabilities: 
1. Answer general questions.
2. Help with IT issues (Network, Software, Hardware, etc.).
3. Manage tickets using your available tools (create, get status, list).

When a user reports an issue, try to understand the impact and urgency. 
If they want to create a ticket, use the 'create_ticket' tool.
Always confirm the details before creating a ticket if possible.
Respond in a conversational, friendly tone.

User message: "${message}"

Please respond appropriately as a helpful IT assistant.`,
      });

      const responseText = result.text || "I processed your request but couldn't generate a text response.";
      res.json({ response: responseText });

    } catch (error: any) {
      console.error("[Kiru AI] Error:", error.message);
      res.status(500).json({
        error: "Failed to get AI response",
        detail: error.message
      });
    }
  });

  // Vite middleware for development
  if (process.env.NODE_ENV !== "production") {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.join(distPath, "index.html"));
    });
  }

  if (process.argv.includes("--test-only")) {
    console.log("[Test Mode] Skipping server listen.");
    return;
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Server running on http://localhost:${PORT}`);
    console.log(`[MySQL] Database: ${dbConfig.database} at ${dbConfig.host}:${dbConfig.port}`);
  });
}

startServer().catch(error => {
  console.error("Failed to start server:", error);
  process.exit(1);
});
