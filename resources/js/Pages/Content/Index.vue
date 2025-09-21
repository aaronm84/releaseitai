<template>
  <AppLayout>
    <Head title="Content History" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-8" style="background: #090909; min-height: 100vh;">
      <!-- Header Section -->
      <div class="p-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">Content History</h1>
            <p class="mt-2 text-lg" style="color: #A1A1AA;">View and manage your brain dumps and processed content</p>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Stats Cards -->
            <div class="grid grid-cols-3 gap-4">
              <div class="p-4 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
                <div class="text-2xl font-bold" style="color: #884DFF;">{{ stats.total_items }}</div>
                <div class="text-sm" style="color: #A1A1AA;">Total Items</div>
              </div>
              <div class="p-4 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
                <div class="text-2xl font-bold" style="color: #22C55E;">{{ stats.brain_dumps }}</div>
                <div class="text-sm" style="color: #A1A1AA;">Brain Dumps</div>
              </div>
              <div class="p-4 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
                <div class="text-2xl font-bold" style="color: #3B82F6;">{{ stats.processed_items }}</div>
                <div class="text-sm" style="color: #A1A1AA;">Processed</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters Section -->
      <div class="p-6 rounded-xl" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;">
        <div class="flex items-center space-x-4">
          <!-- Search -->
          <div class="flex-1">
            <input
              v-model="searchQuery"
              @input="debouncedSearch"
              type="text"
              placeholder="Search content..."
              class="w-full p-3 rounded-lg"
              style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA;"
            />
          </div>

          <!-- Type Filter -->
          <select
            v-model="typeFilter"
            @change="applyFilters"
            class="p-3 rounded-lg"
            style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA;"
          >
            <option value="">All Types</option>
            <option value="brain_dump">Brain Dumps</option>
            <option value="manual">Manual</option>
            <option value="file">File Upload</option>
          </select>

          <!-- Clear Filters -->
          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            class="px-4 py-3 rounded-lg transition-colors"
            style="background: rgba(136, 77, 255, 0.1); border: 1px solid rgba(136, 77, 255, 0.3); color: #884DFF;"
          >
            Clear Filters
          </button>
        </div>
      </div>

      <!-- Content List -->
      <div class="space-y-4">
        <div
          v-for="item in content.data"
          :key="item.id"
          class="p-6 rounded-xl cursor-pointer transition-all duration-300"
          style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A;"
          @mouseenter="$event.target.style.borderColor = 'rgba(136, 77, 255, 0.5)'"
          @mouseleave="$event.target.style.borderColor = '#27272A'"
          @click="viewContent(item.id)"
        >
          <div class="flex justify-between items-start mb-4">
            <div class="flex-1">
              <div class="flex items-center space-x-3 mb-2">
                <h3 class="text-lg font-semibold" style="color: #FAFAFA;">{{ item.title }}</h3>
                <span
                  class="px-2 py-1 rounded-full text-xs font-medium"
                  :style="getTypeStyle(item.type)"
                >
                  {{ formatType(item.type) }}
                </span>
                <span
                  class="px-2 py-1 rounded-full text-xs font-medium"
                  :style="getStatusStyle(item.status)"
                >
                  {{ formatStatus(item.status) }}
                </span>
              </div>
              <p class="text-sm mb-3" style="color: #A1A1AA;">{{ item.preview }}</p>

              <!-- Tags -->
              <div v-if="item.tags && item.tags.length > 0" class="flex flex-wrap gap-2 mb-3">
                <span
                  v-for="tag in item.tags"
                  :key="tag"
                  class="px-2 py-1 rounded-full text-xs"
                  style="background: rgba(59, 130, 246, 0.1); color: #3B82F6; border: 1px solid rgba(59, 130, 246, 0.3);"
                >
                  {{ tag }}
                </span>
              </div>
            </div>

            <div class="flex items-center space-x-4">
              <div v-if="item.action_items_count > 0" class="text-sm" style="color: #A1A1AA;">
                {{ item.action_items_count }} action items
              </div>
              <div class="text-sm" style="color: #A1A1AA;">
                {{ formatDate(item.created_at) }}
              </div>
              <button
                @click.stop="deleteContent(item.id)"
                class="p-2 rounded-lg hover:bg-red-500/20 transition-colors"
                style="color: #EF4444;"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="content.data.length === 0" class="text-center py-12">
          <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: #884DFF;">
            <span class="text-2xl">📝</span>
          </div>
          <h3 class="text-lg font-semibold mb-2" style="color: #FAFAFA;">No Content Found</h3>
          <p class="mb-6" style="color: #A1A1AA;">
            {{ hasActiveFilters ? 'Try adjusting your filters or search terms.' : 'Start by creating some brain dumps on the dashboard.' }}
          </p>
          <button
            @click="goToDashboard"
            class="px-6 py-3 rounded-xl font-medium transition-colors"
            style="background: #884DFF; color: #FAFAFA;"
          >
            Go to Dashboard
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="content.data.length > 0" class="flex justify-center">
        <div class="flex items-center space-x-2">
          <button
            v-for="page in paginationPages"
            :key="page"
            @click="goToPage(page)"
            class="px-3 py-2 rounded-lg transition-colors"
            :style="page === content.current_page
              ? 'background: #884DFF; color: #FAFAFA;'
              : 'background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #A1A1AA;'"
          >
            {{ page }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  content: Object,
  filters: Object,
  stats: Object
})

const searchQuery = ref(props.filters.search || '')
const typeFilter = ref(props.filters.type || '')

const hasActiveFilters = computed(() => {
  return searchQuery.value || typeFilter.value
})

const paginationPages = computed(() => {
  const pages = []
  const currentPage = props.content.current_page
  const lastPage = props.content.last_page

  for (let i = Math.max(1, currentPage - 2); i <= Math.min(lastPage, currentPage + 2); i++) {
    pages.push(i)
  }

  return pages
})

// Debounced search
let searchTimeout
const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500)
}

const applyFilters = () => {
  router.get('/content', {
    search: searchQuery.value,
    type: typeFilter.value
  }, {
    preserveState: true,
    preserveScroll: true
  })
}

const clearFilters = () => {
  searchQuery.value = ''
  typeFilter.value = ''
  router.get('/content')
}

const viewContent = (id) => {
  router.visit(`/content/${id}`)
}

const deleteContent = (id) => {
  if (confirm('Are you sure you want to delete this content? This action cannot be undone.')) {
    router.delete(`/content/${id}`, {
      onSuccess: () => {
        // Content will be refreshed automatically
      }
    })
  }
}

const goToDashboard = () => {
  router.visit('/dashboard')
}

const goToPage = (page) => {
  router.get('/content', {
    page,
    search: searchQuery.value,
    type: typeFilter.value
  }, {
    preserveState: true
  })
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
</script>