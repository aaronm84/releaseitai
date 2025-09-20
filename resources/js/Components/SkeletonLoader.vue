<template>
  <div class="skeleton-loader" :class="containerClasses">
    <div class="skeleton-content">
      <!-- Header skeleton -->
      <div v-if="showHeader" class="skeleton-header mb-6">
        <div class="skeleton-line h-8 w-3/4 mb-2"></div>
        <div class="skeleton-line h-4 w-1/2"></div>
      </div>

      <!-- Cards skeleton based on type -->
      <div v-if="type === 'cards'" class="grid gap-6" :class="gridClasses">
        <div
          v-for="n in count"
          :key="n"
          class="skeleton-card"
        >
          <div class="skeleton-line h-4 w-3/4 mb-3"></div>
          <div class="skeleton-line h-3 w-full mb-2"></div>
          <div class="skeleton-line h-3 w-5/6"></div>
        </div>
      </div>

      <!-- Dashboard skeleton -->
      <div v-if="type === 'dashboard'" class="space-y-6">
        <!-- Morning Brief skeleton -->
        <div class="skeleton-card">
          <div class="flex items-center mb-4">
            <div class="skeleton-line h-6 w-6 rounded-full mr-3"></div>
            <div class="skeleton-line h-5 w-48"></div>
          </div>
          <div class="skeleton-line h-4 w-full mb-2"></div>
          <div class="skeleton-line h-4 w-3/4"></div>
        </div>

        <!-- Metrics skeleton -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div v-for="n in 3" :key="n" class="skeleton-card">
            <div class="skeleton-line h-12 w-16 mb-2"></div>
            <div class="skeleton-line h-4 w-24"></div>
          </div>
        </div>

        <!-- Priority items skeleton -->
        <div class="space-y-3">
          <div v-for="n in 3" :key="n" class="skeleton-card p-4">
            <div class="flex items-center">
              <div class="skeleton-line h-3 w-3 rounded-full mr-4"></div>
              <div class="flex-1">
                <div class="skeleton-line h-4 w-2/3 mb-1"></div>
                <div class="skeleton-line h-3 w-1/2"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Table skeleton -->
      <div v-if="type === 'table'" class="space-y-4">
        <div class="skeleton-line h-10 w-full"></div>
        <div v-for="n in count" :key="n" class="skeleton-line h-12 w-full"></div>
      </div>

      <!-- List skeleton -->
      <div v-if="type === 'list'" class="space-y-3">
        <div v-for="n in count" :key="n" class="flex items-center space-x-3">
          <div class="skeleton-line h-10 w-10 rounded-full"></div>
          <div class="flex-1">
            <div class="skeleton-line h-4 w-3/4 mb-1"></div>
            <div class="skeleton-line h-3 w-1/2"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'cards', // 'cards', 'dashboard', 'table', 'list'
    validator: (value) => ['cards', 'dashboard', 'table', 'list'].includes(value)
  },
  count: {
    type: Number,
    default: 3
  },
  showHeader: {
    type: Boolean,
    default: true
  },
  columns: {
    type: Number,
    default: 3
  }
})

const containerClasses = computed(() => {
  return 'p-6 rounded-xl'
})

const gridClasses = computed(() => {
  const cols = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
  }
  return cols[props.columns] || cols[3]
})
</script>

<style scoped>
.skeleton-loader {
  background: rgba(9, 9, 11, 0.8);
  border: 1px solid #27272A;
}

.skeleton-line {
  background: linear-gradient(
    90deg,
    rgba(39, 39, 42, 0.5) 25%,
    rgba(82, 82, 91, 0.3) 50%,
    rgba(39, 39, 42, 0.5) 75%
  );
  background-size: 200% 100%;
  border-radius: 4px;
  animation: shimmer 1.5s infinite;
}

.skeleton-card {
  background: rgba(9, 9, 11, 0.6);
  border: 1px solid #27272A;
  border-radius: 12px;
  padding: 1.5rem;
}

@keyframes shimmer {
  0% {
    background-position: -200% 0;
  }
  100% {
    background-position: 200% 0;
  }
}

/* ADHD-friendly: Reduce motion for users who prefer it */
@media (prefers-reduced-motion: reduce) {
  .skeleton-line {
    animation: none;
    background: rgba(39, 39, 42, 0.5);
  }
}
</style>