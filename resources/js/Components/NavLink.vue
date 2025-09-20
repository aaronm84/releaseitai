<template>
  <Link
    :href="href"
    :class="[
      'inline-flex items-center px-4 py-3 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none',
      active
        ? 'border-purple-500 focus:border-purple-400'
        : 'border-transparent hover:border-purple-300 focus:border-purple-300'
    ]"
    :style="{
      color: active ? '#884DFF' : '#A1A1AA',
      borderBottomColor: active ? '#884DFF' : 'transparent'
    }"
    @mouseover="handleMouseOver"
    @mouseleave="handleMouseLeave"
  >
    <slot />
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  href: String,
  active: Boolean,
});

const handleMouseOver = (event) => {
  if (!props.active) {
    event.target.style.color = '#FAFAFA';
  }
};

const handleMouseLeave = (event) => {
  if (!props.active) {
    event.target.style.color = '#A1A1AA';
  }
};
</script>