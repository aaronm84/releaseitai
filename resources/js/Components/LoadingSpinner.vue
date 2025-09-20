<template>
  <div
    class="loading-spinner"
    :class="sizeClasses"
    v-if="visible"
  >
    <div class="spinner-container">
      <div class="spinner" :style="spinnerStyle"></div>
      <div v-if="message" class="loading-message" :class="messageClasses">
        {{ message }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  size: {
    type: String,
    default: 'medium', // 'small', 'medium', 'large'
    validator: (value) => ['small', 'medium', 'large'].includes(value)
  },
  message: {
    type: String,
    default: ''
  },
  visible: {
    type: Boolean,
    default: true
  },
  variant: {
    type: String,
    default: 'primary', // 'primary', 'white', 'muted'
    validator: (value) => ['primary', 'white', 'muted'].includes(value)
  }
})

const sizeClasses = computed(() => {
  const sizes = {
    small: 'w-4 h-4',
    medium: 'w-8 h-8',
    large: 'w-12 h-12'
  }
  return sizes[props.size]
})

const messageClasses = computed(() => {
  const sizes = {
    small: 'text-xs mt-1',
    medium: 'text-sm mt-2',
    large: 'text-base mt-3'
  }
  return sizes[props.size]
})

const spinnerStyle = computed(() => {
  const colors = {
    primary: '#884DFF',
    white: '#FFFFFF',
    muted: '#A1A1AA'
  }

  return {
    borderTopColor: colors[props.variant],
    borderRightColor: colors[props.variant]
  }
})
</script>

<style scoped>
.loading-spinner {
  @apply flex items-center justify-center;
}

.spinner-container {
  @apply flex flex-col items-center;
}

.spinner {
  @apply border-2 border-transparent rounded-full animate-spin;
  border-bottom-color: rgba(255, 255, 255, 0.1);
  border-left-color: rgba(255, 255, 255, 0.1);
  animation: spin 1s linear infinite;
}

.loading-message {
  @apply font-medium;
  color: #A1A1AA;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* ADHD-friendly: Reduce motion for users who prefer it */
@media (prefers-reduced-motion: reduce) {
  .spinner {
    animation: pulse 2s ease-in-out infinite;
  }

  @keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
  }
}
</style>