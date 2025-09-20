<template>
  <div v-if="shouldShow && !isDismissed"
       class="dark-end-of-day rounded-xl p-6"
       style="border: 1px solid #27272A;"
       role="region"
       :aria-label="ariaLabels.region">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center">
        <div class="w-1 h-8 bg-gradient-to-b from-green-400 to-green-600 rounded-full mr-4"></div>
        <div class="flex items-center space-x-3">
          <div class="w-8 h-8 bg-green-500/20 rounded-xl flex items-center justify-center">
            <span class="text-lg">🌅</span>
          </div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;"
              :aria-label="ariaLabels.heading">{{ title }}</h2>
        </div>
      </div>
      <button @click="dismiss"
              class="p-2 rounded-lg hover:bg-gray-700/50 transition-colors"
              :aria-label="ariaLabels.dismissButton"
              @keydown.enter="dismiss"
              @keydown.space="dismiss">
        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
      </button>
    </div>

    <div class="space-y-6">
      <!-- Encouraging Message -->
      <div class="p-4 bg-green-500/10 rounded-lg border border-green-500/30">
        <p class="text-green-200 font-medium">{{ encouragingMessage }}</p>
      </div>

      <!-- Completed Tasks -->
      <div v-if="completedTasks.length > 0">
        <h3 class="font-medium text-white flex items-center mb-3">
          <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
          Today's Accomplishments
        </h3>
        <ul class="space-y-2">
          <li v-for="task in completedTasks" :key="task"
              class="flex items-center text-gray-300">
            <span class="w-4 h-4 bg-green-500/20 rounded-full flex items-center justify-center mr-3">
              <span class="w-2 h-2 bg-green-400 rounded-full"></span>
            </span>
            {{ task }}
          </li>
        </ul>
      </div>

      <!-- Meetings Attended -->
      <div v-if="meetingsAttended.length > 0">
        <h3 class="font-medium text-white flex items-center mb-3">
          <span class="w-2 h-2 bg-blue-400 rounded-full mr-2"></span>
          Meetings Attended
        </h3>
        <ul class="space-y-2">
          <li v-for="meeting in meetingsAttended" :key="meeting"
              class="flex items-center text-gray-300">
            <span class="w-4 h-4 bg-blue-500/20 rounded-full flex items-center justify-center mr-3">
              <span class="text-xs">📅</span>
            </span>
            {{ meeting }}
          </li>
        </ul>
      </div>

      <!-- Key Decisions -->
      <div v-if="keyDecisions.length > 0">
        <h3 class="font-medium text-white flex items-center mb-3">
          <span class="w-2 h-2 bg-purple-400 rounded-full mr-2"></span>
          Key Decisions Made
        </h3>
        <ul class="space-y-2">
          <li v-for="decision in keyDecisions" :key="decision"
              class="flex items-center text-gray-300">
            <span class="w-4 h-4 bg-purple-500/20 rounded-full flex items-center justify-center mr-3">
              <span class="text-xs">💡</span>
            </span>
            {{ decision }}
          </li>
        </ul>
      </div>

      <!-- Tomorrow's Priorities -->
      <div v-if="tomorrowPriorities.length > 0">
        <h3 class="font-medium text-white flex items-center mb-3">
          <span class="w-2 h-2 bg-yellow-400 rounded-full mr-2"></span>
          Tomorrow's Focus
        </h3>
        <ul class="space-y-2">
          <li v-for="priority in tomorrowPriorities" :key="priority"
              class="flex items-center text-gray-300">
            <span class="w-4 h-4 bg-yellow-500/20 rounded-full flex items-center justify-center mr-3">
              <span class="text-xs">⭐</span>
            </span>
            {{ priority }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';

const props = defineProps({
  title: {
    type: String,
    default: '🌅 End of Day Wrap-Up'
  },
  completedTasks: {
    type: Array,
    default: () => []
  },
  meetingsAttended: {
    type: Array,
    default: () => []
  },
  keyDecisions: {
    type: Array,
    default: () => []
  },
  tomorrowPriorities: {
    type: Array,
    default: () => []
  },
  encouragingMessage: {
    type: String,
    default: "You've made great progress today! Focus on tomorrow's priorities to maintain momentum."
  }
});

const isDismissed = ref(false);

// Time-based visibility (appears after 3:00 PM)
const shouldShow = computed(() => {
  const now = new Date();
  const hour = now.getHours();
  return hour >= 15; // 3:00 PM and later
});

// ARIA labels for accessibility
const ariaLabels = computed(() => ({
  region: 'End of day summary',
  heading: 'Daily accomplishments and tomorrow\'s priorities',
  dismissButton: 'Dismiss end of day summary'
}));

// Dismiss functionality
const dismiss = () => {
  isDismissed.value = true;
  saveState();
};

// Restore functionality (for external use)
const restore = () => {
  isDismissed.value = false;
  saveState();
};

// State persistence
const saveState = () => {
  localStorage.setItem('endOfDaySummary_dismissed', JSON.stringify(isDismissed.value));
};

const loadState = () => {
  const saved = localStorage.getItem('endOfDaySummary_dismissed');
  if (saved) {
    isDismissed.value = JSON.parse(saved);
  }
};

// Load state on mount
onMounted(() => {
  loadState();
});

// Watch for dismissal changes to announce to screen readers
watch(isDismissed, (newValue) => {
  const announcement = newValue ? 'End of day summary dismissed' : 'End of day summary restored';
  // In a real implementation, this would use a screen reader announcement service
  console.log('Screen reader announcement:', announcement);
});

// Expose methods for testing and external use
defineExpose({
  dismiss,
  restore,
  isDismissed: computed(() => isDismissed.value),
  shouldShow
});
</script>

<style scoped>
.dark-end-of-day {
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(12px);
}
</style>