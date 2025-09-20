<template>
  <button
    @click="handleClick"
    @keydown="handleKeydown"
    class="component-pill"
    :style="pillStyle"
    v-bind="ariaAttributes"
  >
    <span class="pill-icon">{{ icon }}</span>
    <span class="pill-name">{{ name }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  id: {
    type: String,
    required: true
  },
  name: {
    type: String,
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  color: {
    type: String,
    required: true
  },
  description: {
    type: String,
    required: true
  }
})

const emit = defineEmits(['restore'])

const pillStyle = computed(() => ({
  '--pill-color': props.color,
  backgroundColor: `${props.color}20`,
  borderColor: `${props.color}50`,
  color: props.color
}))

const ariaAttributes = computed(() => ({
  'aria-label': props.description,
  'role': 'button',
  'tabindex': '0'
}))

const handleClick = () => {
  emit('restore', props.id)
}

const handleKeydown = (event) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    emit('restore', props.id)
  }
}
</script>

<style scoped>
.component-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid;
  border-radius: 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  transition: all 0.3s ease;
  cursor: pointer;
  outline: none;
}

.component-pill:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border-color: var(--pill-color);
}

.component-pill:focus {
  outline: 2px solid var(--pill-color);
  outline-offset: 2px;
}

.component-pill:active {
  transform: translateY(0);
}

.pill-icon {
  font-size: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pill-name {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 120px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .component-pill {
    padding: 0.375rem 0.625rem;
    font-size: 0.8125rem;
  }

  .pill-name {
    max-width: 80px;
  }
}

/* Animation for pill appearance */
.component-pill {
  animation: pillSlideIn 0.3s ease-out;
}

@keyframes pillSlideIn {
  from {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>