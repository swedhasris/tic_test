// Script to clear all users from Firestore database
// Run with: node clear-users.js

const { initializeApp } = require('firebase/app');
const { getFirestore, collection, getDocs, deleteDoc, doc } = require('firebase/firestore');

// Firebase configuration - matches your project
const firebaseConfig = {
  apiKey: "AIzaSyD-4u7q1IbN6rEaN1k9ZG3Tt1C6LZG2rZw",
  authDomain: "studio-1364433017-7dd51.firebaseapp.com",
  projectId: "studio-1364433017-7dd51",
  storageBucket: "studio-1364433017-7dd51.firebasestorage.app",
  messagingSenderId: "804084291283",
  appId: "1:804084291283:web:8c88fbbd946b503caf0e62"
};

const app = initializeApp(firebaseConfig);
const db = getFirestore(app);

async function clearAllUsers() {
  try {
    console.log('Fetching all users from Firestore...');
    const usersRef = collection(db, 'users');
    const snapshot = await getDocs(usersRef);
    
    console.log(`Found ${snapshot.docs.length} users to delete`);
    
    let deletedCount = 0;
    for (const userDoc of snapshot.docs) {
      await deleteDoc(doc(db, 'users', userDoc.id));
      console.log(`Deleted user: ${userDoc.id} (${userDoc.data().name || 'Unknown'})`);
      deletedCount++;
    }
    
    console.log(`\n✅ Successfully deleted ${deletedCount} users from Firestore`);
    process.exit(0);
  } catch (error) {
    console.error('❌ Error clearing users:', error);
    process.exit(1);
  }
}

clearAllUsers();
