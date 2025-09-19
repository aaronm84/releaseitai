<template>
  <AppLayout>
    <Head :title="workstream.name" />

    <div class="space-y-6">
      <!-- Header -->
      <div class="dashboard-card rounded-lg p-6">
        <div class="flex justify-between items-center">
          <div>
            <div class="flex items-center mb-2">
              <span class="text-3xl mr-3">
                {{ workstream.type === 'product_line' ? '🏢' : workstream.type === 'initiative' ? '🎯' : '🔬' }}
              </span>
              <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent">
                {{ workstream.name }}
              </h1>
            </div>
            <p class="text-gray-600 text-lg">{{ workstream.description }}</p>
            <div class="flex items-center mt-2 space-x-4">
              <span class="status-indicator status-normal text-xs">{{ workstream.type }}</span>
              <span class="status-indicator status-normal text-xs">{{ workstream.status }}</span>
            </div>
          </div>
          <div class="text-right">
            <div class="text-sm font-medium text-gray-900">{{ workstream.completion_percentage }}% complete</div>
            <div class="text-xs text-gray-500">{{ workstream.releases.length }} releases</div>
          </div>
        </div>
      </div>

      <!-- Child Workstreams -->
      <div v-if="workstream.children && workstream.children.length > 0" class="dashboard-card rounded-lg p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 bg-gradient-to-b from-blue-500 to-indigo-500 rounded-full mr-4"></div>
          <h2 class="text-xl font-bold text-gray-900">Child Workstreams</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="child in workstream.children"
            :key="child.id"
            class="workstream-card rounded-lg p-4 cursor-pointer"
            @click="navigateToWorkstream(child.id)"
          >
            <div class="flex items-center justify-between mb-3">
              <h3 class="font-semibold text-gray-900">{{ child.name }}</h3>
              <span class="status-indicator status-normal text-xs">{{ child.type }}</span>
            </div>
            <p class="text-sm text-gray-600 mb-3">{{ child.description }}</p>
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">Active Releases</span>
              <span class="font-medium">{{ child.active_releases_count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Releases -->
      <div v-if="workstream.releases && workstream.releases.length > 0" class="dashboard-card rounded-lg p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 bg-gradient-to-b from-green-500 to-emerald-500 rounded-full mr-4"></div>
          <h2 class="text-xl font-bold text-gray-900">Releases</h2>
        </div>
        <div class="space-y-3">
          <div
            v-for="release in workstream.releases"
            :key="release.id"
            class="priority-card flex items-center p-4 rounded-lg border"
            :class="{
              'bg-green-50 border-green-200': release.status === 'completed',
              'bg-blue-50 border-blue-200': release.status === 'in_progress',
              'bg-gray-50 border-gray-200': release.status === 'planned'
            }"
          >
            <div class="flex-1">
              <h3 class="font-semibold text-gray-900">{{ release.name }}</h3>
              <p class="text-sm text-gray-600">{{ release.description }}</p>
            </div>
            <div class="text-right">
              <div class="status-indicator mb-1" :class="{
                'status-normal': release.status === 'completed',
                'status-warning': release.status === 'in_progress',
                'status-urgent': release.status === 'planned'
              }">
                {{ release.status }}
              </div>
              <div class="text-xs text-gray-500">{{ formatDate(release.target_date) }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Metrics -->
      <div v-if="workstream.metrics" class="dashboard-card rounded-lg p-6">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 bg-gradient-to-b from-purple-500 to-purple-600 rounded-full mr-4"></div>
          <h2 class="text-xl font-bold text-gray-900">Metrics</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="text-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg">
            <div class="text-2xl font-bold text-blue-900">{{ workstream.metrics.total_releases }}</div>
            <div class="text-sm text-blue-700">Total Releases</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-lg">
            <div class="text-2xl font-bold text-green-900">{{ workstream.metrics.completed_releases }}</div>
            <div class="text-sm text-green-700">Completed</div>
          </div>
          <div class="text-center p-4 bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg">
            <div class="text-2xl font-bold text-yellow-900">{{ workstream.metrics.active_releases }}</div>
            <div class="text-sm text-yellow-700">Active</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  workstream: Object,
});

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
</script>