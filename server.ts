import express from "express";
import { createServer as createViteServer } from "vite";
import path from "path";
import { fileURLToPath } from "url";
import cron from "node-cron";
import admin from "firebase-admin";
import { getFirestore } from "firebase-admin/firestore";
import { GoogleGenAI } from "@google/genai";
import { config as loadEnv } from "dotenv";
import firebaseConfig from "./firebase-applet-config.json" with { type: "json" };

// Load environment variables from .env file
loadEnv();

// Log API key status at startup (masked for security)
const geminiKey = process.env.GEMINI_API_KEY;
console.log(`[Kiru AI] GEMINI_API_KEY: ${geminiKey && geminiKey !== "MY_GEMINI_API_KEY" ? "✓ Loaded" : "✗ NOT SET — Kiru AI will not work"}`);

// Ensure environment variables are set for the correct project to guide the Admin SDK
process.env.GCLOUD_PROJECT = firebaseConfig.projectId;
process.env.GOOGLE_CLOUD_PROJECT = firebaseConfig.projectId;

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Initialize Firebase Admin explicitly with the provisioned Project ID
// This prevents auto-detection from targeting the wrong internal project
if (admin.apps.length === 0) {
  admin.initializeApp({
    projectId: firebaseConfig.projectId
  });
}
const serverApp = admin.app();

// Use the provisioned database ID for all Firestore operations
const dbId = firebaseConfig.firestoreDatabaseId;
const getDb = () => getFirestore(serverApp, dbId);

console.log(`[Firebase] Service active on Project: ${serverApp.options.projectId || "default"}, DB: ${dbId}`);

async function escalateStaleTickets() {
  console.log(`[SLA Engine] Checking tickets...`);
  const now = new Date();
  const nowMs = now.getTime();

  try {
    const db = getDb();
    const snapshot = await db.collection("tickets").get();
    console.log(`[SLA Engine] Fetched ${snapshot.docs.length} tickets.`);
    
    for (const ticketDoc of snapshot.docs) {
      const ticket = ticketDoc.data() as any;
      if (["Resolved", "Closed", "Canceled"].includes(ticket.status)) continue;
      
      const updates: any = {};
      const historyEntries: any[] = [];
      const isPaused = ticket.status === "On Hold" || ticket.status === "Waiting for Customer";

      if (isPaused) continue;

      // Response SLA Check
      if (ticket.responseDeadline && !ticket.firstResponseAt && ticket.responseSlaStatus !== "Breached" && ticket.responseSlaStatus !== "Completed") {
        const deadline = new Date(ticket.responseDeadline).getTime();
        const diff = deadline - nowMs;
        
        if (diff <= 0) {
          updates.responseSlaStatus = "Breached";
          historyEntries.push({ 
            action: "Response SLA BREACHED", 
            timestamp: now.toISOString(), 
            user: "SLA Engine" 
          });
        } else if (diff < (deadline - new Date(ticket.createdAt).getTime()) * 0.2) {
          if (ticket.responseSlaStatus !== "At Risk") {
            updates.responseSlaStatus = "At Risk";
          }
        }
      }

      // Resolution SLA Check
      if (ticket.resolutionDeadline && !ticket.resolvedAt && ticket.resolutionSlaStatus !== "Breached" && ticket.resolutionSlaStatus !== "Completed") {
        const deadline = new Date(ticket.resolutionDeadline).getTime();
        const diff = deadline - nowMs;

        if (diff <= 0) {
          updates.resolutionSlaStatus = "Breached";
          updates.priority = "1 - Critical";
          historyEntries.push({ 
            action: "Resolution SLA BREACHED: Ticket escalated to Critical", 
            timestamp: now.toISOString(), 
            user: "SLA Engine" 
          });
        } else if (diff < (deadline - new Date(ticket.createdAt).getTime()) * 0.2) {
          if (ticket.resolutionSlaStatus !== "At Risk") {
            updates.resolutionSlaStatus = "At Risk";
          }
        }
      }

      if (Object.keys(updates).length > 0) {
        await ticketDoc.ref.update({
          ...updates,
          updatedAt: admin.firestore.FieldValue.serverTimestamp(),
          history: [...(ticket.history || []), ...historyEntries]
        });
      }
    }
  } catch (error: any) {
    console.error(`[SLA Engine] Error: ${error.code} - ${error.message}`);
    // If we get permission denied in the background task, it's usually an IAM sync issue
    console.warn("[SLA Engine] Ensure the service account has 'Cloud Datastore User' role.");
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

  // API Routes
  app.get("/api/health", (req, res) => {
    res.json({ status: "ok" });
  });

  app.get("/api/db-test", async (req, res) => {
    try {
      const db = getDb();
      const snapshot = await db.collection("tickets").limit(1).get();
      res.json({ 
        status: "connected", 
        project: serverApp.options.projectId || "auto-detected",
        database: dbId,
        count: snapshot.size
      });
    } catch (error: any) {
      console.error("[Diagnostic] DB Test failed:", error.message);
      res.status(500).json({ 
        status: "error", 
        error: error.message,
        code: error.code,
        project: serverApp.options.projectId || "auto-detected",
        database: dbId
      });
    }
  });

  // Ticket Endpoints
  app.get("/api/tickets/all", async (req, res) => {
    try {
      const snapshot = await getDb().collection("tickets").orderBy("createdAt", "desc").get();
      const tickets = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      res.json(tickets);
    } catch (error) {
      res.status(500).json({ error: "Failed to fetch tickets" });
    }
  });

  app.get("/api/tickets/open", async (req, res) => {
    try {
      const snapshot = await getDb().collection("tickets")
        .where("status", "not-in", ["Resolved", "Closed"])
        .get();
      
      const tickets = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      // Manual sorting because not-in requires specific index for multi-field sort
      tickets.sort((a: any, b: any) => {
        const dateA = a.createdAt?.toDate() || 0;
        const dateB = b.createdAt?.toDate() || 0;
        return dateB - dateA;
      });
      res.json(tickets);
    } catch (error) {
      console.error(error);
      res.status(500).json({ error: "Failed to fetch open tickets" });
    }
  });

  app.get("/api/tickets/assigned/:userId", async (req, res) => {
    try {
      const snapshot = await getDb().collection("tickets")
        .where("assignedTo", "==", req.params.userId)
        .orderBy("createdAt", "desc")
        .get();
      const tickets = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      res.json(tickets);
    } catch (error) {
      res.status(500).json({ error: "Failed to fetch assigned tickets" });
    }
  });

  app.get("/api/tickets/unassigned", async (req, res) => {
    try {
      const snapshot = await getDb().collection("tickets")
        .where("assignedTo", "==", "")
        .orderBy("createdAt", "desc")
        .get();
      const tickets = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      res.json(tickets);
    } catch (error) {
      res.status(500).json({ error: "Failed to fetch unassigned tickets" });
    }
  });

  app.get("/api/tickets/resolved", async (req, res) => {
    try {
      const snapshot = await getDb().collection("tickets")
        .where("status", "in", ["Resolved", "Closed"])
        .get();
      const tickets = snapshot.docs.map(doc => ({ id: doc.id, ...doc.data() }));
      res.json(tickets);
    } catch (error) {
      res.status(500).json({ error: "Failed to fetch resolved tickets" });
    }
  });

  app.post("/api/tickets/create", async (req, res) => {
    try {
      console.log("Creating ticket with data:", JSON.stringify(req.body));
      
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
        ...req.body,
        assignmentGroup,
        createdAt: admin.firestore.FieldValue.serverTimestamp(),
        updatedAt: admin.firestore.FieldValue.serverTimestamp(),
        history: [{
          action: "Ticket Created via API",
          timestamp: new Date().toISOString(),
          user: req.body.caller || "System"
        }]
      };

      // Workflow Automation: Notify Manager for High Priority
      if (req.body.priority === "1 - Critical" || req.body.priority === "2 - High") {
        ticketData.history.push({
          action: "Manager Notified (High Priority)",
          timestamp: new Date().toISOString(),
          user: "System Automation"
        });
      }

      const docRef = await getDb().collection("tickets").add(ticketData);
      console.log("Ticket created with ID:", docRef.id);
      
      const createdDoc = await docRef.get();
      res.json({ id: createdDoc.id, ...createdDoc.data() });
    } catch (error) {
      console.error("Error creating ticket in server:", error);
      res.status(500).json({ error: "Failed to create ticket: " + (error instanceof Error ? error.message : String(error)) });
    }
  });

  app.put("/api/tickets/:id", async (req, res) => {
    try {
      const { id } = req.params;
      const ticketRef = getDb().collection("tickets").doc(id);
      
      // Calculate points if the ticket is being resolved
      let points = 0;
      if (req.body.status === "Resolved" || req.body.status === "Closed") {
        const ticketDoc = await ticketRef.get();
        const ticket = ticketDoc.data();
        if (ticket && ticket.resolutionDeadline && !ticket.resolvedAt) {
          const deadline = new Date(ticket.resolutionDeadline).getTime();
          const resolvedAt = new Date().getTime();
          const createdAt = ticket.createdAt?.toDate ? ticket.createdAt.toDate().getTime() : new Date(ticket.createdAt).getTime();
          
          if (resolvedAt < deadline) {
            // Award points based on speed: (Time Saved / Total SLA Time) * 100
            const totalSla = deadline - createdAt;
            const timeSaved = deadline - resolvedAt;
            points = Math.round((timeSaved / totalSla) * 100);
            if (points < 10) points = 10; // Minimum points for resolving within SLA
          } else {
            points = 5; // Minimal points for resolving after SLA
          }
        }
      }

      const updateData = {
        ...req.body,
        points: points > 0 ? admin.firestore.FieldValue.increment(points) : (req.body.points || 0),
        resolvedAt: (req.body.status === "Resolved" || req.body.status === "Closed") ? admin.firestore.FieldValue.serverTimestamp() : req.body.resolvedAt,
        updatedAt: admin.firestore.FieldValue.serverTimestamp()
      };
      await ticketRef.update(updateData);
      res.json({ id, ...updateData, pointsAwarded: points });
    } catch (error) {
      console.error("Error updating ticket:", error);
      res.status(500).json({ error: "Failed to update ticket" });
    }
  });

  app.delete("/api/tickets/:id", async (req, res) => {
    try {
      const { id } = req.params;
      await getDb().collection("tickets").doc(id).delete();
      res.json({ message: "Ticket deleted successfully" });
    } catch (error) {
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
      
      const snapshot = await getDb().collection("tickets")
        .where("status", "in", ["Resolved", "Closed"])
        .where("resolvedAt", ">=", today)
        .get();
      
      const userStats: { [key: string]: { name: string, points: number, resolvedCount: number } } = {};
      
      snapshot.docs.forEach(doc => {
        const data = doc.data();
        const userId = data.assignedTo || "Unassigned";
        const userName = data.assignedToName || userId;
        const points = data.points || 0;
        
        if (!userStats[userId]) {
          userStats[userId] = { name: userName, points: 0, resolvedCount: 0 };
        }
        userStats[userId].points += points;
        userStats[userId].resolvedCount += 1;
      });
      
      const leaderboard = Object.entries(userStats)
        .map(([id, stats]) => ({ id, ...stats }))
        .sort((a, b) => b.points - a.points);
        
      res.json(leaderboard);
    } catch (error) {
      console.error("Leaderboard Error:", error);
      res.status(500).json({ error: "Failed to fetch leaderboard" });
    }
  });

  // AI Classify Endpoint (used by Tickets.tsx AI Assist button)
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
      try { classification = JSON.parse(raw); } catch {}

      res.json(classification);
    } catch (error: any) {
      console.error("[AI Classify] Error:", error.message);
      res.status(500).json({ error: "AI classification failed", detail: error.message });
    }
  });

  // AI Suggest Endpoint (used by Tickets.tsx AI Assist button)
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

  // AI Chat Endpoint with History and Function Calling
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
  });
}

startServer();
