import { initializeApp } from 'firebase/app';
import {
    getAuth,
    signInWithEmailAndPassword,
    createUserWithEmailAndPassword,
    signInWithEmailLink,
    sendSignInLinkToEmail,
    sendEmailVerification,
    updateProfile,
    signInWithPopup,
    GoogleAuthProvider,
    GithubAuthProvider,
    signOut,
    onAuthStateChanged,
    isSignInWithEmailLink
} from 'firebase/auth';

// Firebase configuration from environment variables
const firebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY || 'AIzaSyBME-HoQZnN8J1Ua3pOj0ND70aPRmEhcBE',
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || 'releaseit-ai.firebaseapp.com',
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || 'releaseit-ai',
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || 'releaseit-ai.firebasestorage.app',
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || '906597849982',
    appId: import.meta.env.VITE_FIREBASE_APP_ID || '1:906597849982:web:999c7ed18a45313f8f312f',
    measurementId: import.meta.env.VITE_FIREBASE_MEASUREMENT_ID || 'G-06RZMW1MEQ'
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

// Initialize providers
const googleProvider = new GoogleAuthProvider();
const githubProvider = new GithubAuthProvider();

// Configure providers
googleProvider.addScope('email');
googleProvider.addScope('profile');
githubProvider.addScope('user:email');

// Firebase Auth Service
export class FirebaseAuthService {
    constructor() {
        this.auth = auth;
        this.currentUser = null;
        this.token = null;

        // Set up auth state listener
        this.setupAuthListener();
    }

    // Set up authentication state listener
    setupAuthListener() {
        onAuthStateChanged(this.auth, async (user) => {
            this.currentUser = user;

            if (user) {
                // Get and store the ID token
                try {
                    this.token = await user.getIdToken();
                    this.setAuthToken(this.token);

                    // Optional: Send token to Laravel for session creation
                    await this.authenticateWithLaravel(this.token);
                } catch (error) {
                    console.error('Error getting ID token:', error);
                }
            } else {
                this.token = null;
                this.clearAuthToken();
            }
        });
    }

    // Email/Password Authentication
    async signInWithEmail(email, password) {
        try {
            const userCredential = await signInWithEmailAndPassword(this.auth, email, password);
            return { success: true, user: userCredential.user };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    async resendEmailVerification() {
        try {
            if (!this.currentUser) {
                return { success: false, error: 'No user logged in' };
            }

            await sendEmailVerification(this.currentUser);
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    async createAccount(email, password, displayName = null) {
        try {
            const userCredential = await createUserWithEmailAndPassword(this.auth, email, password);

            // Update display name if provided
            if (displayName && userCredential.user) {
                await updateProfile(userCredential.user, { displayName });
            }

            // Send email verification
            await sendEmailVerification(userCredential.user);

            return { success: true, user: userCredential.user, emailVerificationSent: true };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    // Magic Link Authentication
    async sendMagicLink(email) {
        try {
            const actionCodeSettings = {
                url: window.location.origin + '/auth/magic-link-callback',
                handleCodeInApp: true,
            };

            await sendSignInLinkToEmail(this.auth, email, actionCodeSettings);

            // Store email for magic link completion
            localStorage.setItem('emailForSignIn', email);

            return { success: true };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    async completeMagicLink(email = null) {
        try {
            const url = window.location.href;

            if (!isSignInWithEmailLink(this.auth, url)) {
                return { success: false, error: 'Invalid magic link' };
            }

            // Get email from parameter or localStorage
            const emailAddress = email || localStorage.getItem('emailForSignIn');

            if (!emailAddress) {
                return { success: false, error: 'Email address required for magic link authentication' };
            }

            const userCredential = await signInWithEmailLink(this.auth, emailAddress, url);

            // Clear stored email
            localStorage.removeItem('emailForSignIn');

            return { success: true, user: userCredential.user };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    // Social Authentication
    async signInWithGoogle() {
        try {
            const result = await signInWithPopup(this.auth, googleProvider);
            return { success: true, user: result.user };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    async signInWithGithub() {
        try {
            const result = await signInWithPopup(this.auth, githubProvider);
            return { success: true, user: result.user };
        } catch (error) {
            return { success: false, error: error.message, code: error.code };
        }
    }

    // Sign Out
    async signOut() {
        try {
            await signOut(this.auth);
            this.clearAuthToken();

            // Redirect to login or home page
            window.location.href = '/login';

            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    // Token Management
    async getCurrentToken() {
        if (this.currentUser) {
            try {
                return await this.currentUser.getIdToken(true); // Force refresh
            } catch (error) {
                console.error('Error refreshing token:', error);
                return null;
            }
        }
        return null;
    }

    setAuthToken(token) {
        // Set token in localStorage for persistence
        localStorage.setItem('firebase_token', token);

        // Set default Authorization header for axios/fetch requests
        if (window.axios) {
            window.axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }
    }

    clearAuthToken() {
        localStorage.removeItem('firebase_token');

        if (window.axios) {
            delete window.axios.defaults.headers.common['Authorization'];
        }
    }

    // Laravel Integration
    async authenticateWithLaravel(token) {
        try {
            // Send token to Laravel backend for session creation
            const response = await fetch('/api/auth/firebase', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                },
                body: JSON.stringify({ token })
            });

            if (!response.ok) {
                console.warn('Failed to authenticate with Laravel backend');
            }
        } catch (error) {
            console.warn('Error authenticating with Laravel:', error);
        }
    }

    // Utility Methods
    isAuthenticated() {
        return !!this.currentUser;
    }

    getCurrentUser() {
        return this.currentUser;
    }

    getUserEmail() {
        return this.currentUser?.email || null;
    }

    getUserDisplayName() {
        return this.currentUser?.displayName || this.currentUser?.email || null;
    }

    isEmailVerified() {
        return this.currentUser?.emailVerified || false;
    }
}

// Create singleton instance
const firebaseAuth = new FirebaseAuthService();

// Export both the instance and the class
export { firebaseAuth as default, auth, googleProvider, githubProvider };