<template>
  <div
    class="p-5 rounded-xl border"
    :class="cardClasses"
  >
    <div class="flex items-center justify-between mb-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center" :class="iconBgClasses">
        <span class="text-lg">{{ icon }}</span>
      </div>
      <span class="text-2xl font-bold" :class="valueClasses">{{ formattedValue }}</span>
    </div>
    <h3 class="font-medium" :class="titleClasses">{{ title }}</h3>
    <p v-if="description" class="text-xs mt-1" :class="descriptionClasses">{{ description }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: [String, Number],
    required: true
  },
  icon: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  variant: {
    type: String,
    default: 'blue', // 'blue', 'green', 'yellow', 'red', 'purple'
    validator: (value) => ['blue', 'green', 'yellow', 'red', 'purple'].includes(value)
  },
  suffix: {
    type: String,
    default: ''
  }
})

const formattedValue = computed(() => {
  return `${props.value}${props.suffix}`
})

const cardClasses = computed(() => {
  const variants = {
    blue: 'bg-gradient-to-br from-blue-500/10 to-blue-600/10 border-blue-500/30',
    green: 'bg-gradient-to-br from-green-500/10 to-green-600/10 border-green-500/30',
    yellow: 'bg-gradient-to-br from-yellow-500/10 to-yellow-600/10 border-yellow-500/30',
    red: 'bg-gradient-to-br from-red-500/10 to-red-600/10 border-red-500/30',
    purple: 'bg-gradient-to-br from-purple-500/10 to-purple-600/10 border-purple-500/30'
  }
  return variants[props.variant]
})

const iconBgClasses = computed(() => {
  const variants = {
    blue: 'bg-blue-500/20',
    green: 'bg-green-500/20',
    yellow: 'bg-yellow-500/20',
    red: 'bg-red-500/20',
    purple: 'bg-purple-500/20'
  }
  return variants[props.variant]
})

const valueClasses = computed(() => {
  const variants = {
    blue: 'text-blue-300',
    green: 'text-green-300',
    yellow: 'text-yellow-300',
    red: 'text-red-300',
    purple: 'text-purple-300'
  }
  return variants[props.variant]
})

const titleClasses = computed(() => {
  const variants = {
    blue: 'text-blue-200',
    green: 'text-green-200',
    yellow: 'text-yellow-200',
    red: 'text-red-200',
    purple: 'text-purple-200'
  }
  return variants[props.variant]
})

const descriptionClasses = computed(() => {
  const variants = {
    blue: 'text-blue-300/70',
    green: 'text-green-300/70',
    yellow: 'text-yellow-300/70',
    red: 'text-red-300/70',
    purple: 'text-purple-300/70'
  }
  return variants[props.variant]
})
</script>