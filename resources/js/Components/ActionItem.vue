<template>
  <div
    class="flex items-center justify-between rounded-xl border transition-colors duration-200"
    :class="[containerClasses, variant === 'urgent' ? 'p-4' : 'p-3']"
  >
    <div class="flex items-center space-x-3">
      <div
        class="rounded-full flex items-center justify-center"
        :class="[avatarClasses, variant === 'urgent' ? 'w-8 h-8' : 'w-6 h-6']"
      >
        <span
          class="font-medium"
          :class="[avatarTextClasses, variant === 'urgent' ? 'text-sm' : 'text-xs']"
        >
          {{ initials }}
        </span>
      </div>
      <div>
        <p class="font-medium" :class="nameClasses">{{ name }}</p>
        <p class="text-xs" :class="subtitleClasses">{{ subtitle }}</p>
      </div>
    </div>

    <div v-if="variant === 'urgent'">
      <button
        class="px-3 py-1 rounded-lg text-sm font-medium transition-colors duration-200"
        :class="buttonClasses"
        @click="handleAction"
      >
        {{ actionText }}
      </button>
    </div>
    <div v-else>
      <span class="text-xs" :class="statusClasses">{{ statusText }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  name: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    required: true
  },
  variant: {
    type: String,
    default: 'recent', // 'urgent', 'recent'
    validator: (value) => ['urgent', 'recent'].includes(value)
  },
  actionText: {
    type: String,
    default: 'Contact'
  },
  statusText: {
    type: String,
    default: '✓ Recent'
  }
})

const emit = defineEmits(['action'])

const initials = computed(() => {
  return props.name
    .split(' ')
    .map(word => word.charAt(0).toUpperCase())
    .join('')
    .substring(0, 2)
})

const containerClasses = computed(() => {
  return props.variant === 'urgent'
    ? 'bg-red-500/10 border-red-500/30'
    : 'bg-green-500/10 border-green-500/30'
})

const avatarClasses = computed(() => {
  return props.variant === 'urgent'
    ? 'bg-red-500/20'
    : 'bg-green-500/20'
})

const avatarTextClasses = computed(() => {
  return props.variant === 'urgent'
    ? 'text-red-300'
    : 'text-green-300'
})

const nameClasses = computed(() => {
  return props.variant === 'urgent'
    ? 'text-red-200'
    : 'text-green-200'
})

const subtitleClasses = computed(() => {
  return props.variant === 'urgent'
    ? 'text-red-300/70'
    : 'text-green-300/70'
})

const buttonClasses = computed(() => {
  return 'bg-red-500/20 text-red-300 hover:bg-red-500/30'
})

const statusClasses = computed(() => {
  return 'text-green-300'
})

const handleAction = () => {
  emit('action', {
    name: props.name,
    variant: props.variant
  })
}
</script>