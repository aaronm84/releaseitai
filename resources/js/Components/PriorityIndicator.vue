<template>
  <div
    class="flex items-center p-5 rounded-xl transition-all duration-300"
    :class="[containerClasses, { 'cursor-pointer hover:opacity-80': clickable }]"
    :style="containerStyle"
    @click="handleClick"
  >
    <div class="w-3 h-3 rounded-full mr-4" :class="dotClasses"></div>
    <div class="flex-1">
      <h4 class="font-medium" style="color: #FAFAFA;">{{ title }}</h4>
      <p class="text-sm" style="color: #A1A1AA;">{{ description }}</p>
    </div>
    <div class="px-3 py-1 text-xs font-medium rounded-full" :style="badgeStyle">
      {{ priorityLabel }}
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
    required: true
  },
  priority: {
    type: String,
    default: 'normal', // 'high', 'medium', 'normal'
    validator: (value) => ['high', 'medium', 'normal'].includes(value)
  },
  clickable: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['click'])

const priorityConfig = computed(() => {
  const configs = {
    high: {
      label: 'Urgent',
      containerStyle: 'border: 1px solid rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);',
      dot: 'bg-red-400',
      badgeStyle: 'background: rgba(239, 68, 68, 0.2); color: #EF4444;'
    },
    medium: {
      label: 'Soon',
      containerStyle: 'border: 1px solid rgba(245, 158, 11, 0.3); background: rgba(245, 158, 11, 0.05);',
      dot: 'bg-yellow-400',
      badgeStyle: 'background: rgba(245, 158, 11, 0.2); color: #F59E0B;'
    },
    normal: {
      label: 'Normal',
      containerStyle: 'border: 1px solid #27272A; background: rgba(9, 9, 11, 0.5);',
      dot: 'bg-gray-400',
      badgeStyle: 'background: rgba(156, 163, 175, 0.2); color: #9CA3AF;'
    }
  }
  return configs[props.priority]
})

const priorityLabel = computed(() => priorityConfig.value.label)
const containerStyle = computed(() => priorityConfig.value.containerStyle)
const dotClasses = computed(() => priorityConfig.value.dot)
const badgeStyle = computed(() => priorityConfig.value.badgeStyle)

const handleClick = () => {
  if (props.clickable) {
    emit('click', {
      title: props.title,
      priority: props.priority
    })
  }
}
</script>