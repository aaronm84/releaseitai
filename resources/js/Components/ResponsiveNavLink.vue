<template>
  <Link
    :href="href"
    :method="method"
    :as="as"
    :class="[
      'block w-full pl-4 pr-6 py-3 border-l-4 text-left text-base font-medium transition duration-150 ease-in-out focus:outline-none',
      active
        ? 'border-purple-400 focus:border-purple-300'
        : 'border-transparent hover:border-purple-300 focus:border-purple-300'
    ]"
    :style="{
      color: active ? '#884DFF' : '#A1A1AA',
      background: active ? 'rgba(136, 77, 255, 0.1)' : 'transparent',
      borderLeftColor: active ? '#884DFF' : 'transparent'
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
  method: String,
  as: String,
  active: Boolean,
});

const handleMouseOver = (event) => {
  if (!props.active) {
    event.target.style.color = '#FAFAFA';
    event.target.style.background = 'rgba(39, 39, 42, 0.8)';
  }
};

const handleMouseLeave = (event) => {
  if (!props.active) {
    event.target.style.color = '#A1A1AA';
    event.target.style.background = 'transparent';
  }
};
</script>