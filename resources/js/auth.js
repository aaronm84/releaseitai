import './bootstrap';
import { createApp } from 'vue';
import FirebaseLoginForm from './Components/Auth/FirebaseLoginForm.vue';
import FirebaseRegisterForm from './Components/Auth/FirebaseRegisterForm.vue';
import EmailVerificationPrompt from './Components/Auth/EmailVerificationPrompt.vue';
import MagicLinkCallback from './Components/Auth/MagicLinkCallback.vue';

const app = createApp({
  components: {
    'firebase-login-form': FirebaseLoginForm,
    'firebase-register-form': FirebaseRegisterForm,
    'email-verification-prompt': EmailVerificationPrompt,
    'magic-link-callback': MagicLinkCallback
  }
});

app.mount('#app');