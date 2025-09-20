<template>
  <Transition name="pill-container">
    <div v-if="pills.length > 0" class="pill-row-container">
      <div class="pill-row" :class="responsiveClasses">
        <TransitionGroup
          name="pill-list"
          tag="div"
          class="pill-grid"
          :style="gridStyle"
        >
          <ComponentPill
            v-for="pill in pills"
            :key="pill.id"
            :id="pill.id"
            :name="pill.name"
            :icon="pill.icon"
            :color="pill.color"
            :description="pill.description"
            @restore="handleRestore"
          />
        </TransitionGroup>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { computed, inject, Transition } from 'vue'
import ComponentPill from './ComponentPill.vue'

const props = defineProps({
  pills: {
    type: Array,
    required: true
  },
  screenSize: {
    type: String,
    default: 'desktop',
    validator: (value) => ['mobile', 'tablet', 'desktop'].includes(value)
  }
})

const emit = defineEmits(['restore'])

// Get minimization functions from parent
const restoreComponent = inject('restoreComponent', () => {})

const pillsPerRow = computed(() => {
  const counts = {
    mobile: 3,
    tablet: 5,
    desktop: 6
  }
  return counts[props.screenSize] || 6
})

const responsiveClasses = computed(() => ({
  'pill-row--mobile': props.screenSize === 'mobile',
  'pill-row--tablet': props.screenSize === 'tablet',
  'pill-row--desktop': props.screenSize === 'desktop'
}))

const gridStyle = computed(() => ({
  '--pills-per-row': pillsPerRow.value
}))

const handleRestore = (componentId) => {
  restoreComponent(componentId)
  emit('restore', componentId)
}
</script>

<style scoped>
.pill-row-container {
  margin-bottom: 1.5rem;
}

.pill-row {
  padding: 1rem;
  background: rgba(9, 9, 11, 0.5);
  border: 1px solid #27272A;
  border-radius: 0.75rem;
  backdrop-filter: blur(12px);
}

.pill-grid {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(var(--pills-per-row), 1fr);
}

/* Responsive adjustments */
.pill-row--mobile .pill-grid {
  grid-template-columns: repeat(3, 1fr);
  gap: 0.5rem;
}

.pill-row--tablet .pill-grid {
  grid-template-columns: repeat(5, 1fr);
  gap: 0.625rem;
}

.pill-row--desktop .pill-grid {
  grid-template-columns: repeat(6, 1fr);
  gap: 0.75rem;
}

/* Auto-fit for when there are fewer pills than the max per row */
@media (min-width: 1024px) {
  .pill-grid {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    max-width: 100%;
  }
}

/* Transition animations */
.pill-list-enter-active,
.pill-list-leave-active {
  transition: all 0.3s ease;
}

.pill-list-enter-from {
  opacity: 0;
  transform: translateY(-20px) scale(0.9);
}

.pill-list-leave-to {
  opacity: 0;
  transform: translateY(-10px) scale(0.95);
}

.pill-list-move {
  transition: transform 0.3s ease;
}

/* Container transition animations */
.pill-container-enter-active {
  transition: all 0.4s ease-out;
}

.pill-container-leave-active {
  transition: all 0.4s ease-in;
}

.pill-container-enter-from {
  opacity: 0;
  transform: translateY(-20px);
  max-height: 0;
  margin-bottom: 0;
}

.pill-container-leave-to {
  opacity: 0;
  transform: translateY(-20px);
  max-height: 0;
  margin-bottom: 0;
}

.pill-container-enter-to,
.pill-container-leave-from {
  opacity: 1;
  transform: translateY(0);
  max-height: 200px;
  margin-bottom: 1.5rem;
}

/* Focus management */
.pill-row:focus-within {
  border-color: #884DFF;
  box-shadow: 0 0 0 2px rgba(136, 77, 255, 0.2);
}

/* Empty state (shouldn't show but just in case) */
.pill-grid:empty::after {
  content: "No minimized components";
  color: #6B7280;
  font-style: italic;
  grid-column: 1 / -1;
  text-align: center;
  padding: 1rem;
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
  .pill-list-enter-active,
  .pill-list-leave-active,
  .pill-list-move,
  .pill-container-enter-active,
  .pill-container-leave-active {
    transition: none;
    animation: none;
  }
}
</style>