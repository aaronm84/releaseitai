<template>
  <div
    class="workstream-card rounded-lg p-4 cursor-pointer"
    :class="cardClasses"
    @click="handleClick"
  >
    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <span v-if="icon" class="text-lg mr-3">{{ icon }}</span>
        <div>
          <h4 class="font-semibold" :class="titleClasses">{{ title }}</h4>
          <p v-if="description" class="text-sm" :class="descriptionClasses">{{ description }}</p>
        </div>
      </div>
      <div v-if="status" class="px-3 py-1 rounded-full text-sm font-medium" :class="statusClasses">
        {{ status }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: ''
  },
  status: {
    type: String,
    default: ''
  },
  variant: {
    type: String,
    default: 'blue', // 'blue', 'green', 'purple', 'orange'
    validator: (value) => ['blue', 'green', 'purple', 'orange'].includes(value)
  },
  clickable: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['click'])

const cardClasses = computed(() => {
  const base = 'transition-all duration-300'
  const variants = {
    blue: 'bg-gradient-to-r from-blue-50 to-blue-100 border-l-4 border-blue-500',
    green: 'bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500',
    purple: 'bg-gradient-to-r from-purple-50 to-purple-100 border-l-4 border-purple-500',
    orange: 'bg-gradient-to-r from-orange-50 to-orange-100 border-l-4 border-orange-500'
  }

  return `${base} ${variants[props.variant]} ${props.clickable ? 'hover:shadow-md hover:-translate-y-1' : ''}`
})

const titleClasses = computed(() => {
  const variants = {
    blue: 'text-blue-900',
    green: 'text-green-900',
    purple: 'text-purple-900',
    orange: 'text-orange-900'
  }
  return variants[props.variant]
})

const descriptionClasses = computed(() => {
  const variants = {
    blue: 'text-blue-700',
    green: 'text-green-700',
    purple: 'text-purple-700',
    orange: 'text-orange-700'
  }
  return variants[props.variant]
})

const statusClasses = computed(() => {
  const variants = {
    blue: 'bg-blue-200 text-blue-800',
    green: 'bg-green-200 text-green-800',
    purple: 'bg-purple-200 text-purple-800',
    orange: 'bg-orange-200 text-orange-800'
  }
  return variants[props.variant]
})

const handleClick = () => {
  if (props.clickable) {
    emit('click')
  }
}
</script>

<style scoped>
.workstream-card {
  transition: all 0.3s ease;
  backdrop-filter: blur(8px);
}

.workstream-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}
</style>