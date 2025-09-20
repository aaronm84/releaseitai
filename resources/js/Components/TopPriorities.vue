<template>
  <div class="dashboard-card p-6 ">
    <div class="flex items-center mb-6">
      <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
      <h2 class="text-xl font-bold" style="color: #FAFAFA;">Top 3 Priorities</h2>
      <div class="ml-auto px-4 py-2 text-sm font-medium rounded-full" style="background: rgba(136, 77, 255, 0.2); color: rgba(136, 77, 255, 0.8); border: 1px solid rgba(136, 77, 255, 0.3);">
        Focus Mode
      </div>
    </div>
    <div v-if="priorities.length > 0" class="space-y-3">
      <div
        v-for="(priority, index) in priorities"
        :key="priority.id"
        class="flex items-center p-5 rounded-xl transition-all duration-300"
        :style="{
          border: priority.due_in_days <= 1 ? '1px solid rgba(239, 68, 68, 0.3)' : priority.due_in_days <= 3 && priority.due_in_days > 1 ? '1px solid rgba(245, 158, 11, 0.3)' : '1px solid #27272A',
          background: priority.due_in_days <= 1 ? 'rgba(239, 68, 68, 0.05)' : priority.due_in_days <= 3 && priority.due_in_days > 1 ? 'rgba(245, 158, 11, 0.05)' : 'rgba(9, 9, 11, 0.5)',
          backdropFilter: 'blur(12px)'
        }"
      >
        <div class="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center text-sm font-bold shadow-lg" style="background: #884DFF; color: #FAFAFA;">
          {{ index + 1 }}
        </div>
        <div class="ml-5 flex-1">
          <h3 class="font-semibold text-lg" style="color: #FAFAFA;">{{ priority.name }}</h3>
          <div class="flex items-center mt-1">
            <div class="w-4 h-4 rounded-lg flex items-center justify-center mr-2" style="background: rgba(136, 77, 255, 0.2);">
              <span class="text-xs">📁</span>
            </div>
            <p class="text-sm font-medium" style="color: #A1A1AA;">{{ priority.workstream_name }}</p>
          </div>
        </div>
        <div class="text-right">
          <div class="mb-2 px-3 py-1 rounded-full text-xs font-medium" :style="{
            background: priority.due_in_days <= 1 ? 'rgba(239, 68, 68, 0.2)' : priority.due_in_days <= 3 && priority.due_in_days > 1 ? 'rgba(245, 158, 11, 0.2)' : 'rgba(136, 77, 255, 0.2)',
            color: priority.due_in_days <= 1 ? '#EF4444' : priority.due_in_days <= 3 && priority.due_in_days > 1 ? '#F59E0B' : '#884DFF',
            border: priority.due_in_days <= 1 ? '1px solid rgba(239, 68, 68, 0.3)' : priority.due_in_days <= 3 && priority.due_in_days > 1 ? '1px solid rgba(245, 158, 11, 0.3)' : '1px solid rgba(136, 77, 255, 0.3)'
          }">
            {{ priority.due_in_days === 0 ? '🔥 Due today' : priority.due_in_days === 1 ? '⚡ Due tomorrow' : `📅 ${priority.due_in_days} days` }}
          </div>
          <div class="text-xs font-medium" style="color: #A1A1AA;">{{ priority.status }}</div>
        </div>
      </div>
    </div>
    <div v-else class="text-center py-12">
      <div class="w-16 h-16 mx-auto mb-4 bg-gradient-to-br from-green-400 to-green-500 rounded-full flex items-center justify-center">
        <span class="text-2xl">🎉</span>
      </div>
      <p class="text-lg font-medium" style="color: #A1A1AA;">No urgent priorities right now.</p>
      <p class="font-semibold mt-1" style="color: #22C55E;">Great job staying on top of things!</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  priorities: {
    type: Array,
    default: () => []
  }
})
</script>