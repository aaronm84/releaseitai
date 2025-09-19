<template>
  <header class="fixed w-full z-50 shadow-lg" style="top: 0; left: 0; right: 0; background: rgba(9, 9, 11, 0.95); border-bottom: 1px solid #27272A; backdrop-filter: blur(12px); padding: 1rem 2rem;">
    <div class="w-full">
      <div class="flex justify-between items-center h-16">
        <div class="flex">
          <!-- Logo -->
          <div class="flex-shrink-0 flex items-center">
            <Link :href="route('dashboard')" class="text-xl font-bold" style="color: #884DFF;">
              {{ appName }}
            </Link>
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

        <!-- User Menu -->
        <div class="hidden sm:flex sm:items-center sm:ml-6">
          <div class="ml-3 relative">
            <Dropdown align="right" width="48">
              <template #trigger>
                <span class="inline-flex rounded-md">
                  <button
                    type="button"
                    class="inline-flex items-center px-4 py-3 border border-transparent text-sm leading-4 font-medium transition ease-in-out duration-150"
                    style="border-radius: 10px; color: #A1A1AA; background: rgba(9, 9, 11, 0.8);"
                    @mouseover="$event.target.style.color = '#FAFAFA'; $event.target.style.background = 'rgba(39, 39, 42, 0.8)'"
                    @mouseleave="$event.target.style.color = '#A1A1AA'; $event.target.style.background = 'rgba(9, 9, 11, 0.8)'"
                  >
                    {{ userName }}
                    <svg
                      class="ml-2 -mr-0.5 h-4 w-4"
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 20 20"
                      fill="currentColor"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                        clip-rule="evenodd"
                      />
                    </svg>
                  </button>
                </span>
              </template>

              <template #content>
                <DropdownLink
                  v-for="userMenuItem in userMenuItems"
                  :key="userMenuItem.name"
                  :href="userMenuItem.href"
                >
                  {{ userMenuItem.name }}
                </DropdownLink>
              </template>
            </Dropdown>
          </div>
        </div>

        <!-- Mobile menu button -->
        <div class="-mr-2 flex items-center sm:hidden">
          <button
            @click="toggleMobileMenu"
            class="inline-flex items-center justify-center p-3 transition duration-150 ease-in-out"
            style="border-radius: 10px; color: #A1A1AA;"
            @mouseover="$event.target.style.color = '#FAFAFA'; $event.target.style.background = 'rgba(39, 39, 42, 0.8)'"
            @mouseleave="$event.target.style.color = '#A1A1AA'; $event.target.style.background = 'transparent'"
          >
            <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
              <path
                :class="{
                  hidden: showingMobileMenu,
                  'inline-flex': !showingMobileMenu,
                }"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
              />
              <path
                :class="{
                  hidden: !showingMobileMenu,
                  'inline-flex': showingMobileMenu,
                }"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div
      :class="{ block: showingMobileMenu, hidden: !showingMobileMenu }"
      class="sm:hidden"
      style="background: rgba(9, 9, 11, 0.98); backdrop-filter: blur(16px);"
    >
      <div class="pt-2 pb-3 space-y-1">
        <ResponsiveNavLink
          v-for="navItem in navigationItems"
          :key="navItem.name"
          :href="navItem.href"
          :active="navItem.active"
        >
          {{ navItem.name }}
        </ResponsiveNavLink>
      </div>

      <div class="pt-4 pb-1" style="border-top: 1px solid #27272A;">
        <div class="px-4">
          <div class="font-medium text-base" style="color: #FAFAFA;">
            {{ userName }}
          </div>
          <div class="font-medium text-sm" style="color: #A1A1AA;">{{ userEmail }}</div>
        </div>

        <div class="mt-3 space-y-1">
          <ResponsiveNavLink
            v-for="userMenuItem in userMenuItems"
            :key="userMenuItem.name"
            :href="userMenuItem.href"
          >
            {{ userMenuItem.name }}
          </ResponsiveNavLink>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'

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

const toggleMobileMenu = () => {
  showingMobileMenu.value = !showingMobileMenu.value
  emit('toggle-mobile-menu', showingMobileMenu.value)
}
</script>