import express from "express";
import { createServer as createViteServer } from "vite";
import path from "path";
import { fileURLToPath } from "url";
import cron from "node-cron";
import mysql from 'mysql2/promise';
import { GoogleGenAI } from "@google/genai";
import { config as loadEnv } from "dotenv";

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
    const sqlite3 = await import('sqlite3');
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
    try { pageContext = JSON.parse(contextStr || '{}'); } catch {}

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
