<template>
  <AppLayout>
    <Head :title="content.title" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8" style="background: #090909; min-height: 100vh;">
      <!-- Header Section -->
      <div class="p-8">
        <div class="flex justify-between items-start">
          <div>
            <button
              @click="goBack"
              class="flex items-center space-x-2 mb-4 text-sm transition-colors"
              style="color: #884DFF;"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
              </svg>
              <span>Back to Content</span>
            </button>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">{{ content.title }}</h1>
            <div class="flex items-center space-x-4 mt-2">
              <span
                class="px-3 py-1 rounded-full text-sm font-medium"
                :style="getTypeStyle(content.type)"
              >
                {{ formatType(content.type) }}
              </span>
              <span
                class="px-3 py-1 rounded-full text-sm font-medium"
                :style="getStatusStyle(content.status)"
              >
                {{ formatStatus(content.status) }}
              </span>
              <span class="text-sm" style="color: #A1A1AA;">
                Created {{ formatDate(content.created_at) }}
              </span>
            </div>
          </div>
          <div class="flex items-center space-x-3">
            <button
              @click="deleteContent"
              class="px-4 py-2 rounded-lg transition-colors flex items-center space-x-2"
              style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #EF4444;"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
              </svg>
              <span>Delete</span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Original Content -->
          <div class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h2 class="text-xl font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
              Original Content
            </h2>
            <div
              class="prose prose-invert max-w-none p-4 rounded-lg"
              style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A; color: #E5E7EB; line-height: 1.6;"
            >
              {{ content.content }}
            </div>
          </div>

          <!-- AI Summary (if available) -->
          <div v-if="content.ai_summary" class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h2 class="text-xl font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
              AI Summary
            </h2>
            <div
              class="prose prose-invert max-w-none p-4 rounded-lg"
              style="background: rgba(136, 77, 255, 0.05); border: 1px solid rgba(136, 77, 255, 0.2); color: #E5E7EB; line-height: 1.6;"
            >
              {{ content.ai_summary }}
            </div>
          </div>

          <!-- Tags -->
          <div v-if="content.tags && content.tags.length > 0" class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h2 class="text-xl font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-green-400 rounded-full mr-3"></span>
              Tags
            </h2>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="tag in content.tags"
                :key="tag"
                class="px-3 py-1 rounded-full text-sm font-medium"
                style="background: rgba(59, 130, 246, 0.1); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3);"
              >
                {{ tag }}
              </span>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Action Items -->
          <div v-if="content.action_items && content.action_items.length > 0" class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-orange-400 rounded-full mr-3"></span>
              Action Items ({{ content.action_items.length }})
            </h3>
            <div class="space-y-3">
              <div
                v-for="item in content.action_items"
                :key="item.id"
                class="p-3 rounded-lg"
                style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;"
              >
                <div class="flex items-start justify-between mb-2">
                  <span class="text-sm font-medium" style="color: #FAFAFA;">{{ item.action_text }}</span>
                  <span
                    class="px-2 py-1 rounded-full text-xs font-medium"
                    :style="getPriorityStyle(item.priority)"
                  >
                    {{ formatPriority(item.priority) }}
                  </span>
                </div>
                <div class="flex items-center justify-between text-xs" style="color: #A1A1AA;">
                  <span>{{ formatStatus(item.status) }}</span>
                  <span v-if="item.due_date">Due: {{ formatDate(item.due_date) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Stakeholders -->
          <div v-if="content.stakeholders && content.stakeholders.length > 0" class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-blue-400 rounded-full mr-3"></span>
              Related Stakeholders ({{ content.stakeholders.length }})
            </h3>
            <div class="space-y-2">
              <div
                v-for="stakeholder in content.stakeholders"
                :key="stakeholder.id"
                class="flex items-center space-x-3 p-2 rounded-lg"
                style="background: rgba(9, 9, 11, 0.5);"
              >
                <div class="w-8 h-8 bg-blue-500/20 rounded-full flex items-center justify-center">
                  <span class="text-xs font-medium text-blue-300">{{ stakeholder.name.split(' ').map(n => n[0]).join('').toUpperCase() }}</span>
                </div>
                <span class="text-sm" style="color: #FAFAFA;">{{ stakeholder.name }}</span>
              </div>
            </div>
          </div>

          <!-- Workstreams -->
          <div v-if="content.workstreams && content.workstreams.length > 0" class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-purple-400 rounded-full mr-3"></span>
              Related Workstreams ({{ content.workstreams.length }})
            </h3>
            <div class="space-y-2">
              <div
                v-for="workstream in content.workstreams"
                :key="workstream.id"
                class="p-2 rounded-lg"
                style="background: rgba(9, 9, 11, 0.5);"
              >
                <span class="text-sm font-medium" style="color: #FAFAFA;">{{ workstream.name }}</span>
                <div class="text-xs" style="color: #A1A1AA;">{{ workstream.type }}</div>
              </div>
            </div>
          </div>

          <!-- Releases -->
          <div v-if="content.releases && content.releases.length > 0" class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-green-400 rounded-full mr-3"></span>
              Related Releases ({{ content.releases.length }})
            </h3>
            <div class="space-y-2">
              <div
                v-for="release in content.releases"
                :key="release.id"
                class="p-2 rounded-lg"
                style="background: rgba(9, 9, 11, 0.5);"
              >
                <span class="text-sm font-medium" style="color: #FAFAFA;">{{ release.name }}</span>
                <div class="text-xs" style="color: #A1A1AA;">{{ release.version }}</div>
              </div>
            </div>
          </div>

          <!-- Metadata -->
          <div class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
            <h3 class="text-lg font-semibold mb-4 flex items-center" style="color: #FAFAFA;">
              <span class="w-2 h-2 bg-gray-400 rounded-full mr-3"></span>
              Metadata
            </h3>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span style="color: #A1A1AA;">Created:</span>
                <span style="color: #FAFAFA;">{{ formatFullDate(content.created_at) }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color: #A1A1AA;">Updated:</span>
                <span style="color: #FAFAFA;">{{ formatFullDate(content.updated_at) }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color: #A1A1AA;">Status:</span>
                <span style="color: #FAFAFA;">{{ formatStatus(content.status) }}</span>
              </div>
              <div class="flex justify-between">
                <span style="color: #A1A1AA;">Type:</span>
                <span style="color: #FAFAFA;">{{ formatType(content.type) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  content: Object
})

const goBack = () => {
  router.visit('/content')
}

const deleteContent = () => {
  if (confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
    router.delete(`/content/${props.content.id}`, {
      onSuccess: () => {
        router.visit('/content')
      }
    })
  }
}

const getTypeStyle = (type) => {
  const styles = {
    brain_dump: 'background: rgba(136, 77, 255, 0.1); color: #884DFF; border: 1px solid rgba(136, 77, 255, 0.3);',
    manual: 'background: rgba(34, 197, 94, 0.1); color: #22C55E; border: 1px solid rgba(34, 197, 94, 0.3);',
    file: 'background: rgba(59, 130, 246, 0.1); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3);'
  }
  return styles[type] || 'background: rgba(161, 161, 170, 0.1); color: #A1A1AA; border: 1px solid rgba(161, 161, 170, 0.3);'
}

const getStatusStyle = (status) => {
  const styles = {
    processed: 'background: rgba(34, 197, 94, 0.1); color: #22C55E; border: 1px solid rgba(34, 197, 94, 0.3);',
    processing: 'background: rgba(245, 158, 11, 0.1); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);',
    pending: 'background: rgba(161, 161, 170, 0.1); color: #A1A1AA; border: 1px solid rgba(161, 161, 170, 0.3);',
    failed: 'background: rgba(239, 68, 68, 0.1); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3);'
  }
  return styles[status] || 'background: rgba(161, 161, 170, 0.1); color: #A1A1AA; border: 1px solid rgba(161, 161, 170, 0.3);'
}

const getPriorityStyle = (priority) => {
  const styles = {
    high: 'background: rgba(239, 68, 68, 0.1); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3);',
    medium: 'background: rgba(245, 158, 11, 0.1); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);',
    low: 'background: rgba(34, 197, 94, 0.1); color: #22C55E; border: 1px solid rgba(34, 197, 94, 0.3);'
  }
  return styles[priority] || 'background: rgba(161, 161, 170, 0.1); color: #A1A1AA; border: 1px solid rgba(161, 161, 170, 0.3);'
}

const formatType = (type) => {
  const types = {
    brain_dump: 'Brain Dump',
    manual: 'Manual',
    file: 'File Upload'
  }
  return types[type] || type
}

const formatStatus = (status) => {
  const statuses = {
    processed: 'Processed',
    processing: 'Processing',
    pending: 'Pending',
    failed: 'Failed'
  }
  return statuses[status] || status
}

const formatPriority = (priority) => {
  const priorities = {
    high: 'High',
    medium: 'Medium',
    low: 'Low'
  }
  return priorities[priority] || priority
}

const formatDate = (dateString) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInDays = Math.floor((now - date) / (1000 * 60 * 60 * 24))

  if (diffInDays === 0) return 'Today'
  if (diffInDays === 1) return 'Yesterday'
  if (diffInDays < 7) return `${diffInDays} days ago`

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
  })
}

const formatFullDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>