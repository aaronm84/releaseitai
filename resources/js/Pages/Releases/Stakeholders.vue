<template>
  <AppLayout>
    <Head :title="`${release.name} - Stakeholders`" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="dashboard-card rounded-lg p-6">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">
              👥 Stakeholder Management
            </h1>
            <p class="text-gray-600 mt-2 text-lg">{{ release.name }}</p>
          </div>
          <div class="flex space-x-3">
            <button
              @click="openAddStakeholderModal"
              class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
            >
              ➕ Add Stakeholder
            </button>
            <button
              @click="openCommunicationModal"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              📧 Send Update
            </button>
          </div>
        </div>
      </div>

      <!-- Engagement Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="dashboard-card rounded-lg p-6 bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center">
              <span class="text-white text-xl">👥</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold text-purple-900">{{ engagementMetrics.total_stakeholders }}</h3>
              <p class="text-sm text-purple-700">Total Stakeholders</p>
            </div>
          </div>
        </div>

        <div class="dashboard-card rounded-lg p-6 bg-gradient-to-br from-green-50 to-green-100 border border-green-200">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-600 rounded-lg flex items-center justify-center">
              <span class="text-white text-xl">📈</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold text-green-900">{{ engagementMetrics.response_rate }}%</h3>
              <p class="text-sm text-green-700">Response Rate</p>
            </div>
          </div>
        </div>

        <div class="dashboard-card rounded-lg p-6 bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center">
              <span class="text-white text-xl">⏱️</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold text-blue-900">{{ engagementMetrics.avg_response_time_hours }}h</h3>
              <p class="text-sm text-blue-700">Avg Response Time</p>
            </div>
          </div>
        </div>

        <div class="dashboard-card rounded-lg p-6 bg-gradient-to-br from-orange-50 to-orange-100 border border-orange-200">
          <div class="flex items-center">
            <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg flex items-center justify-center">
              <span class="text-white text-xl">💬</span>
            </div>
            <div class="ml-4">
              <h3 class="text-lg font-bold text-orange-900">{{ engagementMetrics.recent_interactions }}</h3>
              <p class="text-sm text-orange-700">Recent Interactions</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Stakeholders by Role -->
      <div class="dashboard-card rounded-lg p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-indigo-500 rounded-full mr-4"></div>
          <h2 class="text-xl font-bold text-gray-900">Stakeholders by Role</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div
            v-for="roleGroup in stakeholdersByRole"
            :key="roleGroup.role"
            class="role-group bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-5 border border-gray-200"
          >
            <div class="flex items-center justify-between mb-4">
              <h3 class="font-bold text-gray-900 capitalize">{{ roleGroup.role.replace('_', ' ') }}</h3>
              <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded-full">{{ roleGroup.count }}</span>
            </div>

            <div class="space-y-2">
              <div
                v-for="stakeholder in roleGroup.stakeholders"
                :key="stakeholder.id"
                class="flex items-center p-2 bg-white rounded-lg hover:bg-gray-50 transition-colors"
              >
                <div class="w-8 h-8 bg-gradient-to-br from-gray-400 to-gray-500 rounded-full flex items-center justify-center mr-3">
                  <span class="text-white text-xs font-bold">{{ stakeholder.name.charAt(0) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ stakeholder.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ stakeholder.email }}</p>
                </div>
                <button
                  @click="editStakeholder(stakeholder)"
                  class="p-1 text-gray-400 hover:text-gray-600 transition-colors"
                >
                  ⚙️
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Communications -->
      <div class="dashboard-card rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 bg-gradient-to-b from-blue-500 to-cyan-500 rounded-full mr-4"></div>
            <h2 class="text-xl font-bold text-gray-900">Recent Communications</h2>
          </div>
          <button
            @click="openCommunicationModal"
            class="text-sm text-blue-600 hover:text-blue-800 transition-colors"
          >
            View All →
          </button>
        </div>

        <div class="space-y-4">
          <div
            v-for="communication in recentCommunications"
            :key="communication.id"
            class="communication-item p-4 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg border border-blue-200"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <h4 class="font-semibold text-blue-900">{{ communication.subject }}</h4>
                <div class="flex items-center space-x-4 mt-2 text-sm text-blue-700">
                  <span>📧 {{ communication.channel }}</span>
                  <span>👥 {{ communication.participants_count }} recipients</span>
                  <span>✅ {{ communication.responded_count }} responded</span>
                  <span>⏱️ {{ formatDate(communication.created_at) }}</span>
                </div>
              </div>
              <div class="ml-4">
                <div class="flex items-center space-x-2">
                  <div class="w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                    <div
                      class="h-full bg-gradient-to-r from-green-400 to-green-500 transition-all duration-300"
                      :style="{ width: `${(communication.responded_count / communication.participants_count) * 100}%` }"
                    ></div>
                  </div>
                  <span class="text-xs text-gray-600">
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
        <div style="background: white !important; padding: 30px !important; border-radius: 12px !important; max-width: 500px !important; width: 90% !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;">
          <h3 style="font-size: 20px !important; font-weight: bold !important; color: #1f2937 !important; margin-bottom: 20px !important;">
            Add Stakeholder
          </h3>

          <form @submit.prevent="addStakeholder" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
              <input
                v-model="stakeholderForm.email"
                type="email"
                required
                placeholder="stakeholder@company.com"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
              <select
                v-model="stakeholderForm.role"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              >
                <option value="">Select Role</option>
                <option value="owner">👑 Owner</option>
                <option value="reviewer">📋 Reviewer</option>
                <option value="approver">✅ Approver</option>
                <option value="observer">👀 Observer</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Notification Preference</label>
              <select
                v-model="stakeholderForm.notification_preference"
                required
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
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
                class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors"
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
        <div style="background: white !important; padding: 30px !important; border-radius: 12px !important; max-width: 600px !important; width: 90% !important; max-height: 90vh !important; overflow-y: auto !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;">
          <h3 style="font-size: 20px !important; font-weight: bold !important; color: #1f2937 !important; margin-bottom: 20px !important;">
            Send Communication
          </h3>

          <form @submit.prevent="sendCommunication" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Channel</label>
                <select
                  v-model="communicationForm.channel"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
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