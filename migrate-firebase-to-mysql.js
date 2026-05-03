#!/usr/bin/env node
/**
 * Firebase to MySQL Data Migration Script
 * 
 * This script migrates all data from Firebase Firestore to MySQL.
 * Run this after setting up the MySQL database schema.
 * 
 * Prerequisites:
 * 1. MySQL database must be created and schema applied
 * 2. Firebase service account credentials must be available
 * 3. Environment variables must be set
 * 
 * Usage:
 *   node migrate-firebase-to-mysql.js [--dry-run] [--collection=<name>]
 * 
 * Options:
 *   --dry-run      Preview changes without writing to MySQL
 *   --collection   Migrate only specific collection (users, tickets, etc.)
 */

import admin from 'firebase-admin';
import mysql from 'mysql2/promise';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Parse command line arguments
const args = process.argv.slice(2);
const dryRun = args.includes('--dry-run');
const collectionArg = args.find(arg => arg.startsWith('--collection='));
const specificCollection = collectionArg ? collectionArg.split('=')[1] : null;

// Load configuration
let firebaseConfig;
try {
  firebaseConfig = JSON.parse(readFileSync(join(__dirname, 'firebase-applet-config.json'), 'utf8'));
} catch (error) {
  console.error('Failed to load firebase-applet-config.json:', error.message);
  process.exit(1);
}

// Initialize Firebase Admin
if (admin.apps.length === 0) {
  try {
    admin.initializeApp({
      projectId: firebaseConfig.projectId
    });
    console.log(`[Firebase] Initialized for project: ${firebaseConfig.projectId}`);
  } catch (error) {
    console.error('Failed to initialize Firebase:', error.message);
    process.exit(1);
  }
}

const dbFirestore = admin.firestore();
const dbId = firebaseConfig.firestoreDatabaseId;

// MySQL Configuration
const mysqlConfig = {
  host: process.env.MYSQL_HOST || 'localhost',
  port: parseInt(process.env.MYSQL_PORT || '3306'),
  user: process.env.MYSQL_USER || 'root',
  password: process.env.MYSQL_PASSWORD || '',
  database: process.env.MYSQL_DATABASE || 'connectit_db',
};

let mysqlPool = null;

async function getMySQLConnection() {
  if (!mysqlPool) {
    mysqlPool = mysql.createPool(mysqlConfig);
    console.log(`[MySQL] Connected to ${mysqlConfig.host}:${mysqlConfig.port}/${mysqlConfig.database}`);
  }
  return mysqlPool;
}

// Migration statistics
const stats = {
  users: { attempted: 0, success: 0, failed: 0, errors: [] },
  tickets: { attempted: 0, success: 0, failed: 0, errors: [] },
  comments: { attempted: 0, success: 0, failed: 0, errors: [] },
  approvals: { attempted: 0, success: 0, failed: 0, errors: [] },
  sla_policies: { attempted: 0, success: 0, failed: 0, errors: [] },
  assets: { attempted: 0, success: 0, failed: 0, errors: [] },
  problems: { attempted: 0, success: 0, failed: 0, errors: [] },
  changes: { attempted: 0, success: 0, failed: 0, errors: [] },
  knowledge: { attempted: 0, success: 0, failed: 0, errors: [] },
};

// Helper functions
function formatDate(date) {
  if (!date) return null;
  if (date.toDate) return date.toDate().toISOString().slice(0, 19).replace('T', ' ');
  if (date instanceof Date) return date.toISOString().slice(0, 19).replace('T', ' ');
  if (typeof date === 'string') {
    const d = new Date(date);
    return isNaN(d.getTime()) ? null : d.toISOString().slice(0, 19).replace('T', ' ');
  }
  return null;
}

function generateTicketNumber() {
  const prefix = 'INC';
  const random = Math.floor(1000000 + Math.random() * 9000000);
  return `${prefix}${random}`;
}

// Migration functions
async function migrateUsers() {
  if (specificCollection && specificCollection !== 'users') return;
  
  console.log('\n[Users] Starting migration...');
  const pool = await getMySQLConnection();
  
  const snapshot = await dbFirestore.collection('users').get();
  console.log(`[Users] Found ${snapshot.size} users in Firestore`);
  
  for (const doc of snapshot.docs) {
    stats.users.attempted++;
    const data = doc.data();
    
    try {
      const userData = {
        uid: data.uid || doc.id,
        email: data.email || `${doc.id}@placeholder.com`,
        name: data.name || 'Unknown User',
        role: data.role || 'user',
        phone: data.phone || null,
        is_active: data.disabled !== true,
        is_demo: data.isDemo || false,
        created_at: formatDate(data.createdAt),
        updated_at: formatDate(data.updatedAt),
        last_login: formatDate(data.lastLogin)
      };
      
      if (dryRun) {
        console.log(`[Users] Would create: ${userData.name} (${userData.email})`);
      } else {
        await pool.execute(
          `INSERT INTO users (uid, email, name, role, phone, is_active, is_demo, created_at, updated_at, last_login)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE
           name = VALUES(name), role = VALUES(role), phone = VALUES(phone), 
           is_active = VALUES(is_active), updated_at = VALUES(updated_at)`,
          [userData.uid, userData.email, userData.name, userData.role, userData.phone,
           userData.is_active, userData.is_demo, userData.created_at, userData.updated_at, userData.last_login]
        );
      }
      
      stats.users.success++;
    } catch (error) {
      stats.users.failed++;
      stats.users.errors.push({ id: doc.id, error: error.message });
      console.error(`[Users] Failed to migrate ${doc.id}:`, error.message);
    }
  }
  
  console.log(`[Users] Migration complete: ${stats.users.success}/${stats.users.attempted} successful`);
}

async function migrateTickets() {
  if (specificCollection && specificCollection !== 'tickets') return;
  
  console.log('\n[Tickets] Starting migration...');
  const pool = await getMySQLConnection();
  
  const snapshot = await dbFirestore.collection('tickets').get();
  console.log(`[Tickets] Found ${snapshot.size} tickets in Firestore`);
  
  for (const doc of snapshot.docs) {
    stats.tickets.attempted++;
    const data = doc.data();
    
    try {
      const ticketData = {
        ticket_number: data.number || generateTicketNumber(),
        caller: data.caller || 'Unknown',
        caller_user_id: data.callerUserId || null,
        affected_user: data.affectedUser || null,
        affected_user_id: data.affectedUserId || null,
        category: data.category || 'Inquiry / Help',
        subcategory: data.subcategory || null,
        service: data.service || null,
        service_offering: data.serviceOffering || null,
        cmdb_item: data.cmdbItem || null,
        title: data.title || 'Untitled Ticket',
        description: data.description || null,
        channel: data.channel || 'Self-service',
        status: data.status || 'New',
        impact: data.impact || '3 - Low',
        urgency: data.urgency || '3 - Low',
        priority: data.priority || '4 - Low',
        assignment_group: data.assignmentGroup || null,
        assigned_to: data.assignedTo || null,
        assigned_to_name: data.assignedToName || null,
        created_by: data.createdBy || data.caller || 'System',
        created_by_name: data.createdByName || data.caller || 'System',
        created_at: formatDate(data.createdAt),
        updated_at: formatDate(data.updatedAt),
        first_response_at: formatDate(data.firstResponseAt),
        resolved_at: formatDate(data.resolvedAt),
        closed_at: formatDate(data.closedAt),
        response_deadline: formatDate(data.responseDeadline),
        resolution_deadline: formatDate(data.resolutionDeadline),
        on_hold_start: formatDate(data.onHoldStart),
        on_hold_reason: data.onHoldReason || null,
        total_paused_time_ms: data.totalPausedTime || 0,
        response_sla_status: data.responseSlaStatus || 'In Progress',
        resolution_sla_status: data.resolutionSlaStatus || 'In Progress',
        points: data.points || 0,
        approval_status: data.approvalStatus || 'Not Required'
      };
      
      if (dryRun) {
        console.log(`[Tickets] Would create: ${ticketData.ticket_number} - ${ticketData.title}`);
      } else {
        const [result] = await pool.execute(
          `INSERT INTO tickets (
            ticket_number, caller, caller_user_id, affected_user, affected_user_id,
            category, subcategory, service, service_offering, cmdb_item, title, description,
            channel, status, impact, urgency, priority, assignment_group, assigned_to, assigned_to_name,
            created_by, created_by_name, created_at, updated_at, first_response_at, resolved_at, closed_at,
            response_deadline, resolution_deadline, on_hold_start, on_hold_reason, total_paused_time_ms,
            response_sla_status, resolution_sla_status, points, approval_status
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
          title = VALUES(title), description = VALUES(description), status = VALUES(status),
          priority = VALUES(priority), assigned_to = VALUES(assigned_to), updated_at = VALUES(updated_at)`,
          Object.values(ticketData)
        );
        
        const ticketId = result.insertId;
        
        // Migrate history
        if (data.history && Array.isArray(data.history)) {
          for (const historyEntry of data.history) {
            await pool.execute(
              `INSERT INTO ticket_history (ticket_id, action, user, timestamp, details)
               VALUES (?, ?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE details = VALUES(details)`,
              [ticketId, historyEntry.action, historyEntry.user || 'Unknown', 
               formatDate(historyEntry.timestamp) || new Date(), JSON.stringify(historyEntry)]
            );
          }
        }
      }
      
      stats.tickets.success++;
    } catch (error) {
      stats.tickets.failed++;
      stats.tickets.errors.push({ id: doc.id, error: error.message });
      console.error(`[Tickets] Failed to migrate ${doc.id}:`, error.message);
    }
  }
  
  console.log(`[Tickets] Migration complete: ${stats.tickets.success}/${stats.tickets.attempted} successful`);
}

async function migrateComments() {
  if (specificCollection && specificCollection !== 'comments') return;
  
  console.log('\n[Comments] Starting migration...');
  const pool = await getMySQLConnection();
  
  // Comments are subcollections in Firestore, need to get them per ticket
  const ticketsSnapshot = await dbFirestore.collection('tickets').get();
  let totalComments = 0;
  
  for (const ticketDoc of ticketsSnapshot.docs) {
    const commentsSnapshot = await ticketDoc.ref.collection('comments').get();
    totalComments += commentsSnapshot.size;
    
    for (const doc of commentsSnapshot.docs) {
      stats.comments.attempted++;
      const data = doc.data();
      
      try {
        // Get the ticket ID from MySQL
        const [tickets] = await pool.execute(
          'SELECT id FROM tickets WHERE ticket_number = ?',
          [ticketDoc.data().number]
        );
        
        if (tickets.length === 0) {
          console.warn(`[Comments] Ticket not found for comment ${doc.id}`);
          continue;
        }
        
        const ticketId = tickets[0].id;
        
        if (dryRun) {
          console.log(`[Comments] Would create comment for ticket ${ticketDoc.data().number}`);
        } else {
          await pool.execute(
            `INSERT INTO comments (ticket_id, user_id, user_name, message, is_internal, created_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE message = VALUES(message)`,
            [ticketId, data.userId || null, data.userName || 'Unknown', 
             data.message || '', data.isInternal ? 1 : 0, formatDate(data.createdAt)]
          );
        }
        
        stats.comments.success++;
      } catch (error) {
        stats.comments.failed++;
        stats.comments.errors.push({ id: doc.id, error: error.message });
        console.error(`[Comments] Failed to migrate ${doc.id}:`, error.message);
      }
    }
  }
  
  console.log(`[Comments] Migration complete: ${stats.comments.success}/${stats.comments.attempted} successful (${totalComments} total found)`);
}

async function migrateAssets() {
  if (specificCollection && specificCollection !== 'assets') return;
  
  console.log('\n[Assets] Starting migration...');
  const pool = await getMySQLConnection();
  
  const snapshot = await dbFirestore.collection('assets').get();
  console.log(`[Assets] Found ${snapshot.size} assets in Firestore`);
  
  for (const doc of snapshot.docs) {
    stats.assets.attempted++;
    const data = doc.data();
    
    try {
      const assetData = {
        name: data.name || 'Unnamed Asset',
        type: data.type || 'Hardware',
        status: data.status || 'Operational',
        owner: data.owner || null,
        owner_name: data.ownerName || null,
        location: data.location || null,
        serial_number: data.serialNumber || null,
        model: data.model || null,
        manufacturer: data.manufacturer || null,
        purchase_date: data.purchaseDate || null,
        warranty_expiry: data.warrantyExpiry || null,
        ip_address: data.ipAddress || null,
        description: data.description || null,
        created_at: formatDate(data.createdAt),
        updated_at: formatDate(data.updatedAt)
      };
      
      if (dryRun) {
        console.log(`[Assets] Would create: ${assetData.name}`);
      } else {
        await pool.execute(
          `INSERT INTO assets (name, type, status, owner, owner_name, location, serial_number, model, manufacturer, purchase_date, warranty_expiry, ip_address, description, created_at, updated_at)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status), updated_at = VALUES(updated_at)`,
          Object.values(assetData)
        );
      }
      
      stats.assets.success++;
    } catch (error) {
      stats.assets.failed++;
      stats.assets.errors.push({ id: doc.id, error: error.message });
      console.error(`[Assets] Failed to migrate ${doc.id}:`, error.message);
    }
  }
  
  console.log(`[Assets] Migration complete: ${stats.assets.success}/${stats.assets.attempted} successful`);
}

async function migrateKnowledge() {
  if (specificCollection && specificCollection !== 'knowledge') return;
  
  console.log('\n[Knowledge] Starting migration...');
  const pool = await getMySQLConnection();
  
  const snapshot = await dbFirestore.collection('knowledge').get();
  console.log(`[Knowledge] Found ${snapshot.size} articles in Firestore`);
  
  for (const doc of snapshot.docs) {
    stats.knowledge.attempted++;
    const data = doc.data();
    
    try {
      const articleData = {
        article_number: data.articleNumber || `KB${Math.floor(1000 + Math.random() * 9000)}`,
        title: data.title || 'Untitled Article',
        category: data.category || 'General',
        subcategory: data.subcategory || null,
        content: data.content || '',
        summary: data.summary || null,
        tags: data.tags ? (Array.isArray(data.tags) ? data.tags.join(',') : data.tags) : null,
        views: data.views || 0,
        rating: data.rating || 0,
        rating_count: data.ratingCount || 0,
        helpful_count: data.helpfulCount || 0,
        not_helpful_count: data.notHelpfulCount || 0,
        author: data.author || 'Unknown',
        author_name: data.authorName || data.author || 'Unknown',
        reviewer: data.reviewer || null,
        reviewer_name: data.reviewerName || null,
        status: data.status || 'Draft',
        visibility: data.visibility || 'Internal',
        version: data.version || 1,
        created_at: formatDate(data.createdAt),
        updated_at: formatDate(data.updatedAt),
        published_at: formatDate(data.publishedAt),
        archived_at: formatDate(data.archivedAt)
      };
      
      if (dryRun) {
        console.log(`[Knowledge] Would create: ${articleData.title}`);
      } else {
        await pool.execute(
          `INSERT INTO knowledge_articles (
            article_number, title, category, subcategory, content, summary, tags, views, rating, rating_count,
            helpful_count, not_helpful_count, author, author_name, reviewer, reviewer_name, status, visibility,
            version, created_at, updated_at, published_at, archived_at
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), views = VALUES(views), updated_at = VALUES(updated_at)`,
          Object.values(articleData)
        );
      }
      
      stats.knowledge.success++;
    } catch (error) {
      stats.knowledge.failed++;
      stats.knowledge.errors.push({ id: doc.id, error: error.message });
      console.error(`[Knowledge] Failed to migrate ${doc.id}:`, error.message);
    }
  }
  
  console.log(`[Knowledge] Migration complete: ${stats.knowledge.success}/${stats.knowledge.attempted} successful`);
}

// Main migration function
async function runMigration() {
  console.log('='.repeat(60));
  console.log('Firebase to MySQL Data Migration');
  console.log('='.repeat(60));
  console.log(`Mode: ${dryRun ? 'DRY RUN (no changes will be made)' : 'LIVE MIGRATION'}`);
  if (specificCollection) {
    console.log(`Collection filter: ${specificCollection}`);
  }
  console.log('');
  
  try {
    // Test connections
    console.log('Testing connections...');
    await getMySQLConnection();
    await dbFirestore.listCollections();
    console.log('✓ Connections successful\n');
    
    // Run migrations
    await migrateUsers();
    await migrateTickets();
    await migrateComments();
    await migrateAssets();
    await migrateKnowledge();
    
    // Print summary
    console.log('\n' + '='.repeat(60));
    console.log('Migration Summary');
    console.log('='.repeat(60));
    
    for (const [collection, stat] of Object.entries(stats)) {
      if (stat.attempted > 0) {
        console.log(`${collection}:`);
        console.log(`  Attempted: ${stat.attempted}`);
        console.log(`  Success: ${stat.success}`);
        console.log(`  Failed: ${stat.failed}`);
        if (stat.failed > 0 && stat.errors.length > 0) {
          console.log(`  First error: ${stat.errors[0].error}`);
        }
      }
    }
    
    const totalAttempted = Object.values(stats).reduce((sum, s) => sum + s.attempted, 0);
    const totalSuccess = Object.values(stats).reduce((sum, s) => sum + s.success, 0);
    const totalFailed = Object.values(stats).reduce((sum, s) => sum + s.failed, 0);
    
    console.log('\n' + '-'.repeat(60));
    console.log(`TOTAL: ${totalSuccess}/${totalAttempted} successful (${totalFailed} failed)`);
    console.log('='.repeat(60));
    
    if (mysqlPool) {
      await mysqlPool.end();
    }
    
    process.exit(totalFailed > 0 ? 1 : 0);
    
  } catch (error) {
    console.error('\nMigration failed:', error.message);
    if (mysqlPool) {
      await mysqlPool.end();
    }
    process.exit(1);
  }
}

// Run the migration
runMigration();
