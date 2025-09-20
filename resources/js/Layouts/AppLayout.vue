<template>
  <div class="min-h-screen" style="background: #090909; margin: 0; padding: 0;">
    <!-- Header -->
    <AppHeader :navigation-items="navigationItems" />

    <!-- Flash Messages -->
    <FlashMessages />

    <!-- Page Content -->
    <main style="padding-top: 7rem; padding-left: 2rem; padding-right: 2rem; padding-bottom: 2rem;">
      <div class="space-y-8">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppHeader from '@/Components/AppHeader.vue';
import FlashMessages from '@/Components/FlashMessages.vue';

const page = usePage();

const navigationItems = computed(() => [
  {
    name: 'Dashboard',
    href: route('dashboard'),
    active: route().current('dashboard')
  },
  // {
  //   name: 'Design System',
  //   href: route('design-system'),
  //   active: route().current('design-system')
  // }
]);
</script>