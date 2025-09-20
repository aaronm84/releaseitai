<template>
  <AppLayout>
    <Head :title="`${release.name} - Stakeholders`" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
      <!-- Header -->
      <div class="dashboard-card p-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
              👥 Stakeholder Management
            </h1>
            <p class="mt-2 text-lg" style="color: #A1A1AA;">{{ release.name }}</p>
          </div>
          <div class="flex space-x-3">
            <button
              @click="openAddStakeholderModal"
              class="px-4 py-2 rounded-lg transition-colors"
              style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
            >
              ➕ Add Stakeholder
            </button>
            <button
              @click="openCommunicationModal"
              class="px-4 py-2 rounded-lg transition-colors"
              style="background: #3B82F6; color: #FAFAFA; border-radius: 10px;"
            >
              📧 Send Update
            </button>
          </div>
        </div>
      </div>

      <!-- Engagement Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="dashboard-card p-6" style="background: rgba(136, 77, 255, 0.1); border: 1px solid rgba(136, 77, 255, 0.3);">
          <div class="flex items-center">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #884DFF;">
              <span class="text-xl" style="color: #FAFAFA;">👥</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold" style="color: rgba(136, 77, 255, 0.9);">{{ engagementMetrics.total_stakeholders }}</h3>
              <p class="text-sm" style="color: rgba(136, 77, 255, 0.7);">Total Stakeholders</p>
            </div>
          </div>
        </div>

        <div class="dashboard-card p-6" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
          <div class="flex items-center">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #22C55E;">
              <span class="text-xl" style="color: #FAFAFA;">📈</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold" style="color: rgba(34, 197, 94, 0.9);">{{ engagementMetrics.response_rate }}%</h3>
              <p class="text-sm" style="color: rgba(34, 197, 94, 0.7);">Response Rate</p>
            </div>
          </div>
        </div>

        <div class="dashboard-card p-6" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
          <div class="flex items-center">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #3B82F6;">
              <span class="text-xl" style="color: #FAFAFA;">⏱️</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold" style="color: rgba(59, 130, 246, 0.9);">{{ engagementMetrics.avg_response_time_hours }}h</h3>
              <p class="text-sm" style="color: rgba(59, 130, 246, 0.7);">Avg Response Time</p>
            </div>
          </div>
        </div>

        <div class="dashboard-card p-6" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);">
          <div class="flex items-center">
            <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #F59E0B;">
              <span class="text-xl" style="color: #FAFAFA;">💬</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold" style="color: rgba(245, 158, 11, 0.9);">{{ engagementMetrics.recent_interactions }}</h3>
              <p class="text-sm" style="color: rgba(245, 158, 11, 0.7);">Recent Interactions</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stakeholders by Role -->
      <div class="dashboard-card p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Stakeholders by Role</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="roleGroup in stakeholdersByRole"
            :key="roleGroup.role"
            class="rounded-xl p-5" style="background: rgba(136, 77, 255, 0.1); border: 1px solid rgba(136, 77, 255, 0.3);"
          >
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold capitalize" style="color: rgba(136, 77, 255, 0.9);">{{ roleGroup.role.replace('_', ' ') }}</h3>
              <span class="text-xs px-2 py-1 rounded-full" style="background: rgba(136, 77, 255, 0.2); color: rgba(136, 77, 255, 0.8);">{{ roleGroup.count }}</span>
            </div>

            <div class="space-y-2">
              <div
                v-for="stakeholder in roleGroup.stakeholders"
                :key="stakeholder.id"
                class="flex items-center p-2 rounded-lg transition-colors"
                style="background: rgba(9, 9, 11, 0.3); border: 1px solid #27272A;"
              >
                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: #884DFF;">
                  <span class="text-xs font-bold" style="color: #FAFAFA;">{{ stakeholder.name.charAt(0) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium truncate" style="color: #FAFAFA;">{{ stakeholder.name }}</p>
                  <p class="text-xs truncate" style="color: #A1A1AA;">{{ stakeholder.email }}</p>
                </div>
                <button
                  @click="editStakeholder(stakeholder)"
                  class="p-1 transition-colors"
                  style="color: #A1A1AA;"
                >
                  ⚙️
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Communications -->
      <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Recent Communications</h2>
          </div>
          <button
            @click="openCommunicationModal"
            class="text-sm transition-colors"
            style="color: #884DFF;"
          >
            View All →
          </button>
        </div>

        <div class="space-y-6">
          <div
            v-for="communication in recentCommunications"
            :key="communication.id"
            class="p-4 rounded-lg" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-semibold" style="color: rgba(59, 130, 246, 0.9);">{{ communication.subject }}</h4>
                <div class="flex items-center space-x-4 mt-2 text-sm" style="color: rgba(59, 130, 246, 0.7);">
                  <span>📧 {{ communication.channel }}</span>
                  <span>👥 {{ communication.participants_count }} recipients</span>
                  <span>✅ {{ communication.responded_count }} responded</span>
                  <span>⏱️ {{ formatDate(communication.created_at) }}</span>
                </div>
              </div>
              <div class="ml-4">
                <div class="flex items-center space-x-2">
                  <div class="w-16 h-2 rounded-full overflow-hidden" style="background: rgba(39, 39, 42, 0.5);">
                    <div
                      class="h-full transition-all duration-300"
                      style="background: #22C55E;"
                      :style="{ width: `${(communication.responded_count / communication.participants_count) * 100}%` }"
                    ></div>
                  </div>
                  <span class="text-xs" style="color: #A1A1AA;">
                    {{ Math.round((communication.responded_count / communication.participants_count) * 100) }}%
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Stakeholder Modal -->
    <Teleport to="body">
      <div v-if="showAddStakeholderModal"
           style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0, 0, 0, 0.75) !important; z-index: 999999 !important; display: flex !important; align-items: center !important; justify-content: center !important;">
        <div style="background: #090909 !important; padding: 30px !important; border-radius: 12px !important; max-width: 500px !important; width: 90% !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important; border: 1px solid #27272A !important; backdrop-filter: blur(12px) !important;">
          <h3 style="font-size: 20px !important; font-weight: bold !important; color: #FAFAFA !important; margin-bottom: 20px !important;">
            Add Stakeholder
          </h3>

          <form @submit.prevent="addStakeholder" class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Email</label>
              <input
                v-model="stakeholderForm.email"
                type="email"
                required
                placeholder="stakeholder@company.com"
                class="w-full px-3 py-2"
                style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Role</label>
              <select
                v-model="stakeholderForm.role"
                required
                class="w-full px-3 py-2"
                style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
              >
                <option value="">Select Role</option>
                <option value="owner">👑 Owner</option>
                <option value="reviewer">📋 Reviewer</option>
                <option value="approver">✅ Approver</option>
                <option value="observer">👀 Observer</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Notification Preference</label>
              <select
                v-model="stakeholderForm.notification_preference"
                required
                class="w-full px-3 py-2"
                style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
              >
                <option value="email">📧 Email</option>
                <option value="slack">💬 Slack</option>
                <option value="none">🔇 None</option>
              </select>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                @click="closeAddStakeholderModal"
                class="px-4 py-2 rounded-lg transition-colors"
                style="background: rgba(9, 9, 11, 0.8); color: #A1A1AA; border: 1px solid #27272A; border-radius: 10px;"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 rounded-lg transition-colors"
                style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
              >
                Add Stakeholder
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Send Communication Modal -->
    <Teleport to="body">
      <div v-if="showCommunicationModal"
           style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0, 0, 0, 0.75) !important; z-index: 999999 !important; display: flex !important; align-items: center !important; justify-content: center !important;">
        <div style="background: #090909 !important; padding: 30px !important; border-radius: 12px !important; max-width: 600px !important; width: 90% !important; max-height: 90vh !important; overflow-y: auto !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important; border: 1px solid #27272A !important; backdrop-filter: blur(12px) !important;">
          <h3 style="font-size: 20px !important; font-weight: bold !important; color: #FAFAFA !important; margin-bottom: 20px !important;">
            Send Communication
          </h3>

          <form @submit.prevent="sendCommunication" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Channel</label>
                <select
                  v-model="communicationForm.channel"
                  required
                  class="w-full px-3 py-2"
                  style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
                >
                  <option value="email">📧 Email</option>
                  <option value="slack">💬 Slack</option>
                  <option value="teams">👥 Teams</option>
                  <option value="phone">📞 Phone</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                <select
                  v-model="communicationForm.priority"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                >
                  <option value="low">🟢 Low</option>
                  <option value="normal">🟡 Normal</option>
                  <option value="high">🟠 High</option>
                  <option value="urgent">🔴 Urgent</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
              <select
                v-model="communicationForm.communication_type"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              >
                <option value="update">📝 Status Update</option>
                <option value="approval_request">✅ Approval Request</option>
                <option value="notification">🔔 Notification</option>
                <option value="reminder">⏰ Reminder</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
              <input
                v-model="communicationForm.subject"
                type="text"
                required
                placeholder="Enter subject line..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Recipients by Role</label>
              <div class="grid grid-cols-2 gap-2">
                <label class="flex items-center space-x-2">
                  <input type="checkbox" v-model="communicationForm.recipient_roles" value="owner" class="rounded">
                  <span class="text-sm">👑 Owners</span>
                </label>
                <label class="flex items-center space-x-2">
                  <input type="checkbox" v-model="communicationForm.recipient_roles" value="reviewer" class="rounded">
                  <span class="text-sm">📋 Reviewers</span>
                </label>
                <label class="flex items-center space-x-2">
                  <input type="checkbox" v-model="communicationForm.recipient_roles" value="approver" class="rounded">
                  <span class="text-sm">✅ Approvers</span>
                </label>
                <label class="flex items-center space-x-2">
                  <input type="checkbox" v-model="communicationForm.recipient_roles" value="observer" class="rounded">
                  <span class="text-sm">👀 Observers</span>
                </label>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
              <textarea
                v-model="communicationForm.content"
                rows="4"
                required
                placeholder="Enter your message..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
              ></textarea>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                @click="closeCommunicationModal"
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                Send Communication
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  release: Object,
  stakeholders: Array,
  stakeholdersByRole: Array,
  engagementMetrics: Object,
  recentCommunications: Array,
  user: Object,
});

// Reactive data
const showAddStakeholderModal = ref(false);
const showCommunicationModal = ref(false);

const stakeholderForm = ref({
  email: '',
  role: '',
  notification_preference: 'email'
});

// Methods
const openAddStakeholderModal = () => {
  stakeholderForm.value = {
    email: '',
    role: '',
    notification_preference: 'email'
  };
  showAddStakeholderModal.value = true;
};

const closeAddStakeholderModal = () => {
  showAddStakeholderModal.value = false;
};

const addStakeholder = () => {
  router.post(`/releases/${props.release.id}/stakeholders`, stakeholderForm.value, {
    onSuccess: () => {
      closeAddStakeholderModal();
    }
  });
};

const editStakeholder = (stakeholder) => {
  // TODO: Implement edit stakeholder functionality
  console.log('Edit stakeholder:', stakeholder);
};

const communicationForm = ref({
  subject: '',
  content: '',
  channel: 'email',
  priority: 'normal',
  communication_type: 'update',
  recipient_roles: [],
  specific_recipients: []
});

const openCommunicationModal = () => {
  communicationForm.value = {
    subject: '',
    content: '',
    channel: 'email',
    priority: 'normal',
    communication_type: 'update',
    recipient_roles: [],
    specific_recipients: []
  };
  showCommunicationModal.value = true;
};

const closeCommunicationModal = () => {
  showCommunicationModal.value = false;
};

const sendCommunication = () => {
  router.post(`/releases/${props.release.id}/communications`, communicationForm.value, {
    onSuccess: () => {
      closeCommunicationModal();
    }
  });
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
};
</script>