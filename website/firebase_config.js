import { initializeApp } from "https://www.gstatic.com/firebasejs/10.10.0/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/10.10.0/firebase-auth.js";

// Your web app's Firebase configuration
export const firebaseConfig = {
  apiKey: "AIzaSyCZ1ipCZ4zq4SrhZJhNfQ90pmutNeX63w0",
  authDomain: "sfr-test-website-260313.firebaseapp.com",
  projectId: "sfr-test-website-260313",
  storageBucket: "sfr-test-website-260313.firebasestorage.app",
  messagingSenderId: "46070605300",
  appId: "1:46070605300:web:50c2b169522c50ee2e05a1"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
export const auth = getAuth(app);
