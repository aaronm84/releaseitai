<template>
  <div class="dashboard-card p-6">
    <div class="flex justify-between items-center mb-6">
      <div class="flex items-center">
        <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
        <div class="flex items-center space-x-3">
          <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="">
            <span class="text-lg">🧠</span>
          </div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Brain Dump</h2>
        </div>
      </div>
      <div class="flex items-center space-x-2">
        <span class="text-sm" style="color: #A1A1AA;">Press</span>
        <kbd class="px-3 py-1 rounded-lg text-xs font-semibold shadow-sm" style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #A1A1AA;">Ctrl+Enter</kbd>
        <span class="text-sm" style="color: #A1A1AA;">to process</span>
      </div>
    </div>
    <div class="space-y-4">
      <textarea
        v-model="quickAddContent"
        @input="handleQuickAddInput"
        @keydown="handleKeyDown"
        :placeholder="config.placeholder"
        class="w-full p-5 rounded-xl resize-none transition-all duration-300"
        :style="{
          background: isProcessing ? 'rgba(136, 77, 255, 0.05)' : 'rgba(9, 9, 11, 0.8)',
          borderColor: isProcessing ? '#884DFF' : '#27272A',
          borderRadius: '10px',
          color: '#FAFAFA',
          backdropFilter: 'blur(12px)',
          minHeight: '240px'
        }"
        placeholder-class="text-gray-400"
      ></textarea>

      <div v-if="isProcessing" class="flex items-center justify-center p-4 rounded-xl" style="background: rgba(136, 77, 255, 0.1); border: 1px solid rgba(136, 77, 255, 0.3);">
        <svg class="animate-spin -ml-1 mr-3 h-6 w-6" style="color: #884DFF;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <div class="flex items-center space-x-2">
          <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background: rgba(136, 77, 255, 0.2);">
            <span class="text-sm">🤖</span>
          </div>
          <span class="font-medium" style="color: rgba(136, 77, 255, 0.8);">Processing your input...</span>
        </div>
      </div>

      <div v-if="showSuccess" class="flex items-center justify-center p-4 rounded-xl" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
        <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3" style="background: #22C55E;">
          <svg class="w-5 h-5" style="color: #FAFAFA;" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
          </svg>
        </div>
        <div style="color: rgba(34, 197, 94, 0.8);">
          <div class="flex items-center space-x-2">
            <span class="text-lg">✨</span>
            <p class="font-semibold">Content processed successfully!</p>
          </div>
          <p class="text-sm mt-1" style="color: #22C55E;">Items extracted below. Clearing in 5 seconds...</p>
        </div>
      </div>

      <div class="flex justify-between items-center">
        <button
          @click="processContent"
          :disabled="quickAddContent.length < 10 || isProcessing"
          class="px-6 py-3 rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 font-medium"
          style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
        >
          {{ isProcessing ? 'Processing...' : 'Process Content' }}
        </button>

        <div v-if="quickAddContent.length > 0" class="text-sm" style="color: #A1A1AA;">
          {{ quickAddContent.length }} characters • Auto-saved
        </div>
      </div>

      <div v-if="extractedItems" class="space-y-3">
        <h3 class="font-medium" style="color: #FAFAFA;">Extracted Items:</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div v-if="extractedItems.tasks?.length" class="p-5 rounded-xl" style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3);">
            <h4 class="font-bold mb-3 flex items-center" style="color: rgba(59, 130, 246, 0.8);">
              <span class="w-2 h-2 rounded-full mr-2" style="background: #3B82F6;"></span>
              <div class="w-5 h-5 rounded-lg flex items-center justify-center mr-2" style="background: rgba(59, 130, 246, 0.2);">
                <span class="text-xs">📋</span>
              </div>
              Tasks ({{ extractedItems.tasks.length }})
            </h4>
            <ul class="space-y-1">
              <li v-for="task in extractedItems.tasks" :key="task.title" class="text-sm" style="color: rgba(59, 130, 246, 0.7);">
                • {{ task.title }}
              </li>
            </ul>
          </div>

          <div v-if="extractedItems.meetings?.length" class="p-5 rounded-xl" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3);">
            <h4 class="font-bold mb-3 flex items-center" style="color: rgba(34, 197, 94, 0.8);">
              <span class="w-2 h-2 rounded-full mr-2" style="background: #22C55E;"></span>
              <div class="w-5 h-5 rounded-lg flex items-center justify-center mr-2" style="background: rgba(34, 197, 94, 0.2);">
                <span class="text-xs">🤝</span>
              </div>
              Meetings ({{ extractedItems.meetings.length }})
            </h4>
            <ul class="space-y-1">
              <li v-for="meeting in extractedItems.meetings" :key="meeting.title" class="text-sm" style="color: rgba(34, 197, 94, 0.7);">
                • {{ meeting.title }}
              </li>
            </ul>
          </div>
        </div>

        <div class="flex space-x-3">
          <button
            @click="createReleaseFromItems"
            class="px-6 py-3 rounded-xl transition-all duration-300 font-medium"
            style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
          >
            Create Release
          </button>
          <button
            @click="addToExistingRelease"
            class="px-6 py-3 rounded-xl transition-all duration-300 font-medium"
            style="background: rgba(9, 9, 11, 0.8); color: #A1A1AA; border: 1px solid #27272A; border-radius: 10px;"
          >
            Add to Existing
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  config: {
    type: Object,
    default: () => ({
      enabled: true,
      placeholder: 'Paste meeting notes, emails, or ideas...',
      autoSave: true,
      processingDelay: 500,
    })
  }
})

const quickAddContent = ref('')
const isProcessing = ref(false)
const extractedItems = ref(null)
const showSuccess = ref(false)

const handleKeyDown = (event) => {
  // Ctrl+Enter or Cmd+Enter to process
  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
    event.preventDefault()
    processContent()
  }
}

const handleQuickAddInput = async () => {
  // Auto-save but don't auto-process for ADHD users (less distraction)
  autoSave()
}

const processContent = async () => {
  if (quickAddContent.value.length < 10) {
    alert('Please enter at least 10 characters to process.')
    return
  }

  isProcessing.value = true

  try {
    // Simulate AI processing for now since we don't have the backend endpoint yet
    await new Promise(resolve => setTimeout(resolve, 1000))

    // Mock extracted items based on content
    const content = quickAddContent.value.toLowerCase()
    const mockItems = {
      tasks: [],
      meetings: [],
      decisions: []
    }

    if (content.includes('meeting') || content.includes('call')) {
      mockItems.meetings.push({ title: 'Follow-up meeting', date: 'TBD' })
    }

    if (content.includes('task') || content.includes('todo') || content.includes('need to')) {
      mockItems.tasks.push({ title: 'Extracted task from brain dump', priority: 'medium' })
    }

    if (content.includes('decide') || content.includes('decision')) {
      mockItems.decisions.push({ item: 'Decision point identified' })
    }

    extractedItems.value = mockItems
    showSuccess.value = true

    // Show success message
    console.log('Content processed successfully!', mockItems)

    // Clear after 5 seconds (longer to see results)
    setTimeout(() => {
      console.log('Clearing brain dump...')
      quickAddContent.value = ''
      extractedItems.value = null
      showSuccess.value = false
      localStorage.removeItem('braindump_content')
    }, 5000)

  } catch (error) {
    console.error('Error processing quick add:', error)
    alert('Error processing content. Please try again.')
  } finally {
    isProcessing.value = false
  }
}

const createReleaseFromItems = () => {
  router.post('/quick-add/convert-to-release', {
    content: quickAddContent.value,
    extracted_items: extractedItems.value,
  })
}

const addToExistingRelease = () => {
  // Navigate to release selection
  router.visit('/quick-add/select-release', {
    data: {
      content: quickAddContent.value,
      extracted_items: extractedItems.value,
    }
  })
}

// Auto-save functionality for ADHD users
let autoSaveTimeout
const autoSave = () => {
  clearTimeout(autoSaveTimeout)
  autoSaveTimeout = setTimeout(() => {
    if (quickAddContent.value) {
      localStorage.setItem('braindump_content', quickAddContent.value)
    }
  }, 1000)
}

onMounted(() => {
  // Restore content from localStorage
  const saved = localStorage.getItem('braindump_content')
  if (saved) {
    quickAddContent.value = saved
  }

  // Set up auto-save
  const textarea = document.querySelector('textarea')
  if (textarea) {
    textarea.addEventListener('input', autoSave)
  }
})
</script>