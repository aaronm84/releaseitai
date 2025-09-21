<template>
  <AppLayout>
    <Head title="Profile Settings" />

    <div class="max-w-7xl mx-auto px-4 py-6" style="background: #090909; min-height: 100vh;">
      <!-- Header Section -->
      <div class="p-8">
        <h1 class="text-3xl font-bold" style="color: #FAFAFA;">Profile Settings</h1>
        <p class="mt-2 text-lg" style="color: #A1A1AA;">Manage your account settings and preferences</p>
      </div>

      <!-- Profile Form -->
      <div class="max-w-2xl">
        <div class="dashboard-card p-8">
          <form @submit.prevent="updateProfile">
            <div class="space-y-6">
              <!-- Basic Information -->
              <div>
                <h3 class="text-lg font-medium mb-4" style="color: #FAFAFA;">Basic Information</h3>

                <div class="grid grid-cols-1 gap-6">
                  <div>
                    <label class="block text-sm font-medium mb-2" style="color: #D1D5DB;">Name</label>
                    <input
                      v-model="form.name"
                      type="text"
                      required
                      class="w-full rounded-md border px-3 py-2 text-base transition-all duration-200 focus:outline-none"
                      style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3rem !important; padding: 1rem 1.5rem !important; border-color: #884DFF; focus:ring-2; focus:ring-purple-500/50;"
                    />
                    <div v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-2" style="color: #D1D5DB;">Email</label>
                    <input
                      v-model="form.email"
                      type="email"
                      required
                      class="w-full rounded-md border px-3 py-2 text-base transition-all duration-200 focus:outline-none"
                      style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3rem !important; padding: 1rem 1.5rem !important; border-color: #884DFF; focus:ring-2; focus:ring-purple-500/50;"
                    />
                    <div v-if="errors.email" class="text-red-500 text-sm mt-1">{{ errors.email }}</div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-2" style="color: #D1D5DB;">Timezone</label>
                    <select
                      v-model="form.timezone"
                      class="w-full rounded-md border px-3 py-2 text-base transition-all duration-200 focus:outline-none"
                      style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3rem !important; padding: 1rem 1.5rem !important; border-color: #884DFF; focus:ring-2; focus:ring-purple-500/50;"
                    >
                      <option value="">Select timezone...</option>
                      <option
                        v-for="(label, value) in timezones"
                        :key="value"
                        :value="value"
                      >
                        {{ label }}
                      </option>
                    </select>
                    <div v-if="errors.timezone" class="text-red-500 text-sm mt-1">{{ errors.timezone }}</div>
                    <p class="text-sm mt-1" style="color: #A1A1AA;">This affects when you see morning, afternoon, and evening briefs</p>
                  </div>
                </div>
              </div>

              <!-- Work Information -->
              <div>
                <h3 class="text-lg font-medium mb-4" style="color: #FAFAFA;">Work Information</h3>

                <div class="grid grid-cols-1 gap-6">
                  <div>
                    <label class="block text-sm font-medium mb-2" style="color: #D1D5DB;">Job Title</label>
                    <input
                      v-model="form.title"
                      type="text"
                      class="w-full rounded-md border px-3 py-2 text-base transition-all duration-200 focus:outline-none"
                      style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3rem !important; padding: 1rem 1.5rem !important; border-color: #884DFF; focus:ring-2; focus:ring-purple-500/50;"
                    />
                    <div v-if="errors.title" class="text-red-500 text-sm mt-1">{{ errors.title }}</div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-2" style="color: #D1D5DB;">Company</label>
                    <input
                      v-model="form.company"
                      type="text"
                      class="w-full rounded-md border px-3 py-2 text-base transition-all duration-200 focus:outline-none"
                      style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3rem !important; padding: 1rem 1.5rem !important; border-color: #884DFF; focus:ring-2; focus:ring-purple-500/50;"
                    />
                    <div v-if="errors.company" class="text-red-500 text-sm mt-1">{{ errors.company }}</div>
                  </div>

                  <div>
                    <label class="block text-sm font-medium mb-2" style="color: #D1D5DB;">Department</label>
                    <input
                      v-model="form.department"
                      type="text"
                      class="w-full rounded-md border px-3 py-2 text-base transition-all duration-200 focus:outline-none"
                      style="background: rgba(9, 9, 11, 0.8); border: 1px solid #27272A; color: #FAFAFA; height: 3rem !important; padding: 1rem 1.5rem !important; border-color: #884DFF; focus:ring-2; focus:ring-purple-500/50;"
                    />
                    <div v-if="errors.department" class="text-red-500 text-sm mt-1">{{ errors.department }}</div>
                  </div>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex justify-end space-x-4 pt-6">
                <button
                  type="button"
                  @click="$inertia.visit('/dashboard')"
                  class="px-6 py-3 rounded-xl font-medium transition-all duration-300"
                  style="background: #27272A; color: #FAFAFA; border: 1px solid #3F3F46;"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  :disabled="processing"
                  class="px-6 py-3 rounded-xl font-medium transition-all duration-300"
                  style="background: #884DFF; color: #FAFAFA;"
                  :style="{ opacity: processing ? 0.6 : 1 }"
                >
                  {{ processing ? 'Saving...' : 'Save Changes' }}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  user: Object,
  timezones: Object,
  errors: {
    type: Object,
    default: () => ({})
  }
})

const processing = ref(false)

const form = reactive({
  name: props.user.name || '',
  email: props.user.email || '',
  timezone: props.user.timezone || '',
  title: props.user.title || '',
  company: props.user.company || '',
  department: props.user.department || ''
})

const updateProfile = () => {
  processing.value = true

  router.patch('/profile', form, {
    onFinish: () => {
      processing.value = false
    },
    onSuccess: () => {
      // Success message will be handled by the backend redirect
    }
  })
}
</script>

<style scoped>
.dashboard-card {
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(12px);
  border: 1px solid #27272A;
  border-radius: 12px;
}
</style>