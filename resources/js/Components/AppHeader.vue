<template>
  <header class="fixed w-full z-50 shadow-lg" style="top: 0; left: 0; right: 0; background: rgba(9, 9, 11, 0.95); border-bottom: 1px solid #27272A; backdrop-filter: blur(12px); padding: 1rem 2rem;">
    <div class="w-full" style="">
      <div class="flex justify-between items-center h-16">
        <div class="flex">
          <!-- Logo -->
          <div class="flex-shrink-0 flex items-center content-center">
            <button
              @click="navigateToDashboard"
              class="focus:outline-none bg-transparent border-none p-0 cursor-pointer transition-all duration-300"
              style="
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
              "
            >
              <img
                src="/logo.svg"
                alt="ReleaseIt.ai"
                style="height: 42px; width: auto; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);"
                @mouseenter="$event.target.style.filter = 'drop-shadow(0 0 8px rgba(136, 77, 255, 0.6)) drop-shadow(0 0 16px rgba(136, 77, 255, 0.4))'"
                @mouseleave="$event.target.style.filter = 'none'"
              />
            </button>
          </div>

          <!-- Navigation Links -->
          <div class="hidden space-x-8 sm:ml-10 sm:flex items-center">
            <NavLink
              v-for="navItem in navigationItems"
              :key="navItem.name"
              :href="navItem.href"
              :active="navItem.active"
            >
              {{ navItem.name }}
            </NavLink>
          </div>
        </div>

        <!-- Menu button (both mobile and desktop) -->
        <div class="-mr-2 flex items-center">
          <MenuToggle
            v-model="showingMobileMenu"
            label-open="Open navigation menu"
            label-close="Close navigation menu"
            @toggle="toggleMobileMenu"
          />
        </div>
      </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <transition
      enter-active-class="duration-300 ease-out overflow-hidden"
      enter-from-class="opacity-0 scale-y-0 origin-top"
      enter-to-class="opacity-100 scale-y-100 origin-top"
      leave-active-class="duration-200 ease-in overflow-hidden"
      leave-from-class="opacity-100 scale-y-100 origin-top"
      leave-to-class="opacity-0 scale-y-0 origin-top"
    >
      <div
        v-if="showingMobileMenu"
        class="shadow-2xl"
        style="background: rgba(9, 9, 11, 0.98); backdrop-filter: blur(16px); transform-origin: top center;"
      >
      <div class="pt-2 pb-3 space-y-1">
        <ResponsiveNavLink
          v-for="(navItem, index) in navigationItems"
          :key="navItem.name"
          :href="navItem.href"
          :active="navItem.active"
          :style="{
            transitionDelay: showingMobileMenu ? `${index * 75}ms` : '0ms',
            transform: showingMobileMenu ? 'translateY(0)' : 'translateY(-8px)',
            opacity: showingMobileMenu ? '1' : '0'
          }"
          class="transition-all duration-250 ease-out"
        >
          {{ navItem.name }}
        </ResponsiveNavLink>
      </div>

      <div class="pt-4 pb-1" style="border-top: 1px solid #27272A;">
        <!-- <div class="px-4">
          <div class="font-medium text-base" style="color: #FAFAFA;">
            {{ userName }}
          </div>
          <div class="font-medium text-sm" style="color: #A1A1AA;">{{ userEmail }}</div>
        </div> -->

        <div class="mt-3 space-y-1">
          <ResponsiveNavLink
            v-for="(userMenuItem, index) in userMenuItems"
            :key="userMenuItem.name"
            :href="userMenuItem.href"
            :style="{
              transitionDelay: showingMobileMenu ? `${(navigationItems.length + index) * 75 + 150}ms` : '0ms',
              transform: showingMobileMenu ? 'translateY(0)' : 'translateY(-8px)',
              opacity: showingMobileMenu ? '1' : '0'
            }"
            class="transition-all duration-250 ease-out"
          >
            {{ userMenuItem.name }}
          </ResponsiveNavLink>
        </div>
      </div>
      </div>
    </transition>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import MenuToggle from '@/Components/MenuToggle.vue'
import {
  RocketLaunchIcon
} from '@heroicons/vue/24/solid'

const props = defineProps({
  appName: {
    type: String,
    default: 'ReleaseIt.ai'
  },
  navigationItems: {
    type: Array,
    default: () => []
  },
  userMenuItems: {
    type: Array,
    default: () => [
      { name: 'Profile', href: '/profile' },
      { name: 'Log Out', href: '/logout' }
    ]
  }
})

const emit = defineEmits(['toggle-mobile-menu'])

const page = usePage()
const showingMobileMenu = ref(false)

const userName = computed(() => page.props.auth?.user?.name || 'User')
const userEmail = computed(() => page.props.auth?.user?.email || 'user@example.com')

const toggleMobileMenu = (isOpen) => {
  // Handle both manual toggle calls and MenuToggle component calls
  if (typeof isOpen === 'boolean') {
    showingMobileMenu.value = isOpen
  } else {
    showingMobileMenu.value = !showingMobileMenu.value
  }
  emit('toggle-mobile-menu', showingMobileMenu.value)
}

const navigateToDashboard = () => {
  // Force a full page navigation to dashboard to avoid modal issues
  window.location.href = route('dashboard')
}
</script>