<template>
  <AppLayout>
    <Head title="Dashboard" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
      <!-- Header Section -->
      <div class="p-8">
        <div class="flex justify-between">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
              {{ timeAwareGreeting }}, {{ user.name }}
            </h1>
            <p class="mt-2 text-lg" style="color: #A1A1AA;">Here's what needs your attention today</p>
          </div>
          <div class="dark-status-indicator self-start">
              {{ currentDate }}
            </div>
        </div>
      </div>

      <!-- Pill Row for minimized components -->
      <PillRow :pills="pills" @restore="restoreComponent" />

      <div class="grid grid-cols-3 gap-4 flex">

        <!-- Time-aware Brief -->
        <MinimizableComponent
          v-if="morningBrief"
          component-id="MorningBrief"
          :component-name="morningBrief.title"
          class="col-span-3 flex-1"
          >
          <MorningBrief
            :title="morningBrief.title"
            :icon="morningBrief.icon"
            :summary="morningBrief.summary"
            :highlights="morningBrief.highlights"
          />
        </MinimizableComponent>

        <!-- Top 3 Priorities -->
        <MinimizableComponent
          component-id="TopPriorities"
          component-name="Top Priorities"
          class="cols-span-1 flex-1">
          <TopPriorities :priorities="topPriorities" class=""/>
        </MinimizableComponent>

        <!-- Quick Add Brain Dump -->
        <MinimizableComponent
          component-id="BrainDump"
          component-name="Brain Dump"
          class="col-span-2 flex-1">
          <BrainDump :config="quickAddConfig" class=""/>
        </MinimizableComponent>

        <!-- Workstreams Overview -->
        <MinimizableComponent
          v-if="workstreams && workstreams.length > 0"
          component-id="Workstreams"
          component-name="Workstreams"
          class="col-span-2">
          <div class="dashboard-card p-6">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
              <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
              <h2 class="text-xl font-bold" style="color: #FAFAFA;">Your Workstreams</h2>
            </div>
            <button
              @click="navigateToWorkstreams"
              class="text-sm font-medium transition-colors duration-300"
              style="color: #884DFF;"
            >
              View All →
            </button>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="workstream in workstreams"
              :key="workstream.id"
              class="rounded-xl p-6 cursor-pointer transition-all duration-300"
              style="background: rgba(9, 9, 11, 0.3); border: 1px solid #27272A; backdrop-filter: blur(12px);"
              @mouseenter="$event.target.style.borderColor = 'rgba(136, 77, 255, 0.5)'"
              @mouseleave="$event.target.style.borderColor = '#27272A'"
              @click="navigateToWorkstream(workstream.id)"
            >
              <div class="flex items-center justify-between mb-6">
                <h3 class="font-bold text-lg" style="color: #FAFAFA;">{{ workstream.name }}</h3>
                <span class="px-3 py-1 rounded-full text-xs font-medium" style="background: rgba(136, 77, 255, 0.2); color: #884DFF; border: 1px solid rgba(136, 77, 255, 0.3);">
                  {{ workstream.type }}
                </span>
              </div>

              <div class="space-y-2">
                <div class="flex justify-between text-sm">
                  <span style="color: #A1A1AA;">Active Releases</span>
                  <span class="font-medium" style="color: #FAFAFA;">{{ workstream.active_releases_count }}</span>
                </div>
                <div class="flex justify-between text-sm">
                  <span style="color: #A1A1AA;">Total Releases</span>
                  <span class="font-medium" style="color: #FAFAFA;">{{ workstream.total_releases_count }}</span>
                </div>
              </div>

              <div class="mt-6">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-medium" style="color: #A1A1AA;">Progress</span>
                  <span class="text-sm font-bold" style="color: #884DFF;">{{ workstream.completion_percentage }}%</span>
                </div>
                <div class="w-full h-3 rounded-full" style="background: rgba(39, 39, 42, 0.5);">
                  <div
                    class="h-3 rounded-full"
                    style="background: #884DFF;"
                    :style="{ width: `${workstream.completion_percentage}%` }"
                  ></div>
                </div>
              </div>
            </div>
          </div>
          </div>
        </MinimizableComponent>

        <!-- Empty State for Workstreams -->
        <MinimizableComponent
          v-else
          component-id="Workstreams"
          component-name="Workstreams"
          class="col-span-2">
          <div class="dashboard-card p-6 text-center">
          <div class="py-12">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background: #884DFF;">
              <span class="text-2xl">🏗️</span>
            </div>
            <h3 class="text-lg font-semibold mb-2" style="color: #FAFAFA;">No Workstreams Yet</h3>
            <p class="mb-6" style="color: #A1A1AA;">Create your first workstream to organize your product releases</p>
            <button
              @click="navigateToWorkstreams"
              class="px-8 py-4 rounded-xl font-medium transition-all duration-300 flex items-center space-x-2 mx-auto"
              style="background: #884DFF; color: #FAFAFA; border-radius: 10px;"
            >
              <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background: rgba(255, 255, 255, 0.2);">
                <span class="text-sm">🚀</span>
              </div>
              <span>Create Your First Workstream</span>
            </button>
          </div>
          </div>
        </MinimizableComponent>

        <!-- Stakeholder Insights -->
        <MinimizableComponent
          v-if="stakeholderData"
          component-id="Stakeholders"
          component-name="Stakeholders"
          class="col-span-1">
          <div class="dark-card rounded-xl p-6 border border-dark-border">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
              <div class="w-1 h-8 bg-gradient-to-b from-purple-400 to-purple-600 rounded-full mr-4"></div>
              <div class="flex items-center space-x-3">
                <div class="w-8 h-8 bg-purple-500/20 rounded-xl flex items-center justify-center">
                  <span class="text-lg">👥</span>
                </div>
                <h2 class="text-xl font-bold text-white">Stakeholder Overview</h2>
              </div>
            </div>
            <button
              @click="navigateToStakeholders"
              class="text-sm text-purple-400 hover:text-purple-300 font-medium transition-colors duration-300"
            >
              View All →
            </button>
          </div>

          <!-- Stakeholder Metrics -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="p-5 bg-gradient-to-br from-blue-500/10 to-blue-600/10 rounded-xl ">
              <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center">
                  <span class="text-lg">👥</span>
                </div>
                <span class="text-2xl font-bold text-blue-300">{{ stakeholderData.total_stakeholders }}</span>
              </div>
              <h3 class="font-medium text-blue-200">Total Stakeholders</h3>
              <p class="text-xs text-blue-300/70 mt-1">People in your network</p>
            </div>

            <div class="p-5 bg-gradient-to-br from-yellow-500/10 to-yellow-600/10 rounded-xl">
              <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-yellow-500/20 rounded-xl flex items-center justify-center">
                  <span class="text-lg">⏰</span>
                </div>
                <span class="text-2xl font-bold text-yellow-300">{{ stakeholderData.needs_follow_up }}</span>
              </div>
              <h3 class="font-medium text-yellow-200">Need Follow-up</h3>
              <p class="text-xs text-yellow-300/70 mt-1">Overdue communications</p>
            </div>

            <div class="p-5 bg-gradient-to-br from-green-500/10 to-green-600/10 rounded-xl ">
              <div class="flex items-center justify-between mb-3">
                <div class="w-10 h-10 bg-green-500/20 rounded-xl flex items-center justify-center">
                  <span class="text-lg">📈</span>
                </div>
                <span class="text-2xl font-bold text-green-300">{{ Math.round(stakeholderData.response_rate) }}%</span>
              </div>
              <h3 class="font-medium text-green-200">Response Rate</h3>
              <p class="text-xs text-green-300/70 mt-1">Communication health</p>
            </div>
          </div>

          <!-- Action Items -->
          <div v-if="stakeholderData.overdue_contacts && stakeholderData.overdue_contacts.length > 0" class="space-y-4">
            <h3 class="font-medium text-white flex items-center">
              <span class="w-2 h-2 bg-red-400 rounded-full mr-2"></span>
              Urgent Follow-ups
            </h3>
            <div class="space-y-2">
              <div
                v-for="contact in stakeholderData.overdue_contacts.slice(0, 3)"
                :key="contact.id"
                class="flex items-center justify-between p-4 bg-red-500/10 rounded-xl border border-red-500/30"
              >
                <div class="flex items-center space-x-3">
                  <div class="w-8 h-8 bg-red-500/20 rounded-full flex items-center justify-center">
                    <span class="text-sm font-medium text-red-300">{{ contact.name.split(' ').map(n => n[0]).join('').toUpperCase() }}</span>
                  </div>
                  <div>
                    <p class="font-medium text-red-200">{{ contact.name }}</p>
                    <p class="text-xs text-red-300/70">{{ contact.days_overdue }} days overdue</p>
                  </div>
                </div>
                <button
                  @click="navigateToStakeholder(contact.id)"
                  class="px-3 py-1 bg-red-500/20 text-red-300 rounded-lg text-sm font-medium hover:bg-red-500/30 transition-colors duration-200"
                >
                  Contact
                </button>
              </div>
            </div>
          </div>

          <!-- Recent Activity -->
          <div v-if="stakeholderData.recently_contacted && stakeholderData.recently_contacted.length > 0" class="mt-6 space-y-4">
            <h3 class="font-medium text-white flex items-center">
              <span class="w-2 h-2 bg-green-400 rounded-full mr-2"></span>
              Recent Communications
            </h3>
            <div class="space-y-2">
              <div
                v-for="contact in stakeholderData.recently_contacted.slice(0, 3)"
                :key="contact.id"
                class="flex items-center justify-between p-3 bg-green-500/10 rounded-xl border border-green-500/30"
              >
                <div class="flex items-center space-x-3">
                  <div class="w-6 h-6 bg-green-500/20 rounded-full flex items-center justify-center">
                    <span class="text-xs font-medium text-green-300">{{ contact.name.split(' ').map(n => n[0]).join('').toUpperCase() }}</span>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-green-200">{{ contact.name }}</p>
                    <p class="text-xs text-green-300/70">{{ formatLastContact(contact.last_contact_at) }} via {{ contact.channel }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>
        </MinimizableComponent>

      </div>



    </div>
  </AppLayout>
</template>

<script setup>
import { computed, provide } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BrainDump from '@/Components/BrainDump.vue';
import MorningBrief from '@/Components/MorningBrief.vue';
import TopPriorities from '@/Components/TopPriorities.vue';
import MinimizableComponent from '@/Components/MinimizableComponent.vue';
import PillRow from '@/Components/PillRow.vue';
import { useComponentMinimization } from '@/composables/useComponentMinimization';

const props = defineProps({
  releases: Array,
  topPriorities: Array,
  workstreams: Array,
  stakeholderData: Object,
  user: Object,
  quickAddConfig: Object,
  morningBrief: Object,
});

// Component minimization system
const {
  pills,
  isMinimized,
  canBeMinimized,
  minimizeComponent,
  restoreComponent,
  registerComponent,
  getComponentAriaAttributes,
  handleKeyboardEvent,
  getPillAriaAttributes,
  handlePillKeyboardEvent
} = useComponentMinimization();

// Provide minimization functions to child components
provide('minimizeComponent', minimizeComponent);
provide('restoreComponent', restoreComponent);
provide('isMinimized', isMinimized);
provide('canBeMinimized', canBeMinimized);
provide('getComponentAriaAttributes', getComponentAriaAttributes);
provide('handleKeyboardEvent', handleKeyboardEvent);
provide('getPillAriaAttributes', getPillAriaAttributes);
provide('handlePillKeyboardEvent', handlePillKeyboardEvent);

// Register time-aware brief component with dynamic configuration
if (props.morningBrief) {
  registerComponent('MorningBrief', {
    name: props.morningBrief.title,
    icon: props.morningBrief.icon,
    color: '#3B82F6',
    description: `Restore ${props.morningBrief.title} component`
  });
}


const currentDate = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
});

const timeAwareGreeting = computed(() => {
  const now = new Date();
  const hour = now.getHours();

  if (hour >= 6 && hour < 12) {
    return 'Good morning';
  } else if (hour >= 12 && hour < 18) {
    return 'Good afternoon';
  } else {
    return 'Good evening';
  }
});



const navigateToWorkstream = (workstreamId) => {
  router.visit(`/workstreams/${workstreamId}`);
};

const navigateToWorkstreams = () => {
  router.visit('/workstreams');
};

const navigateToStakeholders = () => {
  router.visit('/stakeholders');
};

const navigateToStakeholder = (stakeholderId) => {
  router.visit(`/stakeholders/${stakeholderId}`);
};

const formatLastContact = (dateString) => {
  const date = new Date(dateString);
  const now = new Date();
  const diffInDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));

  if (diffInDays === 0) return 'Today';
  if (diffInDays === 1) return 'Yesterday';
  if (diffInDays < 7) return `${diffInDays} days ago`;

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric'
  });
};

</script>