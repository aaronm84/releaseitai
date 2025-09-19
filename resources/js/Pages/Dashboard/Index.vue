<template>
  <AppLayout>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-dark-primary space-y-6">
      <!-- Header Section -->
      <div class="dark-card rounded-xl p-6 border border-dark-border">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-white to-gray-200 bg-clip-text text-transparent">
              Good morning, {{ user.name }}
            </h1>
            <p class="text-gray-300 mt-2 text-lg">Here's what needs your attention today</p>
          </div>
          <div class="flex items-center space-x-4">
            <button
              @click="navigateToWorkstreams"
              class="purple-gradient-button px-6 py-3 text-white rounded-xl font-medium transition-all duration-300 flex items-center space-x-2"
            >
              <div class="w-5 h-5 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
                <span class="text-sm">🏗️</span>
              </div>
              <span>Manage Workstreams</span>
            </button>
            <div class="dark-status-indicator">
              {{ currentDate }}
            </div>
          </div>
        </div>
      </div>

      <!-- Top 3 Priorities -->
      <div class="dark-card rounded-xl p-6 border border-dark-border">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
          <h2 class="text-xl font-bold text-white">Top 3 Priorities</h2>
          <div class="ml-auto px-4 py-2 bg-gradient-to-r from-purple-500/20 to-purple-600/20 text-purple-300 text-sm font-medium rounded-full border border-purple-500/30">
            Focus Mode
          </div>
        </div>
        <div v-if="topPriorities.length > 0" class="space-y-3">
          <div
            v-for="(priority, index) in topPriorities"
            :key="priority.id"
            class="dark-priority-card flex items-center p-5 rounded-xl transition-all duration-300 border"
            :class="{
              'urgent border-red-500/30 bg-red-500/5': priority.due_in_days <= 1,
              'warning border-yellow-500/30 bg-yellow-500/5': priority.due_in_days <= 3 && priority.due_in_days > 1,
              'border-dark-border bg-dark-secondary/50': priority.due_in_days > 3
            }"
          >
            <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-purple-600 text-white flex items-center justify-center text-sm font-bold shadow-lg">
              {{ index + 1 }}
            </div>
            <div class="ml-5 flex-1">
              <h3 class="font-semibold text-white text-lg">{{ priority.name }}</h3>
              <div class="flex items-center mt-1">
                <div class="w-4 h-4 bg-purple-500/20 rounded-lg flex items-center justify-center mr-2">
                  <span class="text-xs">📁</span>
                </div>
                <p class="text-sm text-gray-300 font-medium">{{ priority.workstream_name }}</p>
              </div>
            </div>
            <div class="text-right">
              <div class="dark-status-indicator mb-2" :class="{
                'dark-status-urgent': priority.due_in_days <= 1,
                'dark-status-warning': priority.due_in_days <= 3 && priority.due_in_days > 1,
                'dark-status-normal': priority.due_in_days > 3
              }">
                {{ priority.due_in_days === 0 ? '🔥 Due today' : priority.due_in_days === 1 ? '⚡ Due tomorrow' : `📅 ${priority.due_in_days} days` }}
              </div>
              <div class="text-xs text-gray-400 font-medium">{{ priority.status }}</div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-12">
          <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-green-400 to-green-500 rounded-full flex items-center justify-center">
            <span class="text-2xl">🎉</span>
          </div>
          <p class="text-gray-300 text-lg font-medium">No urgent priorities right now.</p>
          <p class="text-green-400 font-semibold mt-1">Great job staying on top of things!</p>
        </div>
      </div>

      <!-- Quick Add Brain Dump -->
      <div class="dark-brain-dump-container rounded-xl p-6 border border-dark-border">
        <div class="flex justify-between items-center mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 bg-purple-500/20 rounded-xl flex items-center justify-center">
                <span class="text-lg">🧠</span>
              </div>
              <h2 class="text-xl font-bold text-white">Brain Dump</h2>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-400">Press</span>
            <kbd class="px-3 py-1 bg-dark-secondary rounded-lg text-xs font-semibold shadow-sm border border-dark-border text-gray-300">Ctrl+Enter</kbd>
            <span class="text-sm text-gray-400">to process</span>
          </div>
        </div>
        <div class="space-y-4">
          <textarea
            v-model="quickAddContent"
            @input="handleQuickAddInput"
            @keydown="handleKeyDown"
            :placeholder="quickAddConfig.placeholder"
            class="dark-brain-dump-input w-full h-36 p-5 rounded-xl resize-none text-white placeholder-gray-400 border border-dark-border bg-dark-secondary/50 focus:border-purple-500 focus:bg-dark-secondary transition-all duration-300"
            :class="{ 'border-purple-400 bg-purple-500/5': isProcessing }"
          ></textarea>

          <div v-if="isProcessing" class="flex items-center justify-center p-4 bg-gradient-to-r from-purple-500/10 to-purple-600/10 rounded-xl border border-purple-500/30">
            <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-purple-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <div class="flex items-center space-x-2">
              <div class="w-6 h-6 bg-purple-500/20 rounded-lg flex items-center justify-center">
                <span class="text-sm">🤖</span>
              </div>
              <span class="text-purple-300 font-medium">Processing your input...</span>
            </div>
          </div>

          <div v-if="showSuccess" class="flex items-center justify-center p-4 bg-gradient-to-r from-green-500/10 to-emerald-500/10 rounded-xl border border-green-500/30">
            <div class="w-8 h-8 bg-gradient-to-br from-green-400 to-green-500 rounded-full flex items-center justify-center mr-3">
              <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="text-green-300">
              <div class="flex items-center space-x-2">
                <span class="text-lg">✨</span>
                <p class="font-semibold">Content processed successfully!</p>
              </div>
              <p class="text-sm text-green-400 mt-1">Items extracted below. Clearing in 5 seconds...</p>
            </div>
          </div>

          <div class="flex justify-between items-center">
            <button
              @click="processContent"
              :disabled="quickAddContent.length < 10 || isProcessing"
              class="purple-gradient-button px-6 py-3 text-white rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 font-medium"
            >
              {{ isProcessing ? 'Processing...' : 'Process Content' }}
            </button>

            <div v-if="quickAddContent.length > 0" class="text-sm text-gray-400">
              {{ quickAddContent.length }} characters • Auto-saved
            </div>
          </div>

          <div v-if="extractedItems" class="space-y-3">
            <h3 class="font-medium text-white">Extracted Items:</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-if="extractedItems.tasks?.length" class="p-5 bg-gradient-to-br from-blue-500/10 to-blue-600/10 rounded-xl border border-blue-500/30">
                <h4 class="font-bold text-blue-300 mb-3 flex items-center">
                  <span class="w-2 h-2 bg-blue-400 rounded-full mr-2"></span>
                  <div class="w-5 h-5 bg-blue-500/20 rounded-lg flex items-center justify-center mr-2">
                    <span class="text-xs">📋</span>
                  </div>
                  Tasks ({{ extractedItems.tasks.length }})
                </h4>
                <ul class="space-y-1">
                  <li v-for="task in extractedItems.tasks" :key="task.title" class="text-sm text-blue-200">
                    • {{ task.title }}
                  </li>
                </ul>
              </div>

              <div v-if="extractedItems.meetings?.length" class="p-5 bg-gradient-to-br from-green-500/10 to-green-600/10 rounded-xl border border-green-500/30">
                <h4 class="font-bold text-green-300 mb-3 flex items-center">
                  <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
                  <div class="w-5 h-5 bg-green-500/20 rounded-lg flex items-center justify-center mr-2">
                    <span class="text-xs">🤝</span>
                  </div>
                  Meetings ({{ extractedItems.meetings.length }})
                </h4>
                <ul class="space-y-1">
                  <li v-for="meeting in extractedItems.meetings" :key="meeting.title" class="text-sm text-green-200">
                    • {{ meeting.title }}
                  </li>
                </ul>
              </div>
            </div>

            <div class="flex space-x-3">
              <button
                @click="createReleaseFromItems"
                class="purple-gradient-button px-6 py-3 text-white rounded-xl transition-all duration-300 font-medium"
              >
                Create Release
              </button>
              <button
                @click="addToExistingRelease"
                class="dark-secondary-button px-6 py-3 text-gray-300 rounded-xl border border-dark-border hover:border-purple-500/50 transition-all duration-300 font-medium"
              >
                Add to Existing
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Workstreams Overview -->
      <div v-if="workstreams && workstreams.length > 0" class="dark-card rounded-xl p-6 border border-dark-border">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
            <h2 class="text-xl font-bold text-white">Your Workstreams</h2>
          </div>
          <button
            @click="navigateToWorkstreams"
            class="text-sm text-purple-400 hover:text-purple-300 font-medium transition-colors duration-300"
          >
            View All →
          </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="workstream in workstreams"
            :key="workstream.id"
            class="dark-workstream-card rounded-xl p-6 cursor-pointer border border-dark-border bg-dark-secondary/30 hover:border-purple-500/50 transition-all duration-300"
            @click="navigateToWorkstream(workstream.id)"
          >
            <div class="flex items-center justify-between mb-6">
              <h3 class="font-bold text-white text-lg">{{ workstream.name }}</h3>
              <span class="dark-status-indicator dark-status-normal text-xs">
                {{ workstream.type }}
              </span>
            </div>

            <div class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Active Releases</span>
                <span class="font-medium text-white">{{ workstream.active_releases_count }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-400">Total Releases</span>
                <span class="font-medium text-white">{{ workstream.total_releases_count }}</span>
              </div>
            </div>

            <div class="mt-6">
              <div class="flex justify-between items-center mb-2">
                <span class="text-sm font-medium text-gray-400">Progress</span>
                <span class="text-sm font-bold text-purple-400">{{ workstream.completion_percentage }}%</span>
              </div>
              <div class="dark-progress-container w-full h-3">
                <div
                  class="dark-progress-bar h-3 rounded-full"
                  :style="{ width: `${workstream.completion_percentage}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State for Workstreams -->
      <div v-else class="dark-card rounded-xl p-6 text-center border border-dark-border">
        <div class="py-12">
          <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center">
            <span class="text-2xl">🏗️</span>
          </div>
          <h3 class="text-lg font-semibold text-white mb-2">No Workstreams Yet</h3>
          <p class="text-gray-300 mb-6">Create your first workstream to organize your product releases</p>
          <button
            @click="navigateToWorkstreams"
            class="purple-gradient-button px-8 py-4 text-white rounded-xl font-medium transition-all duration-300 flex items-center space-x-2 mx-auto"
          >
            <div class="w-6 h-6 bg-white bg-opacity-20 rounded-lg flex items-center justify-center">
              <span class="text-sm">🚀</span>
            </div>
            <span>Create Your First Workstream</span>
          </button>
        </div>
      </div>

      <!-- Morning Brief -->
      <div v-if="morningBrief" class="dark-morning-brief rounded-xl p-6 border border-dark-border">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-purple-500/20 rounded-xl flex items-center justify-center">
              <span class="text-lg">🌅</span>
            </div>
            <h2 class="text-xl font-bold text-white">Morning Brief</h2>
          </div>
        </div>
        <div class="prose prose-sm max-w-none">
          <p class="text-gray-300">{{ morningBrief.summary }}</p>
          <div v-if="morningBrief.highlights" class="mt-4">
            <h4 class="font-medium text-white">Key Highlights:</h4>
            <ul class="mt-2">
              <li v-for="highlight in morningBrief.highlights" :key="highlight" class="text-gray-300">
                {{ highlight }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  releases: Array,
  topPriorities: Array,
  workstreams: Array,
  user: Object,
  quickAddConfig: Object,
  morningBrief: Object,
});

const quickAddContent = ref('');
const isProcessing = ref(false);
const extractedItems = ref(null);
const showSuccess = ref(false);

const currentDate = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
});

const handleKeyDown = (event) => {
  // Ctrl+Enter or Cmd+Enter to process
  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
    event.preventDefault();
    processContent();
  }
};

const handleQuickAddInput = async () => {
  // Auto-save but don't auto-process for ADHD users (less distraction)
  autoSave();
};

const processContent = async () => {
  if (quickAddContent.value.length < 10) {
    alert('Please enter at least 10 characters to process.');
    return;
  }

  isProcessing.value = true;

  try {
    // Simulate AI processing for now since we don't have the backend endpoint yet
    await new Promise(resolve => setTimeout(resolve, 1000));

    // Mock extracted items based on content
    const content = quickAddContent.value.toLowerCase();
    const mockItems = {
      tasks: [],
      meetings: [],
      decisions: []
    };

    if (content.includes('meeting') || content.includes('call')) {
      mockItems.meetings.push({ title: 'Follow-up meeting', date: 'TBD' });
    }

    if (content.includes('task') || content.includes('todo') || content.includes('need to')) {
      mockItems.tasks.push({ title: 'Extracted task from brain dump', priority: 'medium' });
    }

    if (content.includes('decide') || content.includes('decision')) {
      mockItems.decisions.push({ item: 'Decision point identified' });
    }

    extractedItems.value = mockItems;
    showSuccess.value = true;

    // Show success message
    console.log('Content processed successfully!', mockItems);

    // Clear after 5 seconds (longer to see results)
    setTimeout(() => {
      console.log('Clearing brain dump...');
      quickAddContent.value = '';
      extractedItems.value = null;
      showSuccess.value = false;
      localStorage.removeItem('braindump_content');
    }, 5000);

  } catch (error) {
    console.error('Error processing quick add:', error);
    alert('Error processing content. Please try again.');
  } finally {
    isProcessing.value = false;
  }
};

const createReleaseFromItems = () => {
  router.post('/quick-add/convert-to-release', {
    content: quickAddContent.value,
    extracted_items: extractedItems.value,
  });
};

const addToExistingRelease = () => {
  // Navigate to release selection
  router.visit('/quick-add/select-release', {
    data: {
      content: quickAddContent.value,
      extracted_items: extractedItems.value,
    }
  });
};

const navigateToWorkstream = (workstreamId) => {
  router.visit(`/workstreams/${workstreamId}`);
};

const navigateToWorkstreams = () => {
  router.visit('/workstreams');
};

// Auto-save functionality for ADHD users
let autoSaveTimeout;
const autoSave = () => {
  clearTimeout(autoSaveTimeout);
  autoSaveTimeout = setTimeout(() => {
    if (quickAddContent.value) {
      localStorage.setItem('braindump_content', quickAddContent.value);
    }
  }, 1000);
};

onMounted(() => {
  // Restore content from localStorage
  const saved = localStorage.getItem('braindump_content');
  if (saved) {
    quickAddContent.value = saved;
  }

  // Set up auto-save
  const textarea = document.querySelector('textarea');
  if (textarea) {
    textarea.addEventListener('input', autoSave);
  }
});
</script>