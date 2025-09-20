<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 py-6 space-y-8" style="background: #090909; min-height: 100vh;">
      <!-- Header -->
      <div class="p-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
              Add New Stakeholder
            </h1>
            <p class="mt-2" style="color: #A1A1AA;">Create a new stakeholder profile to track communications and engagement</p>
          </div>
          <button @click="$inertia.visit(route('stakeholders.index'))" class="px-4 py-2 rounded-lg font-medium my-6" style="background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                        Cancel
          </button>
        </div>
      </div>

      <!-- Form -->
      <div class="dashboard-card p-8">
        <form @submit.prevent="submit" class="space-y-8">
          <!-- Basic Information -->
          <div>
            <h2 class="text-xl font-semibold mb-6" style="color: #FAFAFA;">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="name" class="form-label">Name *</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="form-input"
                  :class="{ 'border-red-500': errors.name }"
                  placeholder="John Doe"
                  required
                />
                <div v-if="errors.name" class="form-error">{{ errors.name }}</div>
              </div>

              <div>
                <label for="email" class="form-label">Email *</label>
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  class="form-input"
                  :class="{ 'border-red-500': errors.email }"
                  placeholder="john@company.com"
                  required
                />
                <div v-if="errors.email" class="form-error">{{ errors.email }}</div>
              </div>

              <div>
                <label for="title" class="form-label">Job Title</label>
                <input
                  id="title"
                  v-model="form.title"
                  type="text"
                  class="form-input"
                  placeholder="Product Manager"
                />
              </div>

              <div>
                <label for="company" class="form-label">Company</label>
                <input
                  id="company"
                  v-model="form.company"
                  type="text"
                  class="form-input"
                  placeholder="Tech Corp"
                />
              </div>

              <div>
                <label for="department" class="form-label">Department</label>
                <input
                  id="department"
                  v-model="form.department"
                  type="text"
                  class="form-input"
                  placeholder="Engineering"
                />
              </div>

              <div>
                <label for="phone" class="form-label">Phone</label>
                <input
                  id="phone"
                  v-model="form.phone"
                  type="tel"
                  class="form-input"
                  placeholder="+1 (555) 123-4567"
                />
              </div>
            </div>
          </div>

          <!-- Communication Preferences -->
          <div>
            <h2 class="text-xl font-semibold mb-6" style="color: #FAFAFA;">Communication</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="slack_handle" class="form-label">Slack Handle</label>
                <input
                  id="slack_handle"
                  v-model="form.slack_handle"
                  type="text"
                  class="form-input"
                  placeholder="@john.doe"
                />
              </div>

              <div>
                <label for="teams_handle" class="form-label">Teams Handle</label>
                <input
                  id="teams_handle"
                  v-model="form.teams_handle"
                  type="text"
                  class="form-input"
                  placeholder="john.doe@company.com"
                />
              </div>

              <div>
                <label for="preferred_communication_channel" class="form-label">Preferred Channel</label>
                <select
                  id="preferred_communication_channel"
                  v-model="form.preferred_communication_channel"
                  class="form-select"
                >
                  <option value="">Select channel...</option>
                  <option value="email">Email</option>
                  <option value="slack">Slack</option>
                  <option value="teams">Teams</option>
                  <option value="phone">Phone</option>
                  <option value="linkedin">LinkedIn</option>
                  <option value="twitter">Twitter</option>
                </select>
              </div>

              <div>
                <label for="communication_frequency" class="form-label">Communication Frequency</label>
                <select
                  id="communication_frequency"
                  v-model="form.communication_frequency"
                  class="form-select"
                >
                  <option value="as_needed">As Needed</option>
                  <option value="immediate">Immediate</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                  <option value="monthly">Monthly</option>
                  <option value="quarterly">Quarterly</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Stakeholder Analysis -->
          <div>
            <h2 class="text-xl font-semibold mb-6" style="color: #FAFAFA;">Stakeholder Analysis</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="influence_level" class="form-label">Influence Level</label>
                <select
                  id="influence_level"
                  v-model="form.influence_level"
                  class="form-select"
                >
                  <option value="">Select level...</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>

              <div>
                <label for="support_level" class="form-label">Support Level</label>
                <select
                  id="support_level"
                  v-model="form.support_level"
                  class="form-select"
                >
                  <option value="">Select level...</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>

              <div>
                <label for="timezone" class="form-label">Timezone</label>
                <input
                  id="timezone"
                  v-model="form.timezone"
                  type="text"
                  class="form-input"
                  placeholder="America/New_York"
                />
              </div>

              <div class="flex items-center space-x-4">
                <div class="flex items-center">
                  <input
                    id="is_available"
                    v-model="form.is_available"
                    type="checkbox"
                    class="form-checkbox"
                  />
                  <label for="is_available" class="form-label ml-2 mb-0">Currently Available</label>
                </div>

                <div class="flex items-center">
                  <input
                    id="needs_follow_up"
                    v-model="form.needs_follow_up"
                    type="checkbox"
                    class="form-checkbox"
                  />
                  <label for="needs_follow_up" class="form-label ml-2 mb-0">Needs Follow-up</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div>
            <h2 class="text-xl font-semibold mb-6" style="color: #FAFAFA;">Notes</h2>
            <div class="space-y-6">
              <div>
                <label for="notes" class="form-label">General Notes</label>
                <textarea
                  id="notes"
                  v-model="form.notes"
                  rows="4"
                  class="form-textarea"
                  placeholder="Any additional information about this stakeholder..."
                ></textarea>
              </div>

              <div>
                <label for="stakeholder_notes" class="form-label">Stakeholder Management Notes</label>
                <textarea
                  id="stakeholder_notes"
                  v-model="form.stakeholder_notes"
                  rows="4"
                  class="form-textarea"
                  placeholder="Internal notes about managing this stakeholder relationship..."
                ></textarea>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end space-x-4 pt-6 border-t" style="border-color: #27272A;">
            <Link :href="route('stakeholders.index')" class="btn-secondary">
              Cancel
            </Link>
            <button type="submit" class="btn-primary" :disabled="processing">
              <span v-if="processing">Creating...</span>
              <span v-else>Create Stakeholder</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const form = useForm({
  name: '',
  email: '',
  title: '',
  company: '',
  department: '',
  phone: '',
  slack_handle: '',
  teams_handle: '',
  preferred_communication_channel: '',
  communication_frequency: 'as_needed',
  influence_level: '',
  support_level: '',
  timezone: '',
  is_available: true,
  needs_follow_up: false,
  notes: '',
  stakeholder_notes: ''
})

const errors = ref({})
const processing = ref(false)

const submit = () => {
  processing.value = true
  errors.value = {}

  form.post(route('stakeholders.store'), {
    onError: (formErrors) => {
      errors.value = formErrors
      processing.value = false
    },
    onSuccess: () => {
      processing.value = false
    }
  })
}
</script>