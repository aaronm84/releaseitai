<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
            <!-- Header -->
            <div class="dashboard-card p-8">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <Link
                            :href="route('stakeholders.index')"
                            class="transition-colors duration-200 hover:opacity-75"
                            style="color: #A1A1AA;"
                        >
                            ← Back to Stakeholders
                        </Link>
                        <div class="h-6 w-px" style="background: #27272A;"></div>
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background: #884DFF;">
                                <span class="text-lg font-medium" style="color: #FAFAFA;">
                                    {{ stakeholder.name.split(' ').map(n => n[0]).join('').toUpperCase() }}
                                </span>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold" style="color: #FAFAFA;">
                                    {{ stakeholder.name }}
                                </h1>
                                <p style="color: #A1A1AA;">{{ stakeholder.email }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <Link
                            :href="route('stakeholders.edit', stakeholder.id)"
                            class="px-4 py-2 font-medium transition-all duration-200 shadow-lg hover:opacity-90"
                            style="background: #F59E0B; color: #FAFAFA; border-radius: 10px;"
                        >
                            Edit
                        </Link>
                        <button
                            v-if="stakeholder.email"
                            @click="sendEmail(stakeholder.email)"
                            class="px-4 py-2 font-medium transition-all duration-200 shadow-lg hover:opacity-90"
                            style="background: #3B82F6; color: #FAFAFA; border-radius: 10px;"
                        >
                            Email
                        </button>
                        <button
                            v-if="stakeholder.slack_handle"
                            @click="openSlack(stakeholder.slack_handle)"
                            class="px-4 py-2 font-medium transition-all duration-200 shadow-lg hover:opacity-90"
                            style="background: #22C55E; color: #FAFAFA; border-radius: 10px;"
                        >
                            Slack
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contact & Profile Information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Profile -->
                <div class="lg:col-span-2 space-y-10">
                    <!-- Contact Information -->
                    <div class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #3B82F6, #6366F1);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Contact Information</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Title</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.title || 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Company</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.company || 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Department</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.department || 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Phone</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.phone || 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Slack Handle</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.slack_handle || 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Teams Handle</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.teams_handle || 'Not specified' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Communication Preferences -->
                    <div class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #22C55E, #10B981);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Communication Preferences</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Preferred Channel</label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" style="background: rgba(59, 130, 246, 0.2); color: #60A5FA; border: 1px solid rgba(59, 130, 246, 0.3);">
                                    {{ stakeholder.preferred_communication_channel?.toUpperCase() || 'EMAIL' }}
                                </span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Communication Frequency</label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" style="background: rgba(34, 197, 94, 0.2); color: #4ADE80; border: 1px solid rgba(34, 197, 94, 0.3);">
                                    {{ formatFrequency(stakeholder.communication_frequency) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Communications -->
                    <div v-if="recentCommunications && recentCommunications.length > 0" class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #884DFF, #6366F1);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Recent Communications</h2>
                        </div>
                        <div class="space-y-6">
                            <div
                                v-for="communication in recentCommunications"
                                :key="communication.id"
                                class="border rounded-lg p-4"
                                style="border-color: #27272A; background: rgba(9, 9, 11, 0.3);"
                            >
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-medium" style="color: #FAFAFA;">{{ communication.subject }}</h3>
                                    <span class="text-sm" style="color: #A1A1AA;">{{ formatDate(communication.communication_date) }}</span>
                                </div>
                                <p class="text-sm mb-2" style="color: #A1A1AA;">{{ communication.content }}</p>
                                <div class="flex items-center space-x-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background: rgba(107, 114, 128, 0.2); color: #9CA3AF; border: 1px solid rgba(107, 114, 128, 0.3);">
                                        {{ communication.channel }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                          :style="{
                                              background: communication.priority === 'urgent' ? 'rgba(239, 68, 68, 0.2)' :
                                                         communication.priority === 'high' ? 'rgba(245, 158, 11, 0.2)' :
                                                         communication.priority === 'normal' ? 'rgba(59, 130, 246, 0.2)' : 'rgba(107, 114, 128, 0.2)',
                                              color: communication.priority === 'urgent' ? '#F87171' :
                                                     communication.priority === 'high' ? '#FBBF24' :
                                                     communication.priority === 'normal' ? '#60A5FA' : '#9CA3AF',
                                              border: communication.priority === 'urgent' ? '1px solid rgba(239, 68, 68, 0.3)' :
                                                      communication.priority === 'high' ? '1px solid rgba(245, 158, 11, 0.3)' :
                                                      communication.priority === 'normal' ? '1px solid rgba(59, 130, 246, 0.3)' : '1px solid rgba(107, 114, 128, 0.3)'
                                          }">
                                        {{ communication.priority }} priority
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-10">
                    <!-- Stakeholder Profile -->
                    <div class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #F97316, #EF4444);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Profile</h2>
                        </div>
                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Influence Level</label>
                                <span v-if="stakeholder.influence_level"
                                      class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                      :style="{
                                          background: stakeholder.influence_level === 'high' ? 'rgba(239, 68, 68, 0.2)' :
                                                     stakeholder.influence_level === 'medium' ? 'rgba(245, 158, 11, 0.2)' : 'rgba(34, 197, 94, 0.2)',
                                          color: stakeholder.influence_level === 'high' ? '#F87171' :
                                                 stakeholder.influence_level === 'medium' ? '#FBBF24' : '#4ADE80',
                                          border: stakeholder.influence_level === 'high' ? '1px solid rgba(239, 68, 68, 0.3)' :
                                                  stakeholder.influence_level === 'medium' ? '1px solid rgba(245, 158, 11, 0.3)' : '1px solid rgba(34, 197, 94, 0.3)'
                                      }">
                                    {{ stakeholder.influence_level.toUpperCase() }}
                                </span>
                                <span v-else style="color: #A1A1AA;">Not specified</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Support Level</label>
                                <span v-if="stakeholder.support_level"
                                      class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                      :style="{
                                          background: stakeholder.support_level === 'high' ? 'rgba(34, 197, 94, 0.2)' :
                                                     stakeholder.support_level === 'medium' ? 'rgba(245, 158, 11, 0.2)' : 'rgba(239, 68, 68, 0.2)',
                                          color: stakeholder.support_level === 'high' ? '#4ADE80' :
                                                 stakeholder.support_level === 'medium' ? '#FBBF24' : '#F87171',
                                          border: stakeholder.support_level === 'high' ? '1px solid rgba(34, 197, 94, 0.3)' :
                                                  stakeholder.support_level === 'medium' ? '1px solid rgba(245, 158, 11, 0.3)' : '1px solid rgba(239, 68, 68, 0.3)'
                                      }">
                                    {{ stakeholder.support_level.toUpperCase() }}
                                </span>
                                <span v-else style="color: #A1A1AA;">Not specified</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Timezone</label>
                                <p style="color: #FAFAFA;">{{ stakeholder.timezone || 'Not specified' }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1" style="color: #A1A1AA;">Availability</label>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                      :style="{
                                          background: stakeholder.is_available ? 'rgba(34, 197, 94, 0.2)' : 'rgba(239, 68, 68, 0.2)',
                                          color: stakeholder.is_available ? '#4ADE80' : '#F87171',
                                          border: stakeholder.is_available ? '1px solid rgba(34, 197, 94, 0.3)' : '1px solid rgba(239, 68, 68, 0.3)'
                                      }">
                                    {{ stakeholder.is_available ? 'Available' : 'Unavailable' }}
                                </span>
                                <p v-if="!stakeholder.is_available && stakeholder.unavailable_until" class="text-sm mt-1" style="color: #A1A1AA;">
                                    Until {{ formatDate(stakeholder.unavailable_until) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Last Contact -->
                    <div class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #884DFF, #EC4899);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Last Contact</h2>
                        </div>
                        <div v-if="stakeholder.last_contact_at" class="space-y-2">
                            <p style="color: #FAFAFA;">{{ formatDate(stakeholder.last_contact_at) }}</p>
                            <p class="text-sm" style="color: #A1A1AA;">via {{ stakeholder.last_contact_channel }}</p>
                            <p class="text-xs" style="color: #A1A1AA;">{{ getDaysAgo(stakeholder.last_contact_at) }} days ago</p>
                        </div>
                        <div v-else style="color: #A1A1AA;">
                            No contact recorded
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="stakeholder.tags && stakeholder.tags.length > 0" class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #6366F1, #884DFF);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Tags</h2>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="tag in stakeholder.tags"
                                :key="tag"
                                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                style="background: rgba(99, 102, 241, 0.2); color: #A5B4FC; border: 1px solid rgba(99, 102, 241, 0.3);"
                            >
                                {{ tag }}
                            </span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="stakeholder.stakeholder_notes" class="dashboard-card p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #6B7280, #4B5563);"></div>
                            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Notes</h2>
                        </div>
                        <p class="whitespace-pre-line" style="color: #A1A1AA;">{{ stakeholder.stakeholder_notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Associated Releases -->
            <div v-if="stakeholderReleases && stakeholderReleases.length > 0" class="dashboard-card p-6">
                <div class="flex items-center mb-6">
                    <div class="w-1 h-8 rounded-full mr-4" style="background: linear-gradient(to bottom, #22C55E, #10B981);"></div>
                    <h2 class="text-xl font-bold" style="color: #FAFAFA;">Associated Releases</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="release in stakeholderReleases"
                        :key="release.id"
                        class="border rounded-lg p-4 transition-all duration-200 hover:opacity-90"
                        style="border-color: #27272A; background: rgba(9, 9, 11, 0.3);"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="font-medium" style="color: #FAFAFA;">{{ release.name }}</h3>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                                  :style="{
                                      background: release.status === 'planned' ? 'rgba(59, 130, 246, 0.2)' :
                                                 release.status === 'in_progress' ? 'rgba(245, 158, 11, 0.2)' :
                                                 release.status === 'completed' ? 'rgba(34, 197, 94, 0.2)' :
                                                 release.status === 'cancelled' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(107, 114, 128, 0.2)',
                                      color: release.status === 'planned' ? '#60A5FA' :
                                             release.status === 'in_progress' ? '#FBBF24' :
                                             release.status === 'completed' ? '#4ADE80' :
                                             release.status === 'cancelled' ? '#F87171' : '#9CA3AF',
                                      border: release.status === 'planned' ? '1px solid rgba(59, 130, 246, 0.3)' :
                                              release.status === 'in_progress' ? '1px solid rgba(245, 158, 11, 0.3)' :
                                              release.status === 'completed' ? '1px solid rgba(34, 197, 94, 0.3)' :
                                              release.status === 'cancelled' ? '1px solid rgba(239, 68, 68, 0.3)' : '1px solid rgba(107, 114, 128, 0.3)'
                                  }">
                                {{ release.status }}
                            </span>
                        </div>
                        <p class="text-sm mb-2" style="color: #A1A1AA;">{{ release.workstream_name }}</p>
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.2); color: #A78BFA; border: 1px solid rgba(136, 77, 255, 0.3);">
                                {{ release.role }}
                            </span>
                            <span class="text-xs" style="color: #A1A1AA;">{{ formatDate(release.target_date) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    stakeholder: Object,
    recentCommunications: Array,
    stakeholderReleases: Array,
    user: Object
})

const formatDate = (dateString) => {
    if (!dateString) return 'N/A'
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    })
}

const formatFrequency = (frequency) => {
    const frequencies = {
        'daily': 'Daily',
        'weekly': 'Weekly',
        'biweekly': 'Bi-weekly',
        'monthly': 'Monthly',
        'as_needed': 'As Needed'
    }
    return frequencies[frequency] || 'As Needed'
}

const getDaysAgo = (dateString) => {
    if (!dateString) return 0
    const date = new Date(dateString)
    const now = new Date()
    const diffTime = Math.abs(now - date)
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

const sendEmail = (email) => {
    window.open(`mailto:${email}`, '_blank')
}

const openSlack = (slackHandle) => {
    window.open(`slack://user?team=${slackHandle}`, '_blank')
}
</script>