<template>
  <input
    :type="type"
    :class="inputClasses"
    :value="modelValue"
    @input="$emit('update:modelValue', $event.target.value)"
    v-bind="$attrs"
  />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'text'
  },
  modelValue: {
    type: [String, Number],
    default: ''
  },
  class: {
    type: String,
    default: ''
  },
  error: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const inputClasses = computed(() => {
  const baseClasses = [
    'flex',
    'h-10',
    'w-full',
    'rounded-md',
    'border',
    'px-3',
    'py-2',
    'text-base',
    'transition-all',
    'duration-200',
    'file:border-0',
    'file:bg-transparent',
    'file:text-sm',
    'file:font-medium',
    'placeholder:text-gray-400',
    'focus:outline-none',
    'disabled:cursor-not-allowed',
    'disabled:opacity-50',
    'md:text-sm'
  ]

  // Apply your existing dark theme colors
  baseClasses.push(
    'bg-gray-900/80',
    'text-white',
    'backdrop-blur-sm'
  )

  // Conditional border and focus styles based on error state
  if (props.error) {
    baseClasses.push(
      'border-red-500',
      'focus:border-red-500',
      'focus:ring-2',
      'focus:ring-red-500/50'
    )
  } else {
    baseClasses.push(
      'border-gray-600',
      'focus:border-purple-500',
      'focus:ring-2',
      'focus:ring-purple-500/50'
    )
  }

  return `${baseClasses.join(' ')} ${props.class}`
})
</script>

<script>
export default {
  inheritAttrs: false
}
</script>