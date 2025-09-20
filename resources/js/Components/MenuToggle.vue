<script setup>
import { ref, watch, onMounted } from 'vue'

// v-model support (optional)
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  labelOpen: { type: String, default: 'Open menu' },
  labelClose: { type: String, default: 'Close menu' },
  size: { type: Number, default: 24 }, // px
})
const emit = defineEmits(['update:modelValue', 'toggle'])

const open = ref(props.modelValue)
watch(() => props.modelValue, v => (open.value = v))
watch(open, v => emit('update:modelValue', v))

function toggle() {
  open.value = !open.value
  emit('toggle', open.value)
}
</script>

<template>
  <button
    type="button"
    :aria-label="open ? labelClose : labelOpen"
    :aria-pressed="open ? 'true' : 'false'"
    :aria-expanded="open ? 'true' : 'false'"
    class="menu-toggle-button"
    @click="toggle"
  >
    <!-- Hit target -->
    <span class="sr-only">{{ open ? labelClose : labelOpen }}</span>
    <div class="menu-icon-container" :style="{ width: size + 'px', height: size + 'px' }">
      <!-- line 1 -->
      <span
        class="menu-line menu-line-1"
        :class="open ? 'menu-open' : 'menu-closed'"
      />
      <!-- line 2 -->
      <span
        class="menu-line menu-line-2"
        :class="open ? 'menu-open' : 'menu-closed'"
      />
    </div>
  </button>
</template>

<style scoped>
.menu-toggle-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 12px;
  background: transparent !important;
  border: none !important;
  border-radius: 8px;
  color: #A1A1AA;
  cursor: pointer;
  transition: var(--transition-smooth);
  outline: none !important;
  box-shadow: none !important;
}

.menu-toggle-button:hover {
  color: #FAFAFA;
}

.menu-toggle-button:hover .menu-icon-container {
  filter: drop-shadow(0 0 8px rgba(136, 77, 255, 2.0)) drop-shadow(0 0 16px rgba(136, 77, 255, 1.5)) drop-shadow(0 0 20px rgba(136, 77, 255, 0.75));
}

.menu-toggle-button:focus,
.menu-toggle-button:active,
.menu-toggle-button[aria-pressed="true"] {
  outline: none !important;
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
}

.menu-icon-container {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
}

.menu-line {
  position: absolute;
  left: 50%;
  width: 20px;
  height: 2px;
  background-color: currentColor;
  border-radius: 1px;
  transform-origin: center;
  transition: all 0.2s ease-in-out;
}

.menu-line-1.menu-closed {
  transform: translateX(-50%) translateY(-4px);
}

.menu-line-1.menu-open {
  transform: translateX(-50%) translateY(0) rotate(45deg);
}

.menu-line-2.menu-closed {
  transform: translateX(-50%) translateY(4px);
}

.menu-line-2.menu-open {
  transform: translateX(-50%) translateY(0) rotate(-45deg);
}

/* Screen reader only */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Respect reduced motion for ADHD users */
@media (prefers-reduced-motion: reduce) {
  .menu-line {
    transition: none !important;
  }
  .menu-toggle-button {
    transition: color 0.1s ease-in-out !important;
  }
}
</style>