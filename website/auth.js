import { auth } from './firebase_config.js';
import { 
  createUserWithEmailAndPassword, 
  signInWithEmailAndPassword,
  sendEmailVerification,
  updateProfile,
  GoogleAuthProvider,
  FacebookAuthProvider,
  signInWithPopup,
  signOut
} from "https://www.gstatic.com/firebasejs/10.10.0/firebase-auth.js";

// DOM Elements
const loginBox = document.getElementById('loginBox');
const registerBox = document.getElementById('registerBox');
const showRegister = document.getElementById('showRegister');
const showLogin = document.getElementById('showLogin');

// Forms & Inputs
const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');
const loginMessage = document.getElementById('loginMessage');
const registerMessage = document.getElementById('registerMessage');

// Toggle Forms
showRegister.addEventListener('click', (e) => {
  e.preventDefault();
  loginBox.classList.add('hidden');
  registerBox.classList.remove('hidden');
});

showLogin.addEventListener('click', (e) => {
  e.preventDefault();
  registerBox.classList.add('hidden');
  loginBox.classList.remove('hidden');
});

// Helper for UI messages
function showMsg(element, type, message) {
  element.textContent = message;
  element.className = `message ${type}`;
}

// ----------------------------------------------------
// DATABASE SYNC LOGIC
// ----------------------------------------------------
async function syncDatabase(user, provider) {
  try {
    let firstName = '';
    let lastName = '';
    
    // Try to parse from displayName
    if (user.displayName) {
      let parts = user.displayName.split(' ');
      firstName = parts[0] || '';
      lastName = parts.slice(1).join(' ') || '';
    }

    const response = await fetch('sync_user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        firebase_uid: user.uid,
        first_name: firstName,
        last_name: lastName,
        email: user.email,
        auth_provider: provider,
        is_verified: user.emailVerified
      })
    });

    const data = await response.json();
    if (data.status === 'success') {
      // Optional: Redirect to dashboard after successful sync
      window.location.href = 'dashboard.php';
    } else {
      console.error('Sync failed:', data.message);
      window.LuxeUI.alert('Failed to sync user with local database.', 'Sync Error', 'fa-rotate', '#ef4444');
    }
  } catch (error) {
    console.error('Database Sync Error:', error);
  }
}

// ----------------------------------------------------
// EMAIL/PASSWORD REGISTRATION & LOGIN
// ----------------------------------------------------

// Register
registerForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const firstName = document.getElementById('regFirstName').value.trim();
  const lastName = document.getElementById('regLastName').value.trim();
  const email = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;
  const confirmPassword = document.getElementById('regConfirmPassword').value;
  const termsCheck = document.getElementById('termsCheck').checked;

  if (password !== confirmPassword) {
    return showMsg(registerMessage, 'error', 'Passwords do not match');
  }

  if (!termsCheck) {
    return showMsg(registerMessage, 'error', 'You must agree to terms and conditions');
  }

  try {
    const userCredential = await createUserWithEmailAndPassword(auth, email, password);
    const user = userCredential.user;
    
    // Update profile
    await updateProfile(user, { displayName: `${firstName} ${lastName}` });

    // Send email verification
    await sendEmailVerification(user);
    
    showMsg(registerMessage, 'success', 'Registration successful! Please check your email to verify your account.');
    
    // Auto-sign out to force login after verification
    await signOut(auth);
    
  } catch (error) {
    showMsg(registerMessage, 'error', error.message);
  }
});

// Login
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;

  try {
    const userCredential = await signInWithEmailAndPassword(auth, email, password);
    const user = userCredential.user;

    if (!user.emailVerified) {
      await signOut(auth);
      return showMsg(loginMessage, 'error', 'Please verify your email address before logging in.');
    }

    showMsg(loginMessage, 'success', 'Login successful! Syncing...');
    await syncDatabase(user, 'Email');
    
  } catch (error) {
    showMsg(loginMessage, 'error', error.message);
  }
});

// ----------------------------------------------------
// SOCIAL LOGINS (Google & Facebook)
// ----------------------------------------------------

const googleProvider = new GoogleAuthProvider();
const facebookProvider = new FacebookAuthProvider();

async function handleSocialLogin(providerInstance, providerName) {
  try {
    const userCredential = await signInWithPopup(auth, providerInstance);
    const user = userCredential.user;
    
    // Social logins implicitly verify emails
    const msgElement = loginBox.classList.contains('hidden') ? registerMessage : loginMessage;
    showMsg(msgElement, 'success', `${providerName} login successful! Syncing...`);
    
    await syncDatabase(user, providerName);
    
  } catch (error) {
    const msgElement = loginBox.classList.contains('hidden') ? registerMessage : loginMessage;
    showMsg(msgElement, 'error', error.message);
  }
}

// Google Buttons
document.getElementById('loginGoogleBtn').addEventListener('click', () => handleSocialLogin(googleProvider, 'Google'));
document.getElementById('registerGoogleBtn').addEventListener('click', () => handleSocialLogin(googleProvider, 'Google'));

// Facebook Buttons
document.getElementById('loginFacebookBtn').addEventListener('click', () => handleSocialLogin(facebookProvider, 'Facebook'));
document.getElementById('registerFacebookBtn').addEventListener('click', () => handleSocialLogin(facebookProvider, 'Facebook'));
