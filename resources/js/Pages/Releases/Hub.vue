<template>
  <AppLayout>
    <Head :title="`${release.name} - Release Hub`" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
      <!-- Header Section -->
      <div class="dashboard-card p-8">
        <div class="flex justify-between items-start mb-6">
          <div class="flex-1">
            <div class="flex items-center mb-3">
              <div class="w-3 h-3 rounded-full mr-3" :class="{
                'bg-green-400': release.status === 'completed',
                'bg-yellow-400': release.status === 'in_progress',
                'bg-blue-400': release.status === 'planned',
                'bg-red-400': release.status === 'blocked'
              }"></div>
              <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
                {{ release.name }}
              </h1>
              <div class="ml-4 px-3 py-1 rounded-full text-xs font-medium" :style="{
                background: release.status === 'completed' ? 'rgba(34, 197, 94, 0.2)' : release.status === 'in_progress' ? 'rgba(245, 158, 11, 0.2)' : release.status === 'planned' ? 'rgba(59, 130, 246, 0.2)' : 'rgba(239, 68, 68, 0.2)',
                color: release.status === 'completed' ? '#22C55E' : release.status === 'in_progress' ? '#F59E0B' : release.status === 'planned' ? '#3B82F6' : '#EF4444',
                border: release.status === 'completed' ? '1px solid rgba(34, 197, 94, 0.3)' : release.status === 'in_progress' ? '1px solid rgba(245, 158, 11, 0.3)' : release.status === 'planned' ? '1px solid rgba(59, 130, 246, 0.3)' : '1px solid rgba(239, 68, 68, 0.3)'
              }">
                {{ formatStatus(release.status) }}
              </div>
            </div>
            <p class="text-lg mb-4" style="color: #A1A1AA;">{{ release.description || 'No description provided' }}</p>
            <div class="flex items-center space-x-6">
              <div class="flex items-center space-x-2">
                <div class="w-5 h-5 rounded-lg flex items-center justify-center" style="background: rgba(136, 77, 255, 0.2);">
                  <span class="text-xs">📁</span>
                </div>
                <span class="font-medium" style="color: #A1A1AA;">{{ release.workstream?.name || 'No workstream' }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <div class="w-5 h-5 rounded-lg flex items-center justify-center" style="background: rgba(136, 77, 255, 0.2);">
                  <span class="text-xs">📅</span>
                </div>
                <span class="font-medium" style="color: #A1A1AA;">Target: {{ formatDate(release.target_date) }}</span>
              </div>
              <div class="flex items-center space-x-2">
                <div class="w-5 h-5 rounded-lg flex items-center justify-center" style="background: rgba(136, 77, 255, 0.2);">
                  <span class="text-xs">⏱️</span>
                </div>
                <span class="font-medium" style="color: #A1A1AA;">{{ daysUntilTarget }} days remaining</span>
              </div>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Quick Status Update -->
            <select
              v-model="currentStatus"
              @change="updateReleaseStatus"
              class="px-4 py-2 rounded-xl"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; border-radius: 10px; backdrop-filter: blur(12px);"
            >
              <option value="planned">Planned</option>
              <option value="in_progress">In Progress</option>
              <option value="blocked">Blocked</option>
              <option value="completed">Completed</option>
            </select>
            <!-- Quick Actions -->
            <a
              :href="`/releases/${release.id}/stakeholders`"
              class="px-4 py-2 rounded-xl font-medium transition-all duration-300 inline-flex items-center space-x-2"
              style="background: #3B82F6; color: #FAFAFA; border-radius: 10px;"
            >
              <span>👥</span>
              <span>Stakeholders</span>
            </a>
            <button
              @click="openCommunicationModal"
              class="px-4 py-2 rounded-xl font-medium transition-all duration-300"
              style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
            >
              Quick Update
            </button>
          </div>
        </div>

        <!-- Progress Bar -->
        <div class="mb-4">
          <div class="flex justify-between items-center mb-2">
            <span class="text-sm font-medium" style="color: #A1A1AA;">Overall Progress</span>
            <span class="text-sm font-bold" style="color: #884DFF;">{{ progressPercentage }}%</span>
          </div>
          <div class="w-full h-4 rounded-full" style="background: rgba(39, 39, 42, 0.5);">
            <div
              class="h-4 rounded-full transition-all duration-500"
              style="background: #884DFF;"
              :style="{ width: `${progressPercentage}%` }"
            ></div>
          </div>
        </div>
      </div>

      <!-- Metrics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="dashboard-card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium" style="color: #A1A1AA;">Total Tasks</p>
              <p class="text-2xl font-bold" style="color: #FAFAFA;">{{ release.tasks?.length || 0 }}</p>
            </div>
            <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: #3B82F6;">
              <span class="text-xl">📋</span>
            </div>
          </div>
        </div>
        <div class="dashboard-card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm font-medium">Completed</p>
              <p class="text-2xl font-bold text-green-400">{{ completedTasksCount }}</p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center">
              <span class="text-xl">✅</span>
            </div>
          </div>
        </div>
        <div class="dashboard-card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm font-medium">Blocked</p>
              <p class="text-2xl font-bold text-red-400">{{ blockedTasksCount }}</p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center">
              <span class="text-xl">🚫</span>
            </div>
          </div>
        </div>
        <div class="dashboard-card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-400 text-sm font-medium">Days Left</p>
              <p class="text-2xl font-bold" :class="{
                'text-red-400': daysUntilTarget <= 3,
                'text-yellow-400': daysUntilTarget <= 7 && daysUntilTarget > 3,
                'text-white': daysUntilTarget > 7
              }">{{ daysUntilTarget }}</p>
            </div>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
              <span class="text-xl">⏰</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Checklist & Tasks -->
        <div class="lg:col-span-2 space-y-8">

          <!-- Dynamic Checklist -->
          <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center">
                <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
                <h2 class="text-xl font-bold text-white">Release Checklist</h2>
                <div class="ml-4 px-3 py-1 bg-gradient-to-r from-purple-500/20 to-purple-600/20 text-purple-300 text-sm font-medium rounded-full border border-purple-500/30">
                  {{ completedChecklistItems }}/{{ checklistTemplate.length }} completed
                </div>
              </div>
              <button
                @click="resetChecklist"
                class="text-gray-400 hover:text-white text-sm font-medium transition-colors"
              >
                Reset All
              </button>
            </div>

            <div class="space-y-4">
              <div
                v-for="(item, index) in checklistTemplate"
                :key="index"
                class="flex items-start p-4 rounded-xl border transition-all duration-300"
                :class="{
                  'border-green-500/30 bg-green-500/5': checklistStates[index],
                  'border-dark-border bg-dark-secondary/30 hover:border-purple-500/30': !checklistStates[index]
                }"
              >
                <button
                  @click="toggleChecklistItem(index)"
                  class="flex-shrink-0 w-6 h-6 rounded-lg border-2 transition-all duration-300 flex items-center justify-center mr-4 mt-0.5"
                  :class="{
                    'border-green-500 bg-green-500': checklistStates[index],
                    'border-gray-500 hover:border-purple-500': !checklistStates[index]
                  }"
                >
                  <svg v-if="checklistStates[index]" class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                  </svg>
                </button>
                <div class="flex-1">
                  <h3 class="font-medium text-white mb-1">{{ item.title }}</h3>
                  <div class="flex items-center space-x-2">
                    <span class="text-xs px-2 py-1 rounded-full border" :class="{
                      'bg-blue-500/10 border-blue-500/30 text-blue-300': item.type === 'development',
                      'bg-yellow-500/10 border-yellow-500/30 text-yellow-300': item.type === 'testing',
                      'bg-green-500/10 border-green-500/30 text-green-300': item.type === 'documentation',
                      'bg-purple-500/10 border-purple-500/30 text-purple-300': item.type === 'stakeholder',
                      'bg-red-500/10 border-red-500/30 text-red-300': item.type === 'deployment'
                    }">
                      {{ item.type }}
                    </span>
                    <span v-if="item.description" class="text-sm text-gray-400">{{ item.description }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Task Management -->
          <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center">
                <div class="w-1 h-8 bg-gradient-to-b from-blue-400 to-blue-600 rounded-full mr-4"></div>
                <h2 class="text-xl font-bold text-white">Tasks</h2>
              </div>
              <div class="flex items-center space-x-3">
                <button
                  v-if="selectedTasks.length > 0"
                  @click="bulkUpdateTasks"
                  class="text-purple-400 hover:text-purple-300 text-sm font-medium transition-colors"
                >
                  Update {{ selectedTasks.length }} tasks
                </button>
                <button
                  @click="addNewTask"
                  class="purple-gradient-button px-4 py-2 text-white rounded-xl text-sm font-medium transition-all duration-300"
                >
                  Add Task
                </button>
              </div>
            </div>

            <div class="space-y-3">
              <div
                v-for="task in release.tasks"
                :key="task.id"
                class="flex items-center p-4 rounded-xl border border-dark-border bg-dark-secondary/30 hover:border-purple-500/30 transition-all duration-300"
              >
                <input
                  type="checkbox"
                  v-model="selectedTasks"
                  :value="task.id"
                  class="mr-4 w-4 h-4 rounded bg-dark-secondary border-gray-500 text-purple-500"
                  style="box-shadow: 0 0 0 2px #8B5CF6 !important; outline: none !important;"
                  @focus="$event.target.style.boxShadow = '0 0 0 2px #8B5CF6'"
                >
                <div class="flex-1">
                  <div class="flex items-center mb-2">
                    <h3 class="font-medium text-white mr-3">{{ task.title }}</h3>
                    <div class="flex items-center space-x-2">
                      <span class="text-xs px-2 py-1 rounded-full border" :class="{
                        'bg-red-500/10 border-red-500/30 text-red-300': task.priority === 'high',
                        'bg-yellow-500/10 border-yellow-500/30 text-yellow-300': task.priority === 'medium',
                        'bg-green-500/10 border-green-500/30 text-green-300': task.priority === 'low'
                      }">
                        {{ task.priority }} priority
                      </span>
                      <span class="dark-status-indicator" :class="{
                        'dark-status-normal': task.status === 'completed',
                        'dark-status-warning': task.status === 'in_progress',
                        'dark-status-normal': task.status === 'planned',
                        'dark-status-urgent': task.status === 'blocked'
                      }">
                        {{ task.status }}
                      </span>
                    </div>
                  </div>
                  <p v-if="task.description" class="text-sm text-gray-400 mb-2">{{ task.description }}</p>
                  <div class="flex items-center text-xs text-gray-500 space-x-4">
                    <span v-if="task.assignee">Assigned to: {{ task.assignee }}</span>
                    <span v-if="task.due_date">Due: {{ formatDate(task.due_date) }}</span>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <button
                    @click="editTask(task)"
                    class="text-gray-400 hover:text-white transition-colors p-2"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Empty state for tasks -->
              <div v-if="!release.tasks || release.tasks.length === 0" class="text-center py-12">
                <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                  <span class="text-2xl">📋</span>
                </div>
                <p class="text-gray-300 text-lg font-medium">No tasks yet</p>
                <p class="text-gray-400 mt-1">Add your first task to get started</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Communications & Activity -->
        <div class="space-y-8">

          <!-- Blockers & Risks -->
          <div v-if="blockedTasksCount > 0" class="dark-card rounded-xl p-6 border border-red-500/30 bg-red-500/5">
            <div class="flex items-center mb-4">
              <div class="w-1 h-8 bg-gradient-to-b from-red-400 to-red-600 rounded-full mr-4"></div>
              <h3 class="text-lg font-bold text-red-300">Blockers</h3>
              <div class="ml-auto px-3 py-1 bg-gradient-to-r from-red-500/20 to-red-600/20 text-red-300 text-xs font-medium rounded-full border border-red-500/30">
                {{ blockedTasksCount }} blocked
              </div>
            </div>
            <div class="space-y-3">
              <div
                v-for="task in blockedTasks"
                :key="task.id"
                class="p-3 rounded-lg bg-dark-secondary/50 border border-red-500/20"
              >
                <h4 class="font-medium text-white text-sm">{{ task.title }}</h4>
                <p v-if="task.blocker_reason" class="text-xs text-red-300 mt-1">{{ task.blocker_reason }}</p>
                <button
                  @click="resolveBlocker(task)"
                  class="mt-2 text-xs text-red-400 hover:text-red-300 font-medium transition-colors"
                >
                  Mark as resolved →
                </button>
              </div>
            </div>
          </div>

          <!-- Stakeholder Communications -->
          <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-6">
              <div class="flex items-center">
                <div class="w-1 h-8 bg-gradient-to-b from-green-400 to-green-600 rounded-full mr-4"></div>
                <h3 class="text-lg font-bold text-white">Communications</h3>
              </div>
              <button
                @click="openCommunicationModal"
                class="text-purple-400 hover:text-purple-300 text-sm font-medium transition-colors"
              >
                Send Update
              </button>
            </div>

            <div class="space-y-6">
              <div
                v-for="comm in release.communications?.slice(0, 5)"
                :key="comm.id"
                class="p-3 rounded-lg bg-dark-secondary/30 border border-dark-border"
              >
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium text-green-300">{{ comm.type }}</span>
                  <span class="text-xs text-gray-500">{{ formatDate(comm.created_at) }}</span>
                </div>
                <p class="text-sm text-gray-300">{{ comm.message?.substring(0, 100) }}{{ comm.message?.length > 100 ? '...' : '' }}</p>
                <div v-if="comm.recipients" class="mt-2 text-xs text-gray-400">
                  To: {{ comm.recipients.join(', ') }}
                </div>
              </div>

              <!-- Empty state for communications -->
              <div v-if="!release.communications || release.communications.length === 0" class="text-center py-8">
                <div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-green-500 to-green-600 rounded-full flex items-center justify-center">
                  <span class="text-lg">💬</span>
                </div>
                <p class="text-gray-400 text-sm">No communications yet</p>
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div class="dashboard-card p-6">
            <div class="flex items-center mb-6">
              <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
              <h3 class="text-lg font-bold text-white">Recent Activity</h3>
            </div>

            <div class="space-y-6">
              <div
                v-for="activity in recentActivity"
                :key="activity.id"
                class="flex items-start space-x-3"
              >
                <div class="flex-shrink-0 w-8 h-8 rounded-full border-2 border-purple-500/30 bg-purple-500/10 flex items-center justify-center">
                  <span class="text-xs">{{ activity.icon }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-white">{{ activity.description }}</p>
                  <p class="text-xs text-gray-500 mt-1">{{ formatRelativeTime(activity.created_at) }}</p>
                </div>
              </div>

              <!-- Empty state for activity -->
              <div v-if="recentActivity.length === 0" class="text-center py-8">
                <div class="w-12 h-12 mx-auto mb-3 bg-gradient-to-br from-purple-500 to-purple-600 rounded-full flex items-center justify-center">
                  <span class="text-lg">📈</span>
                </div>
                <p class="text-gray-400 text-sm">No recent activity</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Auto-save indicator -->
    <div v-if="showAutoSaveIndicator" class="fixed bottom-4 right-4 px-4 py-2 bg-green-500 text-white text-sm font-medium rounded-lg shadow-lg transition-all duration-300">
      ✓ Auto-saved
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  release: Object,
  checklistTemplate: Array,
  user: Object
});

// Reactive state
const currentStatus = ref(props.release.status);
const checklistStates = ref({});
const selectedTasks = ref([]);
const showAutoSaveIndicator = ref(false);

// Initialize checklist states
onMounted(() => {
  // Initialize checklist from saved state or empty
  const savedChecklist = localStorage.getItem(`checklist_${props.release.id}`);
  if (savedChecklist) {
    checklistStates.value = JSON.parse(savedChecklist);
  } else {
    // Initialize empty checklist
    const initialStates = {};
    props.checklistTemplate.forEach((_, index) => {
      initialStates[index] = false;
    });
    checklistStates.value = initialStates;
  }
});

// Computed properties
const progressPercentage = computed(() => {
  if (!props.release.tasks || props.release.tasks.length === 0) return 0;
  const completed = props.release.tasks.filter(task => task.status === 'completed').length;
  return Math.round((completed / props.release.tasks.length) * 100);
});

const completedTasksCount = computed(() => {
  return props.release.tasks?.filter(task => task.status === 'completed').length || 0;
});

const blockedTasksCount = computed(() => {
  return props.release.tasks?.filter(task => task.status === 'blocked').length || 0;
});

const blockedTasks = computed(() => {
  return props.release.tasks?.filter(task => task.status === 'blocked') || [];
});

const completedChecklistItems = computed(() => {
  return Object.values(checklistStates.value).filter(Boolean).length;
});

const daysUntilTarget = computed(() => {
  if (!props.release.target_date) return 0;
  const target = new Date(props.release.target_date);
  const today = new Date();
  const diffTime = target - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return Math.max(0, diffDays);
});

const recentActivity = computed(() => {
  // Mock recent activity - in real app this would come from backend
  return [
    { id: 1, icon: '✅', description: 'Task "Setup CI/CD pipeline" completed', created_at: new Date(Date.now() - 2 * 60 * 60 * 1000) },
    { id: 2, icon: '📝', description: 'Checklist item "Code review" marked complete', created_at: new Date(Date.now() - 4 * 60 * 60 * 1000) },
    { id: 3, icon: '💬', description: 'Communication sent to stakeholders', created_at: new Date(Date.now() - 6 * 60 * 60 * 1000) },
    { id: 4, icon: '🔄', description: 'Status updated to In Progress', created_at: new Date(Date.now() - 12 * 60 * 60 * 1000) }
  ];
});

// Auto-save functionality with 500ms delay for ADHD users
let autoSaveTimeout;
const autoSave = () => {
  clearTimeout(autoSaveTimeout);
  autoSaveTimeout = setTimeout(() => {
    localStorage.setItem(`checklist_${props.release.id}`, JSON.stringify(checklistStates.value));
    showAutoSaveIndicator.value = true;
    setTimeout(() => {
      showAutoSaveIndicator.value = false;
    }, 2000);
  }, 500);
};

// Watch for checklist changes and auto-save
watch(checklistStates, autoSave, { deep: true });

// Methods
const formatStatus = (status) => {
  const statusMap = {
    'planned': 'Planned',
    'in_progress': 'In Progress',
    'blocked': 'Blocked',
    'completed': 'Completed'
  };
  return statusMap[status] || status;
};

const formatDate = (date) => {
  if (!date) return 'No date';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatRelativeTime = (date) => {
  if (!date) return '';
  const now = new Date();
  const diffInHours = Math.floor((now - new Date(date)) / (1000 * 60 * 60));

  if (diffInHours < 1) return 'Just now';
  if (diffInHours < 24) return `${diffInHours}h ago`;
  const diffInDays = Math.floor(diffInHours / 24);
  return `${diffInDays}d ago`;
};

const toggleChecklistItem = (index) => {
  checklistStates.value[index] = !checklistStates.value[index];
};

const resetChecklist = () => {
  if (confirm('Are you sure you want to reset all checklist items?')) {
    const resetStates = {};
    props.checklistTemplate.forEach((_, index) => {
      resetStates[index] = false;
    });
    checklistStates.value = resetStates;
  }
};

const updateReleaseStatus = () => {
  router.patch(`/releases/${props.release.id}`, {
    status: currentStatus.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showAutoSaveIndicator.value = true;
      setTimeout(() => {
        showAutoSaveIndicator.value = false;
      }, 2000);
    }
  });
};

const openCommunicationModal = () => {
  // In a real app, this would open a modal for composing communications
  alert('Communication modal would open here');
};

const addNewTask = () => {
  router.visit(`/releases/${props.release.id}/tasks/create`);
};

const editTask = (task) => {
  router.visit(`/releases/${props.release.id}/tasks/${task.id}/edit`);
};

const bulkUpdateTasks = () => {
  if (selectedTasks.value.length === 0) return;

  const newStatus = prompt('Enter new status (planned, in_progress, blocked, completed):');
  if (newStatus && ['planned', 'in_progress', 'blocked', 'completed'].includes(newStatus)) {
    router.patch(`/releases/${props.release.id}/tasks/bulk-update`, {
      task_ids: selectedTasks.value,
      status: newStatus
    }, {
      preserveScroll: true,
      onSuccess: () => {
        selectedTasks.value = [];
        showAutoSaveIndicator.value = true;
        setTimeout(() => {
          showAutoSaveIndicator.value = false;
        }, 2000);
      }
    });
  }
};

const resolveBlocker = (task) => {
  router.patch(`/releases/${props.release.id}/tasks/${task.id}`, {
    status: 'in_progress',
    blocker_reason: null
  }, {
    preserveScroll: true
  });
};
</script>