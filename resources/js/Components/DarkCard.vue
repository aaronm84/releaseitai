<template>
  <div class="dark-card rounded-xl p-6" :style="{ border: `1px solid ${borderColor}` }">
    <div v-if="title || icon" class="flex items-center justify-between mb-6">
      <div class="flex items-center">
        <div v-if="showAccent" class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
        <div class="flex items-center space-x-3">
          <div v-if="icon" class="w-8 h-8 bg-purple-500/20 rounded-xl flex items-center justify-center">
            <span class="text-lg">{{ icon }}</span>
          </div>
          <h2 v-if="title" class="text-xl font-bold" style="color: #FAFAFA;">{{ title }}</h2>
        </div>
      </div>
      <slot name="actions" />
    </div>
    <div class="content">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: ''
  },
  showAccent: {
    type: Boolean,
    default: true
  },
  borderColor: {
    type: String,
    default: '#27272A'
  }
})
</script>

<style scoped>
.dark-card {
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(12px);
}
</style>