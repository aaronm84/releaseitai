# Firebase Authentication Quick Reference

**Implementation Date:** September 23, 2025
**Status:** ✅ Production Ready
**Documentation:** See `FIREBASE_AUTHENTICATION_IMPLEMENTATION.md` for full details

---

## 🚀 **Quick Start**

### **Authentication Methods Available**
1. **Email/Password** - Traditional auth with mandatory email verification
2. **Google OAuth** - One-click authentication via Google accounts
3. **GitHub OAuth** - Developer-friendly GitHub authentication
4. **Magic Links** - Passwordless email-based authentication

### **User Flow**
1. User registers/signs in → Firebase authenticates
2. Email verification required (except social logins)
3. Laravel session created → Dashboard access granted

---

## 🔧 **Key Files**

### **Backend**
```
app/Services/FirebaseAuthService.php          # Firebase JWT verification & user sync
app/Http/Middleware/RequireEmailVerification.php  # Email verification enforcement
app/Http/Controllers/Api/AuthController.php   # Firebase auth endpoint
```

### **Frontend**
```
resources/js/firebase.js                      # Firebase service layer
resources/js/Components/Auth/
├── FirebaseLoginForm.vue                     # Multi-method login
├── FirebaseRegisterForm.vue                  # Registration with verification
├── EmailVerificationPrompt.vue               # Verification management
└── MagicLinkCallback.vue                     # Magic link handler
```

### **Routes**
```
/auth/firebase                 # Firebase auth endpoint (web)
/api/auth/firebase            # Firebase auth endpoint (API)
/verify-email                 # Email verification page
/auth/magic-link-callback     # Magic link callback
```

---

## 🔐 **Security Features**

### **Email Verification**
- **Required for**: Email/password registrations
- **Bypassed for**: Google/GitHub OAuth (pre-verified)
- **Enforcement**: `RequireEmailVerification` middleware on all protected routes

### **Route Protection**
```php
// Web routes
Route::middleware(['auth', 'firebase.verified'])

// API routes
Route::middleware(['auth:sanctum', 'firebase.verified'])
```

### **Database Schema**
- **Password field**: Nullable (supports Firebase users without passwords)
- **Firebase UID**: Links Firebase users to Laravel users
- **Email verification**: Tracked via Firebase claims

---

## 📱 **Frontend Components**

### **Login Form Usage**
```blade
{{-- In Blade templates --}}
<firebase-login-form></firebase-login-form>
```

### **Registration Form Usage**
```blade
{{-- In Blade templates --}}
<firebase-register-form></firebase-register-form>
```

### **Verification Prompt Usage**
```blade
{{-- In Blade templates --}}
<email-verification-prompt user-email="{{ $email }}"></email-verification-prompt>
```

---

## ⚙️ **Environment Configuration**

### **Required Variables**
```env
# Firebase Frontend Config
VITE_FIREBASE_API_KEY=your_api_key
VITE_FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com
VITE_FIREBASE_PROJECT_ID=your_project_id
VITE_FIREBASE_STORAGE_BUCKET=your_project.storage.app
VITE_FIREBASE_MESSAGING_SENDER_ID=your_sender_id
VITE_FIREBASE_APP_ID=your_app_id
VITE_FIREBASE_MEASUREMENT_ID=your_measurement_id

# Firebase Backend Config
SERVICES_FIREBASE_PROJECT_ID=your_project_id
```

---

## 🔄 **Common Operations**

### **Check if User is Verified**
```php
// In middleware or controllers
$firebaseAuth = app(FirebaseAuthService::class);
$claims = $firebaseAuth->verifyToken($token);
$isVerified = $firebaseAuth->isEmailVerified($claims);
```

### **Manual User Sync**
```php
// Sync Firebase user to Laravel
$user = $firebaseAuth->getOrCreateUser($claims, $profileData);
```

### **Frontend Auth State**
```javascript
// Check authentication status
if (firebaseAuth.isAuthenticated()) {
    // User is logged in
}

// Check email verification
if (firebaseAuth.isEmailVerified()) {
    // Email is verified
}
```

---

## 🚨 **Troubleshooting**

### **Common Issues**

1. **"Password cannot be null" database error**
   - **Solution**: Run migration to make password nullable
   - **Command**: `php artisan migrate`

2. **Magic link 404 error**
   - **Solution**: Ensure magic link callback route exists
   - **Route**: `/auth/magic-link-callback`

3. **Email verification not working**
   - **Check**: Firebase console has email/password enabled
   - **Check**: Email verification templates configured

4. **Session not persisting**
   - **Solution**: Ensure `Auth::login($user, true)` called in firebase endpoint
   - **Check**: `credentials: 'include'` in fetch requests

### **Debug Commands**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check routes
php artisan route:list | grep firebase

# Clear caches
php artisan config:clear
php artisan cache:clear
```

---

## 📋 **Testing Checklist**

### **Email/Password Flow**
- [ ] Registration creates Firebase account
- [ ] Email verification sent automatically
- [ ] Unverified users blocked from dashboard
- [ ] Verification link works correctly
- [ ] Laravel user created in database

### **Social Authentication**
- [ ] Google OAuth works end-to-end
- [ ] GitHub OAuth works end-to-end
- [ ] Social users bypass email verification
- [ ] User profiles populated correctly

### **Magic Links**
- [ ] Magic link email sent successfully
- [ ] Magic link callback authenticates user
- [ ] Laravel session created properly
- [ ] Dashboard access granted

### **Security**
- [ ] Unverified users cannot access API
- [ ] Protected routes require authentication
- [ ] Firebase tokens validated properly
- [ ] Sessions expire appropriately

---

## 🔗 **Related Documentation**
- `FIREBASE_AUTHENTICATION_IMPLEMENTATION.md` - Complete implementation guide
- `DEVELOPMENT_PROGRESS.md` - Project status and history
- Laravel Sanctum documentation
- Firebase Authentication documentation

---

**Last Updated:** September 23, 2025
**Maintained By:** Development Team