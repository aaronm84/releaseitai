<template>
  <div class="dark-morning-brief rounded-xl p-6" style="border: 1px solid #27272A;">
    <div class="flex items-center mb-6">
      <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
      <div class="flex items-center space-x-3">
        <div class="w-8 h-8 bg-purple-500/20 rounded-xl flex items-center justify-center">
          <span class="text-lg">🌅</span>
        </div>
        <h2 class="text-xl font-bold" style="color: #FAFAFA;">{{ title }}</h2>
      </div>
    </div>
    <div class="prose prose-sm max-w-none">
      <p style="color: #D1D5DB;">{{ summary }}</p>
      <div v-if="highlights && highlights.length > 0" class="mt-4">
        <h4 class="font-medium" style="color: #FAFAFA;">Key Highlights:</h4>
        <ul class="mt-2 list-disc list-inside space-y-1">
          <li v-for="highlight in highlights" :key="highlight" style="color: #D1D5DB;">
            {{ highlight }}
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: 'Morning Brief'
  },
  summary: {
    type: String,
    required: true
  },
  highlights: {
    type: Array,
    default: () => []
  }
})
</script>

<style scoped>
.dark-morning-brief {
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(12px);
}
</style>