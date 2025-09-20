<template>
  <AppLayout>
    <Head title="Workstreams" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
      <!-- Header -->
      <div class="p-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
              🏗️ Workstreams
            </h1>
            <p class="mt-2 text-lg" style="color: #A1A1AA;">Organize your work into hierarchical streams</p>
          </div>
          <button
            @click="openCreateModal"
            class="px-4 py-2 rounded-lg transition-colors"
            style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
          >
            ➕ Create Workstream
          </button>
        </div>
      </div>

      <!-- Workstream Hierarchy Overview -->
      <div class="dashboard-card p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Hierarchy Structure</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="rounded-xl p-5" style="background: rgba(136, 77, 255, 0.1); border: 1px solid rgba(136, 77, 255, 0.3);">
            <div class="flex items-center mb-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: #884DFF;">
                <span class="text-sm font-bold" style="color: #FAFAFA;">🏢</span>
              </div>
              <h3 class="ml-3 font-bold" style="color: rgba(136, 77, 255, 0.9);">Product Lines</h3>
            </div>
            <p class="text-sm" style="color: rgba(136, 77, 255, 0.7);">Top-level business areas or product families</p>
            <div class="mt-3 text-xs" style="color: rgba(136, 77, 255, 0.6);">{{ productLines.length }} active</div>
          </div>

          <div class="rounded-xl p-5" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
            <div class="flex items-center mb-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: #3B82F6;">
                <span class="text-sm font-bold" style="color: #FAFAFA;">🎯</span>
              </div>
              <h3 class="ml-3 font-bold" style="color: rgba(59, 130, 246, 0.9);">Initiatives</h3>
            </div>
            <p class="text-sm" style="color: rgba(59, 130, 246, 0.7);">Strategic projects within product lines</p>
            <div class="mt-3 text-xs" style="color: rgba(59, 130, 246, 0.6);">{{ initiatives.length }} active</div>
          </div>

          <div class="rounded-xl p-5" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
            <div class="flex items-center mb-3">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: #22C55E;">
                <span class="text-sm font-bold" style="color: #FAFAFA;">🔬</span>
              </div>
              <h3 class="ml-3 font-bold" style="color: rgba(34, 197, 94, 0.9);">Experiments</h3>
            </div>
            <p class="text-sm" style="color: rgba(34, 197, 94, 0.7);">Specific experiments and releases</p>
            <div class="mt-3 text-xs" style="color: rgba(34, 197, 94, 0.6);">{{ experiments.length }} active</div>
          </div>
        </div>
      </div>

      <!-- Filter and Search -->
      <div class="dashboard-card p-6">
        <div class="flex flex-col md:flex-row gap-4">
          <div class="flex-1">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="🔍 Search workstreams..."
              class="w-full px-4 py-3 transition-all duration-200"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            />
          </div>
          <div class="flex gap-3">
            <select
              v-model="filterType"
              class="px-4 py-3 transition-all duration-200"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            >
              <option value="">All Types</option>
              <option value="product_line">Product Lines</option>
              <option value="initiative">Initiatives</option>
              <option value="experiment">Experiments</option>
            </select>
            <select
              v-model="filterStatus"
              class="px-4 py-3 transition-all duration-200"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            >
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="planning">Planning</option>
              <option value="on_hold">On Hold</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Workstreams Tree View -->
      <div class="space-y-6">
        <div
          v-for="productLine in filteredWorkstreams"
          :key="productLine.id"
          class="workstream-tree-item"
        >
          <!-- Product Line -->
          <div class="rounded-xl p-6" style="background: rgba(136, 77, 255, 0.1); border-left: 4px solid #884DFF; border-right: 1px solid rgba(136, 77, 255, 0.3); border-top: 1px solid rgba(136, 77, 255, 0.3); border-bottom: 1px solid rgba(136, 77, 255, 0.3);">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <button
                  @click="toggleExpand(productLine.id)"
                  class="mr-3 p-1 rounded transition-colors"
                  style="color: #884DFF;"
                >
                  <svg class="w-5 h-5 transform transition-transform" :class="{ 'rotate-90': expandedItems.has(productLine.id) }" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                  </svg>
                </button>
                <div class="w-10 h-10 rounded-lg flex items-center justify-center mr-4" style="background: #884DFF;">
                  <span class="font-bold" style="color: #FAFAFA;">🏢</span>
                </div>
                <div
                  class="cursor-pointer"
                  @click="navigateToWorkstream(productLine.id)"
                >
                  <h3 class="font-bold text-lg hover:underline" style="color: #FAFAFA;">{{ productLine.name }}</h3>
                  <p class="text-sm" style="color: #A1A1AA;">{{ productLine.description }}</p>
                </div>
              </div>
              <div class="flex items-center space-x-3">
                <span class="px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.2); color: #884DFF; border: 1px solid rgba(136, 77, 255, 0.3);">{{ productLine.status }}</span>
                <div class="text-right">
                  <div class="text-sm font-medium" style="color: #FAFAFA;">{{ productLine.active_releases_count }} releases</div>
                  <div class="text-xs" style="color: #A1A1AA;">{{ productLine.completion_percentage }}% complete</div>
                </div>
                <button
                  @click="editWorkstream(productLine)"
                  class="p-2 rounded-lg transition-colors"
                  style="color: #884DFF;"
                >
                  ✏️
                </button>
              </div>
            </div>

            <!-- Initiatives under this Product Line -->
            <div v-if="expandedItems.has(productLine.id)" class="mt-6 ml-8 space-y-4">
              <div
                v-for="initiative in getInitiatives(productLine.id)"
                :key="initiative.id"
                class="workstream-card rounded-lg p-4 bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-500"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <button
                      @click="toggleExpand(initiative.id)"
                      class="mr-3 p-1 hover:bg-blue-200 rounded transition-colors"
                    >
                      <svg class="w-4 h-4 transform transition-transform" :class="{ 'rotate-90': expandedItems.has(initiative.id) }" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                      </svg>
                    </button>
                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center mr-3">
                      <span class="text-white font-bold text-sm">🎯</span>
                    </div>
                    <div
                      class="cursor-pointer"
                      @click="navigateToWorkstream(initiative.id)"
                    >
                      <h4 class="font-semibold text-blue-900 hover:underline">{{ initiative.name }}</h4>
                      <p class="text-xs text-blue-700">{{ initiative.description }}</p>
                    </div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <span class="status-indicator status-normal text-xs">{{ initiative.status }}</span>
                    <div class="text-right">
                      <div class="text-xs font-medium text-blue-900">{{ initiative.active_releases_count }} releases</div>
                    </div>
                    <button
                      @click="editWorkstream(initiative)"
                      class="p-1 text-blue-600 hover:bg-blue-200 rounded transition-colors"
                    >
                      ✏️
                    </button>
                  </div>
                </div>

                <!-- Experiments under this Initiative -->
                <div v-if="expandedItems.has(initiative.id)" class="mt-4 ml-6 space-y-2">
                  <div
                    v-for="experiment in getExperiments(initiative.id)"
                    :key="experiment.id"
                    class="workstream-card rounded-lg p-3 bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center">
                        <div class="w-6 h-6 bg-gradient-to-br from-green-500 to-green-600 rounded flex items-center justify-center mr-3">
                          <span class="text-white font-bold text-xs">🔬</span>
                        </div>
                        <div
                          class="cursor-pointer"
                          @click="navigateToWorkstream(experiment.id)"
                        >
                          <h5 class="font-medium text-green-900 text-sm hover:underline">{{ experiment.name }}</h5>
                          <p class="text-xs text-green-700">{{ experiment.description }}</p>
                        </div>
                      </div>
                      <div class="flex items-center space-x-2">
                        <span class="status-indicator status-normal text-xs">{{ experiment.status }}</span>
                        <div class="text-xs font-medium text-green-900">{{ experiment.active_releases_count }} releases</div>
                        <button
                          @click="editWorkstream(experiment)"
                          class="p-1 text-green-600 hover:bg-green-200 rounded transition-colors"
                        >
                          ✏️
                        </button>
                      </div>
                    </div>
                  </div>
                  <button
                    @click="createChildWorkstream(initiative, 'experiment')"
                    class="w-full p-2 border-2 border-dashed border-green-300 rounded-lg text-green-600 hover:bg-green-50 transition-colors text-sm"
                  >
                    ➕ Add Experiment
                  </button>
                </div>
              </div>
              <button
                @click="createChildWorkstream(productLine, 'initiative')"
                class="w-full p-3 border-2 border-dashed border-blue-300 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
              >
                ➕ Add Initiative
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </AppLayout>

  <!-- Create/Edit Workstream Modal - Using Teleport to body -->
  <Teleport to="body">
    <div v-if="showCreateModal"
         style="position: fixed !important; top: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; background: rgba(0, 0, 0, 0.75) !important; z-index: 999999 !important; display: flex !important; align-items: center !important; justify-content: center !important;">
      <div style="background: #090909 !important; padding: 30px !important; border-radius: 12px !important; max-width: 500px !important; width: 90% !important; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important; border: 1px solid #27272A !important; backdrop-filter: blur(12px) !important;">
        <h3 style="font-size: 20px !important; font-weight: bold !important; color: #FAFAFA !important; margin-bottom: 20px !important;">
          {{ editingWorkstream ? 'Edit' : 'Create' }} Workstream
        </h3>

        <form @submit.prevent="saveWorkstream" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Name</label>
            <input
              v-model="workstreamForm.name"
              type="text"
              required
              class="w-full px-3 py-2"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Description</label>
            <textarea
              v-model="workstreamForm.description"
              rows="3"
              class="w-full px-3 py-2 resize-none"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            ></textarea>
          </div>

          <div>
            <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Type</label>
            <select
              v-model="workstreamForm.type"
              required
              class="w-full px-3 py-2"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            >
              <option value="product_line">🏢 Product Line</option>
              <option value="initiative">🎯 Initiative</option>
              <option value="experiment">🔬 Experiment</option>
            </select>
          </div>

          <div v-if="workstreamForm.type !== 'product_line'">
            <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Parent Workstream</label>
            <select
              v-model="workstreamForm.parent_id"
              required
              class="w-full px-3 py-2"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
            >
              <option value="">Select Parent</option>
              <option
                v-for="parent in getParentOptions(workstreamForm.type)"
                :key="parent.id"
                :value="parent.id"
              >
                {{ parent.name }}
              </option>
            </select>
          </div>

          <div class="flex justify-end space-x-3 pt-4">
            <button
              type="button"
              @click="cancelEdit"
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
              {{ editingWorkstream ? 'Update' : 'Create' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  workstreams: Array,
});

// Reactive data
const searchQuery = ref('');
const filterType = ref('');
const filterStatus = ref('');
const expandedItems = ref(new Set());
const showCreateModal = ref(false);
const editingWorkstream = ref(null);

const workstreamForm = ref({
  name: '',
  description: '',
  type: 'product_line',
  parent_id: null,
  status: 'active'
});

// Computed properties
const productLines = computed(() =>
  props.workstreams.filter(w => w.type === 'product_line')
);

const initiatives = computed(() =>
  props.workstreams.filter(w => w.type === 'initiative')
);

const experiments = computed(() =>
  props.workstreams.filter(w => w.type === 'experiment')
);

const filteredWorkstreams = computed(() => {
  let filtered = productLines.value;

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(w =>
      w.name.toLowerCase().includes(query) ||
      w.description?.toLowerCase().includes(query)
    );
  }

  if (filterStatus.value) {
    filtered = filtered.filter(w => w.status === filterStatus.value);
  }

  return filtered;
});

// Methods
const toggleExpand = (id) => {
  if (expandedItems.value.has(id)) {
    expandedItems.value.delete(id);
  } else {
    expandedItems.value.add(id);
  }
};

const getInitiatives = (productLineId) => {
  return initiatives.value.filter(i => i.parent_workstream_id === productLineId);
};

const getExperiments = (initiativeId) => {
  return experiments.value.filter(e => e.parent_workstream_id === initiativeId);
};

const getParentOptions = (type) => {
  if (type === 'initiative') {
    return productLines.value;
  } else if (type === 'experiment') {
    return initiatives.value;
  }
  return [];
};

const createChildWorkstream = (parent, type) => {
  workstreamForm.value = {
    name: '',
    description: '',
    type: type,
    parent_id: parent.id,
    status: 'active'
  };
  editingWorkstream.value = null;
  showCreateModal.value = true;
};

const openCreateModal = () => {
  workstreamForm.value = {
    name: '',
    description: '',
    type: 'product_line',
    parent_id: null,
    status: 'active'
  };
  editingWorkstream.value = null;
  showCreateModal.value = true;
};

const editWorkstream = (workstream) => {
  workstreamForm.value = { ...workstream };
  editingWorkstream.value = workstream;
  showCreateModal.value = true;
};

const saveWorkstream = () => {
  const url = editingWorkstream.value
    ? `/workstreams/${editingWorkstream.value.id}`
    : '/workstreams';

  const method = editingWorkstream.value ? 'put' : 'post';

  // Transform parent_id to parent_workstream_id for backend compatibility
  const formData = {
    ...workstreamForm.value,
    parent_workstream_id: workstreamForm.value.parent_id
  };
  delete formData.parent_id;

  router[method](url, formData, {
    onSuccess: () => {
      showCreateModal.value = false;
      resetForm();
    }
  });
};

const cancelEdit = () => {
  showCreateModal.value = false;
  resetForm();
};

const resetForm = () => {
  workstreamForm.value = {
    name: '',
    description: '',
    type: 'product_line',
    parent_id: null,
    status: 'active'
  };
  editingWorkstream.value = null;
};

const navigateToWorkstream = (workstreamId) => {
  router.visit(`/workstreams/${workstreamId}`);
};

onMounted(() => {
  // Auto-expand first product line for better UX
  if (productLines.value.length > 0) {
    expandedItems.value.add(productLines.value[0].id);
  }
});
</script>