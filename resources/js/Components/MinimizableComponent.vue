<template>
  <div v-if="!isMinimized(componentId)"
       class="minimizable-component"
       :class="{ 'component-animating': isAnimating }"
       v-bind="ariaAttributes"
       @keydown="handleKeydown">

    <!-- Minimize button -->
    <button v-if="canBeMinimized(componentId)"
            @click="handleMinimize"
            class="minimize-button"
            :aria-label="`Minimize ${componentName} component`"
            title="Press Escape to minimize">
      <span class="minimize-line"></span>
    </button>

    <!-- Component content -->
    <div class="component-content">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'

const props = defineProps({
  componentId: {
    type: String,
    required: true
  },
  componentName: {
    type: String,
    required: true
  }
})

// Get minimization functions from parent
const minimizeComponent = inject('minimizeComponent', () => {})
const isMinimized = inject('isMinimized', () => false)
const canBeMinimized = inject('canBeMinimized', () => true)
const getComponentAriaAttributes = inject('getComponentAriaAttributes', () => ({}))
const handleKeyboardEvent = inject('handleKeyboardEvent', () => ({ handled: false }))

const isAnimating = ref(false)

const ariaAttributes = computed(() => {
  return getComponentAriaAttributes(props.componentId)
})

const handleMinimize = async () => {
  if (!canBeMinimized(props.componentId)) return

  // Start animation
  isAnimating.value = true

  // Trigger minimize with animation
  try {
    await new Promise(resolve => {
      // Add CSS class for animation
      setTimeout(() => {
        minimizeComponent(props.componentId)
        resolve()
      }, 150) // Half the animation duration
    })
  } finally {
    isAnimating.value = false
  }
}

const handleKeydown = (event) => {
  const result = handleKeyboardEvent(event, props.componentId)
  if (result.handled) {
    event.preventDefault()
  }
}
</script>

<style scoped>
.minimizable-component {
  position: relative;
  transition: all 0.3s ease;
}

.component-animating {
  animation: minimizeOut 0.3s ease-in-out forwards;
}

.minimize-button {
  position: absolute;
  top: 0.75rem;
  right: 0.75rem;
  z-index: 10;
  padding: 0.5rem;
  background: transparent !important;
  border: none !important;
  border-radius: 0.25rem;
  color: #FFFFFF !important;
  opacity: 0.7;
  transition: all 0.2s ease;
  cursor: pointer;
}

.minimizable-component:hover .minimize-button {
  opacity: 1;
}

.minimize-button:hover {
  background: rgba(255, 255, 255, 0.1) !important;
  color: #FFFFFF !important;
  opacity: 1;
}

.minimize-button:focus {
  opacity: 1;
  outline: 2px solid #884DFF;
  outline-offset: 2px;
}

.minimize-line {
  display: block;
  width: 16px;
  height: 2px;
  background: #FFFFFF;
  border-radius: 1px;
}



/* Minimize animation */
@keyframes minimizeOut {
  0% {
    opacity: 1;
    transform: scale(1) translateY(0);
    max-height: 1000px;
  }
  50% {
    opacity: 0.5;
    transform: scale(0.95) translateY(-10px);
  }
  100% {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
    max-height: 0;
    margin: 0;
    padding: 0;
  }
}

/* Focus styles for the container */
.minimizable-component:focus-within {
  outline: 2px solid #884DFF;
  outline-offset: 2px;
  border-radius: 0.75rem;
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
  .minimizable-component,
  .minimize-button,
  .component-animating {
    transition: none;
    animation: none;
  }
}

/* Mobile adjustments */
@media (max-width: 768px) {
  .minimize-button {
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.375rem;
    opacity: 1; /* Always visible on mobile */
  }
}
</style>