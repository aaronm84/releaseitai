<template>
  <AppLayout>
    <Head :title="workstream.name" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
      <!-- Header -->
      <div class="dashboard-card p-8">
        <div class="flex justify-between items-center">
          <div>
            <div class="flex items-center mb-2">
              <span class="text-3xl mr-3">
                {{ workstream.type === 'product_line' ? '🏢' : workstream.type === 'initiative' ? '🎯' : '🔬' }}
              </span>
              <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
                {{ workstream.name }}
              </h1>
            </div>
            <p class="text-lg" style="color: #A1A1AA;">{{ workstream.description }}</p>
            <div class="flex items-center mt-2 space-x-4">
              <span class="px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.2); color: #884DFF; border: 1px solid rgba(136, 77, 255, 0.3);">{{ workstream.type }}</span>
              <span class="px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.2); color: #884DFF; border: 1px solid rgba(136, 77, 255, 0.3);">{{ workstream.status }}</span>
            </div>
          </div>
          <div class="text-right">
            <div class="text-sm font-medium" style="color: #FAFAFA;">{{ workstream.completion_percentage }}% complete</div>
            <div class="text-xs" style="color: #A1A1AA;">{{ workstream.releases.length }} releases</div>
          </div>
        </div>
      </div>

      <!-- Child Workstreams -->
      <div v-if="workstream.children && workstream.children.length > 0" class="dashboard-card p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Child Workstreams</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="child in workstream.children"
            :key="child.id"
            class="rounded-lg p-4 cursor-pointer transition-all duration-300"
            style="background: rgba(9, 9, 11, 0.3); border: 1px solid #27272A; backdrop-filter: blur(12px);"
            @click="navigateToWorkstream(child.id)"
          >
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold" style="color: #FAFAFA;">{{ child.name }}</h3>
              <span class="px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.2); color: #884DFF; border: 1px solid rgba(136, 77, 255, 0.3);">{{ child.type }}</span>
            </div>
            <p class="text-sm mb-3" style="color: #A1A1AA;">{{ child.description }}</p>
            <div class="flex justify-between text-sm">
              <span style="color: #A1A1AA;">Active Releases</span>
              <span class="font-medium" style="color: #FAFAFA;">{{ child.active_releases_count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Releases -->
      <div v-if="workstream.releases && workstream.releases.length > 0" class="dashboard-card p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Releases</h2>
        </div>
        <div class="space-y-4">
          <div
            v-for="release in workstream.releases"
            :key="release.id"
            class="flex items-center p-4 rounded-lg transition-all duration-300"
            :style="{
              background: release.status === 'completed' ? 'rgba(34, 197, 94, 0.1)' : release.status === 'in_progress' ? 'rgba(59, 130, 246, 0.1)' : 'rgba(136, 77, 255, 0.1)',
              border: release.status === 'completed' ? '1px solid rgba(34, 197, 94, 0.3)' : release.status === 'in_progress' ? '1px solid rgba(59, 130, 246, 0.3)' : '1px solid rgba(136, 77, 255, 0.3)',
              backdropFilter: 'blur(12px)'
            }"
          >
            <div class="flex-1">
              <h3 class="font-semibold" style="color: #FAFAFA;">{{ release.name }}</h3>
              <p class="text-sm" style="color: #A1A1AA;">{{ release.description }}</p>
            </div>
            <div class="text-right">
              <div class="mb-1 px-3 py-1 rounded-full text-xs font-medium" :style="{
                background: release.status === 'completed' ? 'rgba(34, 197, 94, 0.2)' : release.status === 'in_progress' ? 'rgba(59, 130, 246, 0.2)' : 'rgba(136, 77, 255, 0.2)',
                color: release.status === 'completed' ? '#22C55E' : release.status === 'in_progress' ? '#3B82F6' : '#884DFF',
                border: release.status === 'completed' ? '1px solid rgba(34, 197, 94, 0.3)' : release.status === 'in_progress' ? '1px solid rgba(59, 130, 246, 0.3)' : '1px solid rgba(136, 77, 255, 0.3)'
              }">
                {{ release.status }}
              </div>
              <div class="text-xs" style="color: #A1A1AA;">{{ formatDate(release.target_date) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Stakeholders -->
      <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="">
                <span class="text-lg">👥</span>
              </div>
              <h2 class="text-xl font-bold" style="color: #FAFAFA;">Stakeholders</h2>
            </div>
          </div>
          <button
            @click="showAddStakeholder = true"
            class="px-4 py-2 rounded-xl transition-all duration-300 font-medium text-sm"
            style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
          >
            Add Stakeholder
          </button>
        </div>

        <div v-if="stakeholders.length === 0" class="text-center py-8" style="color: #A1A1AA;">
          <span class="text-4xl mb-3 block">👤</span>
          <p>No stakeholders added yet. Add key people involved in this {{ workstream.type }}.</p>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="stakeholder in stakeholders"
            :key="stakeholder.id"
            class="p-4 rounded-xl"
            style="background: rgba(9, 9, 11, 0.3); border: 1px solid #27272A; backdrop-filter: blur(12px);"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                  <span class="text-white font-semibold text-sm">{{ stakeholder.name.split(' ').map(n => n[0]).join('').substring(0, 2) }}</span>
                </div>
                <div>
                  <h3 class="font-semibold" style="color: #FAFAFA;">{{ stakeholder.name }}</h3>
                  <p class="text-xs" style="color: #A1A1AA;">{{ stakeholder.role }}</p>
                </div>
              </div>
              <button
                @click="removeStakeholder(stakeholder.id)"
                class="text-red-400 hover:text-red-300 transition-colors"
              >
                ×
              </button>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              <p>{{ stakeholder.email }}</p>
              <div class="flex items-center justify-between mt-2">
                <span class="px-2 py-1 rounded-lg text-xs" style="background: rgba(136, 77, 255, 0.2); color: #884DFF;">
                  {{ stakeholder.involvement_level }}
                </span>
                <button
                  @click="contactStakeholder(stakeholder)"
                  class="text-xs px-2 py-1 rounded-lg transition-colors"
                  style="background: rgba(59, 130, 246, 0.2); color: #3B82F6;"
                >
                  Contact
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Documents & Collateral -->
      <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="">
                <span class="text-lg">📋</span>
              </div>
              <h2 class="text-xl font-bold" style="color: #FAFAFA;">Documents & Collateral</h2>
            </div>
          </div>
          <div class="flex space-x-2">
            <input
              ref="fileInput"
              type="file"
              multiple
              @change="handleFileUpload"
              class="hidden"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.md"
            >
            <button
              @click="$refs.fileInput.click()"
              class="px-4 py-2 rounded-xl transition-all duration-300 font-medium text-sm"
              style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
            >
              Upload Files
            </button>
            <button
              @click="showAddLink = true"
              class="px-4 py-2 rounded-xl transition-all duration-300 font-medium text-sm"
              style="background: rgba(9, 9, 11, 0.8); color: #A1A1AA; border: 1px solid #27272A; border-radius: 10px;"
            >
              Add Link
            </button>
          </div>
        </div>

        <div v-if="documents.length === 0" class="text-center py-8" style="color: #A1A1AA;">
          <span class="text-4xl mb-3 block">📄</span>
          <p>No documents uploaded yet. Add project documents, specs, and collateral.</p>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="document in documents"
            :key="document.id"
            class="flex items-center justify-between p-4 rounded-xl"
            style="background: rgba(9, 9, 11, 0.3); border: 1px solid #27272A; backdrop-filter: blur(12px);"
          >
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 rounded-lg flex items-center justify-center" :style="{
                background: getDocumentTypeColor(document.type).bg,
                color: getDocumentTypeColor(document.type).text
              }">
                <span class="text-sm">{{ getDocumentIcon(document.type) }}</span>
              </div>
              <div>
                <h3 class="font-semibold" style="color: #FAFAFA;">{{ document.name }}</h3>
                <p class="text-sm" style="color: #A1A1AA;">{{ document.type }} • {{ formatFileSize(document.size) }} • {{ formatDate(document.uploaded_at) }}</p>
              </div>
            </div>
            <div class="flex space-x-2">
              <button
                @click="downloadDocument(document)"
                class="text-sm px-3 py-1 rounded-lg transition-colors"
                style="background: rgba(59, 130, 246, 0.2); color: #3B82F6;"
              >
                Download
              </button>
              <button
                @click="removeDocument(document.id)"
                class="text-red-400 hover:text-red-300 transition-colors px-2"
              >
                ×
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Brain Dump -->
      <div class="dashboard-card p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <div class="flex items-center space-x-3">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="">
              <span class="text-lg">🧠</span>
            </div>
            <h2 class="text-xl font-bold" style="color: #FAFAFA;">{{ workstream.name }} Brain Dump</h2>
          </div>
        </div>

        <BrainDump :config="{
          placeholder: `Capture ideas, meeting notes, and thoughts for ${workstream.name}...`,
          workstreamId: workstream.id
        }" />
      </div>

      <!-- Communications -->
      <div class="dashboard-card p-6">
        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
            <div class="flex items-center space-x-3">
              <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="">
                <span class="text-lg">💬</span>
              </div>
              <h2 class="text-xl font-bold" style="color: #FAFAFA;">Communications</h2>
            </div>
          </div>
          <button
            @click="showAddCommunication = true"
            class="px-4 py-2 rounded-xl transition-all duration-300 font-medium text-sm"
            style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
          >
            Log Communication
          </button>
        </div>

        <div v-if="communications.length === 0" class="text-center py-8" style="color: #A1A1AA;">
          <span class="text-4xl mb-3 block">💭</span>
          <p>No communications logged yet. Track meetings, emails, and important conversations.</p>
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="comm in communications"
            :key="comm.id"
            class="p-4 rounded-xl"
            style="background: rgba(9, 9, 11, 0.3); border: 1px solid #27272A; backdrop-filter: blur(12px);"
          >
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" :style="{
                  background: getCommunicationTypeColor(comm.type).bg,
                  color: getCommunicationTypeColor(comm.type).text
                }">
                  <span class="text-sm">{{ getCommunicationIcon(comm.type) }}</span>
                </div>
                <div>
                  <h3 class="font-semibold" style="color: #FAFAFA;">{{ comm.subject }}</h3>
                  <p class="text-sm" style="color: #A1A1AA;">{{ comm.type }} • {{ formatDate(comm.date) }}</p>
                </div>
              </div>
              <span class="px-2 py-1 rounded-lg text-xs" :style="{
                background: getCommunicationTypeColor(comm.type).bg,
                color: getCommunicationTypeColor(comm.type).text
              }">
                {{ comm.type }}
              </span>
            </div>
            <p class="text-sm mb-3" style="color: #A1A1AA;">{{ comm.summary }}</p>
            <div class="flex items-center justify-between">
              <span class="text-xs" style="color: #666;">With: {{ comm.participants.join(', ') }}</span>
              <div class="flex space-x-2">
                <button
                  @click="viewCommunication(comm)"
                  class="text-xs px-2 py-1 rounded-lg transition-colors"
                  style="background: rgba(59, 130, 246, 0.2); color: #3B82F6;"
                >
                  View Details
                </button>
                <button
                  @click="removeCommunication(comm.id)"
                  class="text-red-400 hover:text-red-300 transition-colors text-xs px-2"
                >
                  ×
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Metrics -->
      <div v-if="workstream.metrics" class="dashboard-card p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Metrics</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="text-center p-4 rounded-lg" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
            <div class="text-2xl font-bold" style="color: rgba(59, 130, 246, 0.9);">{{ workstream.metrics.total_releases }}</div>
            <div class="text-sm" style="color: rgba(59, 130, 246, 0.7);">Total Releases</div>
          </div>
          <div class="text-center p-4 rounded-lg" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
            <div class="text-2xl font-bold" style="color: rgba(34, 197, 94, 0.9);">{{ workstream.metrics.completed_releases }}</div>
            <div class="text-sm" style="color: rgba(34, 197, 94, 0.7);">Completed</div>
          </div>
          <div class="text-center p-4 rounded-lg" style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3);">
            <div class="text-2xl font-bold" style="color: rgba(245, 158, 11, 0.9);">{{ workstream.metrics.active_releases }}</div>
            <div class="text-sm" style="color: rgba(245, 158, 11, 0.7);">Active</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BrainDump from '@/Components/BrainDump.vue';

const props = defineProps({
  workstream: Object,
});

// Reactive data
const stakeholders = ref([]);

const documents = ref([]);

const communications = ref([]);

// Modal states
const showAddStakeholder = ref(false);
const showAddLink = ref(false);
const showAddCommunication = ref(false);

// Methods
const navigateToWorkstream = (workstreamId) => {
  router.visit(`/workstreams/${workstreamId}`);
};

const formatDate = (date) => {
  if (!date) return 'No date';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Stakeholder methods
const removeStakeholder = (stakeholderId) => {
  stakeholders.value = stakeholders.value.filter(s => s.id !== stakeholderId);
};

const contactStakeholder = (stakeholder) => {
  window.location.href = `mailto:${stakeholder.email}?subject=Re: ${props.workstream.name}`;
};

// Document methods
const handleFileUpload = (event) => {
  const files = Array.from(event.target.files);
  files.forEach(file => {
    const newDoc = {
      id: Date.now() + Math.random(),
      name: file.name,
      type: file.type.split('/')[1].toUpperCase(),
      size: file.size,
      uploaded_at: new Date().toISOString().split('T')[0],
      url: URL.createObjectURL(file)
    };
    documents.value.push(newDoc);
  });
};

const removeDocument = (documentId) => {
  documents.value = documents.value.filter(d => d.id !== documentId);
};

const downloadDocument = (document) => {
  const link = document.createElement('a');
  link.href = document.url;
  link.download = document.name;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

const getDocumentTypeColor = (type) => {
  const colors = {
    'PDF': { bg: 'rgba(239, 68, 68, 0.2)', text: '#EF4444' },
    'WORD': { bg: 'rgba(59, 130, 246, 0.2)', text: '#3B82F6' },
    'EXCEL': { bg: 'rgba(34, 197, 94, 0.2)', text: '#22C55E' },
    'POWERPOINT': { bg: 'rgba(245, 158, 11, 0.2)', text: '#F59E0B' },
    'TEXT': { bg: 'rgba(107, 114, 128, 0.2)', text: '#6B7280' },
    'MARKDOWN': { bg: 'rgba(136, 77, 255, 0.2)', text: '#884DFF' }
  };
  return colors[type.toUpperCase()] || colors['TEXT'];
};

const getDocumentIcon = (type) => {
  const icons = {
    'PDF': '📄',
    'WORD': '📝',
    'DOCX': '📝',
    'EXCEL': '📊',
    'XLSX': '📊',
    'POWERPOINT': '📊',
    'PPTX': '📊',
    'TEXT': '📄',
    'TXT': '📄',
    'MARKDOWN': '📝',
    'MD': '📝'
  };
  return icons[type.toUpperCase()] || '📄';
};

// Communication methods
const removeCommunication = (commId) => {
  communications.value = communications.value.filter(c => c.id !== commId);
};

const viewCommunication = (comm) => {
  // TODO: Open modal or navigation to detailed view
  console.log('View communication:', comm);
};

const getCommunicationTypeColor = (type) => {
  const colors = {
    'meeting': { bg: 'rgba(59, 130, 246, 0.2)', text: '#3B82F6' },
    'email': { bg: 'rgba(34, 197, 94, 0.2)', text: '#22C55E' },
    'call': { bg: 'rgba(245, 158, 11, 0.2)', text: '#F59E0B' },
    'slack': { bg: 'rgba(136, 77, 255, 0.2)', text: '#884DFF' },
    'other': { bg: 'rgba(107, 114, 128, 0.2)', text: '#6B7280' }
  };
  return colors[type] || colors['other'];
};

const getCommunicationIcon = (type) => {
  const icons = {
    'meeting': '🤝',
    'email': '📧',
    'call': '📞',
    'slack': '💬',
    'other': '💭'
  };
  return icons[type] || '💭';
};
</script>