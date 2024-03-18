/**
 * This is the required configuration for any interaction with Firebase
 * It's provided within the Firebase Console once you've set up a a project
 * See https://firebase.google.com/docs/web/setup#add-sdk-and-initialize
 */
export const firebaseConfig = {
    apiKey: "AIzaSyB8JJPC5E1jtAJJ9X6WQgLxfyowHYMDX-0",
    authDomain: "quickstartjsmessaging.firebaseapp.com",
    projectId: "quickstartjsmessaging",
    storageBucket: "quickstartjsmessaging.appspot.com",
    messagingSenderId: "886544748781",
    appId: "1:886544748781:web:4afe61c9c430d769977ce4",
    measurementId: "G-C2PD04L4N3",
};

/**
 * This 'vapid key' is actually found in the Firebase Console under the 'Cloud Messaging' tab,
 * under 'Web Push Certificates' and then 'Key Pair Details'
 * It's used to connect to Firebase push services
 */
export const vapidKey = "BJdVmfYHH81o3fyEUxRf34vVyYiu2DYH753ncdluKbLfnQKH6PGChmTRzQ-GNKkXc3LD8EwLh11xxwoeyfwF7tw";
