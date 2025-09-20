<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto px-4 py-6 space-y-8" style="background: #090909; min-height: 100vh;">
      <!-- Header -->
      <div class="dashboard-card p-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
              Edit Stakeholder
            </h1>
            <p class="mt-2" style="color: #A1A1AA;">Update stakeholder profile and communication preferences</p>
          </div>
          <div class="flex space-x-4">
            <Link :href="route('stakeholders.show', stakeholder.id)" class="btn-secondary">
              View Profile
            </Link>
            <Link :href="route('stakeholders.index')" class="btn-secondary">
              Back to List
            </Link>
          </div>
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
          <div class="flex justify-between items-center pt-6 border-t" style="border-color: #27272A;">
            <button
              type="button"
              @click="deleteStakeholder"
              class="btn-danger"
              :disabled="processing"
            >
              Delete Stakeholder
            </button>

            <div class="flex space-x-4">
              <Link :href="route('stakeholders.show', stakeholder.id)" class="btn-secondary">
                Cancel
              </Link>
              <button type="submit" class="btn-primary" :disabled="processing">
                <span v-if="processing">Updating...</span>
                <span v-else>Update Stakeholder</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  stakeholder: Object
})

const form = useForm({
  name: props.stakeholder.name || '',
  email: props.stakeholder.email || '',
  title: props.stakeholder.title || '',
  company: props.stakeholder.company || '',
  department: props.stakeholder.department || '',
  phone: props.stakeholder.phone || '',
  slack_handle: props.stakeholder.slack_handle || '',
  teams_handle: props.stakeholder.teams_handle || '',
  preferred_communication_channel: props.stakeholder.preferred_communication_channel || '',
  communication_frequency: props.stakeholder.communication_frequency || 'as_needed',
  influence_level: props.stakeholder.influence_level || '',
  support_level: props.stakeholder.support_level || '',
  timezone: props.stakeholder.timezone || '',
  is_available: props.stakeholder.is_available ?? true,
  needs_follow_up: props.stakeholder.needs_follow_up ?? false,
  notes: props.stakeholder.notes || '',
  stakeholder_notes: props.stakeholder.stakeholder_notes || ''
})

const errors = ref({})
const processing = ref(false)

const submit = () => {
  processing.value = true
  errors.value = {}

  form.put(route('stakeholders.update', props.stakeholder.id), {
    onError: (formErrors) => {
      errors.value = formErrors
      processing.value = false
    },
    onFinish: () => {
      processing.value = false
    }
  })
}

const deleteStakeholder = () => {
  if (confirm('Are you sure you want to delete this stakeholder? This action cannot be undone.')) {
    router.delete(route('stakeholders.destroy', props.stakeholder.id))
  }
}
</script>