<template>
    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
            <!-- Header -->
            <div class=" p-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
                            Stakeholders
                        </h1>
                        <p class="mt-2" style="color: #A1A1AA;">Manage your relationships and stay on top of communications</p>
                    </div>
                    <button @click="$inertia.visit(route('stakeholders.create'))" class="purple-gradient-button px-4 py-2 text-white rounded-lg font-medium">
                        Add Stakeholder
                    </button>
                </div>
            </div>

            <!-- Metrics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="dashboard-card p-6" style="background: rgba(136, 77, 255, 0.1); border-color: rgba(136, 77, 255, 0.3);">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #884DFF;">
                            <UsersIcon class="h-6 w-6" style="color: #FAFAFA;" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium" style="color: #A1A1AA;">Total Stakeholders</p>
                            <p class="text-2xl font-bold" style="color: #FAFAFA;">{{ metrics.total_stakeholders }}</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card p-6" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3);">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #F59E0B;">
                            <ClockIcon class="h-6 w-6" style="color: #FAFAFA;" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium" style="color: #A1A1AA;">Need Follow-up</p>
                            <p class="text-2xl font-bold" style="color: #FAFAFA;">{{ metrics.needs_follow_up }}</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card p-6" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3);">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #EF4444;">
                            <ChartBarIcon class="h-6 w-6" style="color: #FAFAFA;" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium" style="color: #A1A1AA;">High Influence</p>
                            <p class="text-2xl font-bold" style="color: #FAFAFA;">{{ metrics.by_influence?.high || 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card p-6" style="background: rgba(34, 197, 94, 0.1); border-color: rgba(34, 197, 94, 0.3);">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #22C55E;">
                            <HeartIcon class="h-6 w-6" style="color: #FAFAFA;" />
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium" style="color: #A1A1AA;">High Support</p>
                            <p class="text-2xl font-bold" style="color: #FAFAFA;">{{ metrics.by_support?.high || 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="dashboard-card p-6">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[300px]">
                        <div class="relative">
                            <MagnifyingGlassIcon class="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5" style="color: #A1A1AA;" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search stakeholders..."
                                class="w-full pl-10 pr-4 py-3 border transition-all duration-200"
                                style="background: rgba(9, 9, 11, 0.8); border-color: #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
                                @input="handleSearch"
                            />
                        </div>
                    </div>

                    <select
                        v-model="selectedInfluence"
                        @change="handleFilter"
                        class="px-4 py-3 border transition-all duration-200"
                        style="background: rgba(9, 9, 11, 0.8); border-color: #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
                    >
                        <option value="">All Influence Levels</option>
                        <option value="high">High Influence</option>
                        <option value="medium">Medium Influence</option>
                        <option value="low">Low Influence</option>
                    </select>

                    <select
                        v-model="selectedSupport"
                        @change="handleFilter"
                        class="px-4 py-3 border transition-all duration-200"
                        style="background: rgba(9, 9, 11, 0.8); border-color: #27272A; border-radius: 10px; color: #FAFAFA; backdrop-filter: blur(12px);"
                    >
                        <option value="">All Support Levels</option>
                        <option value="high">High Support</option>
                        <option value="medium">Medium Support</option>
                        <option value="low">Low Support</option>
                    </select>

                    <button
                        @click="toggleFollowUpFilter"
                        class="px-4 py-3 text-sm font-medium transition-all duration-200"
                        :style="{
                            borderRadius: '10px',
                            background: showFollowUpOnly ? '#F59E0B' : 'rgba(255, 255, 255, 0.1)',
                            border: showFollowUpOnly ? 'none' : '1px solid rgba(255, 255, 255, 0.2)',
                            color: '#FAFAFA',
                            backdropFilter: 'blur(12px)'
                        }"
                    >
                        {{ showFollowUpOnly ? 'Show All' : 'Need Follow-up' }}
                    </button>
                </div>
            </div>

            <!-- Stakeholders List -->
            <div class="dashboard-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y" style="border-color: #27272A;">
                        <thead style="background: rgba(9, 9, 11, 0.8); backdrop-filter: blur(12px);">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider" style="color: #A1A1AA;">
                                    Stakeholder
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider" style="color: #A1A1AA;">
                                    Company/Role
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider" style="color: #A1A1AA;">
                                    Influence/Support
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider" style="color: #A1A1AA;">
                                    Last Contact
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider" style="color: #A1A1AA;">
                                    Status
                                </th>
                                <th class="px-6 py-4 text-left text-xs font-medium uppercase tracking-wider" style="color: #A1A1AA;">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y" style="background: #090909; border-color: #27272A;">
                            <tr
                                v-for="stakeholder in stakeholders"
                                :key="stakeholder.id"
                                class="transition-colors duration-200 hover:bg-white/5"
                                style="color: #FAFAFA;"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full flex items-center justify-center" style="background: #884DFF;">
                                                <span class="text-sm font-medium" style="color: #FAFAFA;">
                                                    {{ stakeholder.name.split(' ').map(n => n[0]).join('').toUpperCase() }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium" style="color: #FAFAFA;">{{ stakeholder.name }}</div>
                                            <div class="text-sm" style="color: #A1A1AA;">{{ stakeholder.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm" style="color: #FAFAFA;">{{ stakeholder.company || '-' }}</div>
                                    <div class="text-sm" style="color: #A1A1AA;">{{ stakeholder.title || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex space-x-2">
                                        <span
                                            v-if="stakeholder.influence_level"
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                stakeholder.influence_level === 'high'
                                                    ? 'bg-red-100 text-red-800'
                                                    : stakeholder.influence_level === 'medium'
                                                    ? 'bg-yellow-100 text-yellow-800'
                                                    : 'bg-green-100 text-green-800'
                                            ]"
                                        >
                                            {{ stakeholder.influence_level.toUpperCase() }} inf.
                                        </span>
                                        <span
                                            v-if="stakeholder.support_level"
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                stakeholder.support_level === 'high'
                                                    ? 'bg-green-100 text-green-800'
                                                    : stakeholder.support_level === 'medium'
                                                    ? 'bg-yellow-100 text-yellow-800'
                                                    : 'bg-red-100 text-red-800'
                                            ]"
                                        >
                                            {{ stakeholder.support_level.toUpperCase() }} sup.
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="stakeholder.last_contact_at" class="text-sm" style="color: #FAFAFA;">
                                        {{ formatDate(stakeholder.last_contact_at) }}
                                        <div class="text-xs" style="color: #A1A1AA;">
                                            {{ stakeholder.days_since_contact }} days ago via {{ stakeholder.last_contact_channel }}
                                        </div>
                                    </div>
                                    <div v-else class="text-sm" style="color: #A1A1AA;">No contact recorded</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center space-x-2">
                                        <span
                                            v-if="stakeholder.needs_follow_up"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                                        >
                                            <ClockIcon class="h-3 w-3 mr-1" />
                                            Follow-up needed
                                        </span>
                                        <span
                                            v-else-if="stakeholder.last_contact_at"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                                        >
                                            <CheckCircleIcon class="h-3 w-3 mr-1" />
                                            Up to date
                                        </span>
                                        <span
                                            v-if="!stakeholder.is_available"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"
                                        >
                                            Unavailable
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <Link
                                            :href="route('stakeholders.show', stakeholder.id)"
                                            class="transition-colors duration-200 hover:opacity-75"
                                            style="color: #884DFF;"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            :href="route('stakeholders.edit', stakeholder.id)"
                                            class="transition-colors duration-200 hover:opacity-75"
                                            style="color: #F59E0B;"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            v-if="stakeholder.email"
                                            @click="sendEmail(stakeholder.email)"
                                            class="transition-colors duration-200 hover:opacity-75"
                                            style="color: #3B82F6;"
                                        >
                                            Email
                                        </button>
                                        <button
                                            v-if="stakeholder.slack_handle"
                                            @click="openSlack(stakeholder.slack_handle)"
                                            class="transition-colors duration-200 hover:opacity-75"
                                            style="color: #22C55E;"
                                        >
                                            Slack
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="stakeholders.length === 0" class="text-center py-12">
                    <UsersIcon class="mx-auto h-12 w-12" style="color: #A1A1AA;" />
                    <h3 class="mt-2 text-sm font-medium" style="color: #A1A1AA;">No stakeholders found</h3>
                    <p class="mt-1 text-sm" style="color: #A1A1AA;">
                        {{ filters.search ? 'Try adjusting your search terms' : 'Add stakeholders to releases to build your directory' }}
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import {
    UsersIcon,
    ClockIcon,
    ChartBarIcon,
    HeartIcon,
    MagnifyingGlassIcon,
    CheckCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
    stakeholders: Array,
    metrics: Object,
    filters: Object,
    user: Object
})

const searchQuery = ref(props.filters.search || '')
const selectedInfluence = ref(props.filters.influence_level || '')
const selectedSupport = ref(props.filters.support_level || '')
const showFollowUpOnly = ref(props.filters.needs_follow_up || false)

const handleSearch = () => {
    handleFilter()
}

const handleFilter = () => {
    const params = {}

    if (searchQuery.value) params.search = searchQuery.value
    if (selectedInfluence.value) params.influence_level = selectedInfluence.value
    if (selectedSupport.value) params.support_level = selectedSupport.value
    if (showFollowUpOnly.value) params.needs_follow_up = true

    router.get(route('stakeholders.index'), params, {
        preserveState: true,
        preserveScroll: true
    })
}

const toggleFollowUpFilter = () => {
    showFollowUpOnly.value = !showFollowUpOnly.value
    handleFilter()
}

const formatDate = (dateString) => {
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    })
}

const sendEmail = (email) => {
    window.open(`mailto:${email}`, '_blank')
}

const openSlack = (slackHandle) => {
    // Basic slack deep link - you might want to customize this based on your Slack setup
    window.open(`slack://user?team=${slackHandle}`, '_blank')
}
</script>