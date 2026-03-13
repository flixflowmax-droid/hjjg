import { auth } from './firebase_config.js';
import { 
    createUserWithEmailAndPassword, 
    signInWithEmailAndPassword,
    sendEmailVerification,
    GoogleAuthProvider,
    FacebookAuthProvider,
    signInWithPopup
} from "https://www.gstatic.com/firebasejs/11.0.1/firebase-auth.js";

// Utility to display alerts
function showAlert(message, type) {
    const alertBox = document.getElementById('alert-message');
    alertBox.textContent = message;
    alertBox.className = `alert alert-${type}`;
    alertBox.style.display = 'block';
}

// Function to sync user with local PHP/MySQL backend
async function syncUserToBackend(user, authProvider, extraData = {}) {
    const data = {
        firebase_uid: user.uid,
        email: user.email,
        auth_provider: authProvider,
        is_verified: user.emailVerified,
        first_name: extraData.firstName || '',
        last_name: extraData.lastName || ''
    };

    try {
        const response = await fetch('sync_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        console.log("Backend Sync Result:", result);
        if(result.status === 'success') {
            showAlert("Successfully authenticated and synced!", "success");
            // Optional: Redirect to a dashboard after successful sync
            // setTimeout(() => { window.location.href = "dashboard.php"; }, 1500);
        } else {
            showAlert("Warning: Logged in to Firebase but backend sync failed.", "error");
        }
    } catch (error) {
        console.error("Backend Sync Error:", error);
        showAlert("Warning: Could not sync with local database.", "error");
    }
}

// Email Registration
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const firstName = document.getElementById('reg-fname').value;
    const lastName = document.getElementById('reg-lname').value;
    const email = document.getElementById('reg-email').value;
    const password = document.getElementById('reg-password').value;
    const confirmPassword = document.getElementById('reg-confirm').value;
    const terms = document.getElementById('reg-terms').checked;
    
    if (!terms) {
        showAlert("You must agree to the Terms and Conditions.", "error");
        return;
    }

    if (password !== confirmPassword) {
        showAlert("Passwords do not match!", "error");
        return;
    }

    try {
        const userCredential = await createUserWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;
        
        // Prevent immediate login without verification by keeping the UI state or logging them out optionally
        // Sending Email Verification
        await sendEmailVerification(user);
        
        document.getElementById('email-verification-prompt').style.display = 'block';
        showAlert("Registration successful! Please check your email to verify.", "success");
        
        // Sync basic unverified profile to backend
        await syncUserToBackend(user, 'Email', { firstName, lastName });
        
        // Optional: sign out until they verify
        // await auth.signOut();
        
    } catch (error) {
        console.error(error);
        showAlert(error.message, "error");
    }
});

// Email Login
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    try {
        const userCredential = await signInWithEmailAndPassword(auth, email, password);
        const user = userCredential.user;

        if (!user.emailVerified) {
            showAlert("Please verify your email address before logging in.", "error");
            document.getElementById('email-verification-prompt').style.display = 'block';
            // Optional: auth.signOut();
            return;
        }

        await syncUserToBackend(user, 'Email');
    } catch (error) {
        console.error(error);
        showAlert(error.message, "error");
    }
});

// Google Login
document.getElementById('btn-google').addEventListener('click', async () => {
    const provider = new GoogleAuthProvider();
    // provider.addScope('email');
    
    try {
        const result = await signInWithPopup(auth, provider);
        const user = result.user;
        
        const names = user.displayName ? user.displayName.split(' ') : ['', ''];
        
        await syncUserToBackend(user, 'Google', { 
            firstName: names[0], 
            lastName: names.length > 1 ? names.slice(1).join(' ') : '' 
        });
    } catch (error) {
        console.error(error);
        showAlert(error.message, "error");
    }
});

// Facebook Login
document.getElementById('btn-facebook').addEventListener('click', async () => {
    const provider = new FacebookAuthProvider();
    // provider.addScope('email');

    try {
        const result = await signInWithPopup(auth, provider);
        const user = result.user;
        
        const names = user.displayName ? user.displayName.split(' ') : ['', ''];
        
        await syncUserToBackend(user, 'Facebook', { 
            firstName: names[0], 
            lastName: names.length > 1 ? names.slice(1).join(' ') : '' 
        });
    } catch (error) {
        console.error(error);
        showAlert(error.message, "error");
    }
});
