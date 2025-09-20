import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.baseURL = 'http://localhost:8000';
window.axios.defaults.withCredentials = true;

// Get CSRF token from meta tag and set it in axios defaults
const token = document.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.getAttribute('content');
} else {
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
}

// Initialize session with Laravel backend on app start
// This ensures proper authentication state when using Vite dev server
window.axios.get('/').catch((error) => {
    // Silently handle any initialization errors
    console.debug('Session initialization:', error.response?.status || 'completed');
});
