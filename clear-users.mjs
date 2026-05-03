// Script to clear all users from Firestore database
// Run with: node clear-users.mjs

import { initializeApp, cert } from 'firebase-admin/app';
import { getFirestore } from 'firebase-admin/firestore';
import { readFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Read the firebase config from the JSON file
let firebaseConfig;
try {
  const configFile = readFileSync(join(__dirname, 'firebase-applet-config.json'), 'utf8');
  const config = JSON.parse(configFile);
  firebaseConfig = {
    projectId: config.projectId,
  };
} catch (e) {
  console.error('Error reading firebase config:', e.message);
  console.log('Using default project ID...');
  firebaseConfig = {
    projectId: "studio-1364433017-7dd51"
  };
}

// Initialize Firebase Admin (will use default credentials or service account)
const app = initializeApp({
  projectId: firebaseConfig.projectId
}, 'clear-users-app');

const db = getFirestore(app);

async function clearAllUsers() {
  try {
    console.log('Fetching all users from Firestore...');
    const usersRef = db.collection('users');
    const snapshot = await usersRef.get();
    
    if (snapshot.empty) {
      console.log('✅ No users found in the database.');
      process.exit(0);
    }
    
    console.log(`Found ${snapshot.docs.length} users to delete`);
    
    let deletedCount = 0;
    const batch = db.batch();
    
    for (const userDoc of snapshot.docs) {
      const userData = userDoc.data();
      console.log(`Deleting user: ${userDoc.id} (${userData.name || userData.email || 'Unknown'})`);
      batch.delete(userDoc.ref);
      deletedCount++;
    }
    
    // Commit the batch
    await batch.commit();
    
    console.log(`\n✅ Successfully deleted ${deletedCount} users from Firestore`);
    process.exit(0);
  } catch (error) {
    console.error('❌ Error clearing users:', error.message);
    console.error(error);
    process.exit(1);
  }
}

clearAllUsers();
