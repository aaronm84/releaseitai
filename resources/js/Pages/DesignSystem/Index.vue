<template>
  <AppLayout>
    <Head title="Design System" />

    <div class="max-w-7xl mx-auto px-4 py-6 space-y-12" style="background: #090909; min-height: 100vh;">
      <!-- Header Section -->
      <div class="dashboard-card p-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">
              Design System Library
            </h1>
            <p class="mt-2 text-lg" style="color: #A1A1AA;">
              Component library and style guide for ReleaseIt.ai
            </p>
          </div>
          <div class="dark-status-indicator">
            Component Library
          </div>
        </div>
      </div>

      <!-- Navigation Components -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Navigation Components</h2>
        </div>

        <div class="space-y-8">
          <!-- NavLink Examples -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Navigation Links</h3>
            <div class="flex items-center space-x-8 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <NavLink href="#" :active="true">Active Link</NavLink>
              <NavLink href="#" :active="false">Inactive Link</NavLink>
              <NavLink href="#" :active="false">Another Link</NavLink>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              Usage: Used in the main navigation bar. Supports active states and hover effects.
            </div>
          </div>

          <!-- ResponsiveNavLink Examples -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Responsive Navigation Links</h3>
            <div class="space-y-2 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <ResponsiveNavLink href="#" :active="true">Active Mobile Link</ResponsiveNavLink>
              <ResponsiveNavLink href="#" :active="false">Inactive Mobile Link</ResponsiveNavLink>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              Usage: Used in the mobile navigation menu. Optimized for touch interaction.
            </div>
          </div>

          <!-- Dropdown Examples -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Dropdown Menu</h3>
            <div class="flex items-center p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <Dropdown align="left" width="48">
                <template #trigger>
                  <button class="purple-gradient-button px-4 py-2 text-white rounded-lg font-medium">
                    Dropdown Example
                  </button>
                </template>
                <template #content>
                  <DropdownLink href="#option1">Option 1</DropdownLink>
                  <DropdownLink href="#option2">Option 2</DropdownLink>
                  <DropdownLink href="#option3">Option 3</DropdownLink>
                </template>
              </Dropdown>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              Usage: For user menus, action menus, and context-sensitive options.
            </div>
          </div>

          <!-- AppHeader Examples -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Application Header</h3>
            <div class="relative overflow-hidden rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <AppHeader
                app-name="ReleaseIt.ai"
                :navigation-items="[
                  { name: 'Dashboard', href: '#dashboard', active: true },
                  { name: 'Projects', href: '#projects', active: false },
                  { name: 'Team', href: '#team', active: false }
                ]"
                :user-menu-items="[
                  { name: 'Profile', href: '#profile' },
                  { name: 'Settings', href: '#settings' },
                  { name: 'Log Out', href: '#logout' }
                ]"
              />
            </div>
            <div class="text-sm space-y-2" style="color: #A1A1AA;">
              <p><strong>Props:</strong></p>
              <ul class="list-disc list-inside space-y-1 ml-4">
                <li><code>appName</code> (String): Application name displayed in header</li>
                <li><code>navigationItems</code> (Array): Main navigation menu items</li>
                <li><code>userMenuItems</code> (Array): User dropdown menu items</li>
              </ul>
              <p><strong>Events:</strong></p>
              <ul class="list-disc list-inside space-y-1 ml-4">
                <li><code>toggle-mobile-menu</code>: Emitted when mobile menu is toggled</li>
              </ul>
              <p><strong>Usage:</strong> Main application header with responsive navigation and user menu.</p>
            </div>
          </div>

          <!-- MenuToggle Examples -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Menu Toggle Button</h3>
            <div class="flex items-center space-x-4 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <MenuToggle />
              <MenuToggle :size="32" />
              <MenuToggle :size="20" />
            </div>
            <div class="text-sm space-y-2" style="color: #A1A1AA;">
              <p><strong>Props:</strong></p>
              <ul class="list-disc list-inside space-y-1 ml-4">
                <li><code>modelValue</code> (Boolean): Whether menu is open (v-model support)</li>
                <li><code>size</code> (Number): Icon size in pixels (default: 24)</li>
                <li><code>labelOpen</code> (String): Aria label when closed</li>
                <li><code>labelClose</code> (String): Aria label when open</li>
              </ul>
              <p><strong>Events:</strong></p>
              <ul class="list-disc list-inside space-y-1 ml-4">
                <li><code>toggle</code>: Emitted when button is clicked</li>
                <li><code>update:modelValue</code>: For v-model support</li>
              </ul>
              <p><strong>Features:</strong> 2-line to X morphing animation, ADHD-friendly reduced motion support, brand focus styling.</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Button Styles -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Button Styles</h2>
        </div>

        <div class="space-y-8">
          <!-- Primary Buttons -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Primary Buttons</h3>
            <div class="flex items-center space-x-4 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <button class="purple-gradient-button px-6 py-3 text-white rounded-xl font-medium transition-all duration-300">
                Primary Button
              </button>
              <button class="purple-gradient-button px-4 py-2 text-white rounded-lg font-medium">
                Smaller Primary
              </button>
              <button class="purple-gradient-button px-6 py-3 text-white rounded-xl font-medium transition-all duration-300 flex items-center space-x-2">
                <span>🚀</span>
                <span>With Icon</span>
              </button>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              CSS Class: <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">purple-gradient-button</code>
            </div>
          </div>

          <!-- Secondary Buttons -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Secondary Buttons</h3>
            <div class="flex items-center space-x-4 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <button class="px-6 py-3 rounded-xl font-medium transition-all duration-300" style="background: rgba(39, 39, 42, 0.8); color: #FAFAFA; border: 1px solid #52525B;">
                Secondary Button
              </button>
              <button class="px-4 py-2 rounded-lg font-medium transition-all duration-300" style="background: rgba(39, 39, 42, 0.8); color: #FAFAFA; border: 1px solid #52525B;">
                Smaller Secondary
              </button>
            </div>
          </div>

          <!-- Status Buttons -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Status Buttons</h3>
            <div class="flex items-center space-x-4 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <button class="px-4 py-2 rounded-lg font-medium" style="background: rgba(34, 197, 94, 0.2); color: #22C55E; border: 1px solid rgba(34, 197, 94, 0.3);">
                Success
              </button>
              <button class="px-4 py-2 rounded-lg font-medium" style="background: rgba(245, 158, 11, 0.2); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);">
                Warning
              </button>
              <button class="px-4 py-2 rounded-lg font-medium" style="background: rgba(239, 68, 68, 0.2); color: #EF4444; border: 1px solid rgba(239, 68, 68, 0.3);">
                Danger
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Styles -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Card Styles</h2>
        </div>

        <div class="space-y-8">
          <!-- Dashboard Card -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Dashboard Card</h3>
            <div class="dashboard-card p-6">
              <h4 class="text-lg font-bold mb-2" style="color: #FAFAFA;">Example Dashboard Card</h4>
              <p style="color: #A1A1AA;">
                This is the main card style used throughout the application. It provides a dark theme with subtle borders and backdrop blur effects.
              </p>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              CSS Class: <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">dashboard-card</code>
            </div>
          </div>

          <!-- Status Cards -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Status Cards</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="p-4 rounded-xl" style="background: rgba(34, 197, 94, 0.05); border: 1px solid rgba(34, 197, 94, 0.3);">
                <div class="text-sm font-medium" style="color: #22C55E;">Success State</div>
                <div class="text-xs mt-1" style="color: #A1A1AA;">Used for completed tasks</div>
              </div>
              <div class="p-4 rounded-xl" style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.3);">
                <div class="text-sm font-medium" style="color: #F59E0B;">Warning State</div>
                <div class="text-xs mt-1" style="color: #A1A1AA;">Used for attention needed</div>
              </div>
              <div class="p-4 rounded-xl" style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.3);">
                <div class="text-sm font-medium" style="color: #EF4444;">Error State</div>
                <div class="text-xs mt-1" style="color: #A1A1AA;">Used for urgent issues</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Indicators -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Status Indicators</h2>
        </div>

        <div class="space-y-8">
          <!-- Dark Status Indicator -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Dark Status Indicator</h3>
            <div class="flex items-center space-x-4 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <div class="dark-status-indicator">Active Status</div>
              <div class="dark-status-indicator">Ready to Review</div>
              <div class="dark-status-indicator">In Progress</div>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              CSS Class: <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">dark-status-indicator</code>
            </div>
          </div>

          <!-- Badge Styles -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Badge Styles</h3>
            <div class="flex items-center space-x-4 p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.5); border: 1px solid #27272A;">
              <span class="px-3 py-1 text-xs font-medium rounded-full" style="background: rgba(136, 77, 255, 0.2); color: rgba(136, 77, 255, 0.8); border: 1px solid rgba(136, 77, 255, 0.3);">
                Focus Mode
              </span>
              <span class="px-3 py-1 text-xs font-medium rounded-full" style="background: rgba(34, 197, 94, 0.2); color: #22C55E; border: 1px solid rgba(34, 197, 94, 0.3);">
                Completed
              </span>
              <span class="px-3 py-1 text-xs font-medium rounded-full" style="background: rgba(245, 158, 11, 0.2); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3);">
                Due Soon
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Color Palette -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Color Palette</h2>
        </div>

        <div class="space-y-8">
          <!-- Brand Colors -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Brand Colors</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="p-4 rounded-lg text-center" style="background: #884DFF;">
                <div class="text-white font-medium">#884DFF</div>
                <div class="text-white text-xs opacity-80">Primary Purple</div>
              </div>
              <div class="p-4 rounded-lg text-center" style="background: #090909; border: 1px solid #27272A;">
                <div style="color: #FAFAFA;" class="font-medium">#090909</div>
                <div style="color: #A1A1AA;" class="text-xs">Background Dark</div>
              </div>
              <div class="p-4 rounded-lg text-center" style="background: #FAFAFA;">
                <div style="color: #090909;" class="font-medium">#FAFAFA</div>
                <div style="color: #52525B;" class="text-xs">Text Light</div>
              </div>
              <div class="p-4 rounded-lg text-center" style="background: #A1A1AA;">
                <div class="text-white font-medium">#A1A1AA</div>
                <div class="text-white text-xs opacity-80">Text Muted</div>
              </div>
            </div>
          </div>

          <!-- Status Colors -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Status Colors</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div class="p-4 rounded-lg text-center" style="background: #22C55E;">
                <div class="text-white font-medium">#22C55E</div>
                <div class="text-white text-xs opacity-80">Success Green</div>
              </div>
              <div class="p-4 rounded-lg text-center" style="background: #F59E0B;">
                <div class="text-white font-medium">#F59E0B</div>
                <div class="text-white text-xs opacity-80">Warning Amber</div>
              </div>
              <div class="p-4 rounded-lg text-center" style="background: #EF4444;">
                <div class="text-white font-medium">#EF4444</div>
                <div class="text-white text-xs opacity-80">Error Red</div>
              </div>
              <div class="p-4 rounded-lg text-center" style="background: #27272A;">
                <div style="color: #FAFAFA;" class="font-medium">#27272A</div>
                <div style="color: #A1A1AA;" class="text-xs">Border Gray</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Layout Components -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Layout Components</h2>
        </div>

        <div class="space-y-8">
          <!-- Header/Navbar -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Header/Navigation Bar</h3>
            <div class="p-4 rounded-lg" style="background: rgba(9, 9, 11, 0.95); border: 1px solid #27272A;">
              <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                  <div class="text-xl font-bold" style="color: #884DFF;">ReleaseIt.ai</div>
                  <div class="hidden sm:flex space-x-6 ml-8">
                    <a href="#" class="px-4 py-2 text-sm font-medium" style="color: #884DFF; border-bottom: 2px solid #884DFF;">Dashboard</a>
                    <a href="#" class="px-4 py-2 text-sm font-medium" style="color: #A1A1AA;">Workstreams</a>
                    <a href="#" class="px-4 py-2 text-sm font-medium" style="color: #A1A1AA;">Releases</a>
                  </div>
                </div>
                <div class="flex items-center space-x-4">
                  <div class="dark-status-indicator">User Menu</div>
                </div>
              </div>
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              Fixed header with brand, navigation links, and user menu. Uses backdrop blur and sticky positioning.
            </div>
          </div>

          <!-- Morning Brief -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Morning Brief Component</h3>
            <MorningBrief
              summary="Here's what you need to know to start your day effectively. Focus on the top priorities and key stakeholder updates."
              :highlights="[
                '3 releases need your attention today',
                '2 stakeholder follow-ups overdue',
                'Critical deployment scheduled for 2 PM'
              ]"
            />
            <div class="text-sm" style="color: #A1A1AA;">
              <strong>Component:</strong> <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">MorningBrief</code><br>
              <strong>Props:</strong> title, summary, highlights (array)
            </div>
          </div>

          <!-- Dark Card -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Dark Card Component</h3>
            <DarkCard title="Dark Card Example" icon="📊">
              <p style="color: #D1D5DB;">This is a specialized card variant used for content sections that need more visual distinction. It accepts slots for flexible content.</p>
              <template #actions>
                <button class="text-sm text-purple-400 hover:text-purple-300 font-medium transition-colors">
                  Action →
                </button>
              </template>
            </DarkCard>
            <div class="text-sm" style="color: #A1A1AA;">
              <strong>Component:</strong> <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">DarkCard</code><br>
              <strong>Props:</strong> title, icon, showAccent, borderColor<br>
              <strong>Slots:</strong> default (content), actions (header actions)
            </div>
          </div>

          <!-- Workstream Card -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Workstream Cards</h3>
            <div class="space-y-4">
              <WorkstreamCard
                title="Product Initiative"
                description="Active development phase"
                icon="🎯"
                status="Active"
                variant="blue"
                @click="() => console.log('Clicked Product Initiative')"
              />
              <WorkstreamCard
                title="Experiment"
                description="Testing new feature"
                icon="🧪"
                status="Testing"
                variant="green"
                @click="() => console.log('Clicked Experiment')"
              />
              <WorkstreamCard
                title="Research Project"
                description="Gathering user feedback"
                icon="🔬"
                status="Research"
                variant="purple"
                @click="() => console.log('Clicked Research')"
              />
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              <strong>Component:</strong> <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">WorkstreamCard</code><br>
              <strong>Props:</strong> title, description, icon, status, variant (blue/green/purple/orange), clickable<br>
              <strong>Events:</strong> @click
            </div>
          </div>
        </div>
      </div>

      <!-- Metric Cards -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Metric Cards</h2>
        </div>

        <div class="space-y-6">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <MetricCard
              title="Total Stakeholders"
              :value="24"
              icon="👥"
              description="People in your network"
              variant="blue"
            />
            <MetricCard
              title="Need Follow-up"
              :value="7"
              icon="⏰"
              description="Overdue communications"
              variant="yellow"
            />
            <MetricCard
              title="Response Rate"
              :value="85"
              suffix="%"
              icon="📈"
              description="Communication health"
              variant="green"
            />
          </div>
          <div class="text-sm" style="color: #A1A1AA;">
            <strong>Component:</strong> <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">MetricCard</code><br>
            <strong>Props:</strong> title, value, icon, description, variant (blue/green/yellow/red/purple), suffix<br>
            <strong>Usage:</strong> Color-coded gradients and opacity levels for visual hierarchy
          </div>
        </div>
      </div>

      <!-- Interactive Elements -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Interactive Elements</h2>
        </div>

        <div class="space-y-8">
          <!-- Action Items -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Action Items</h3>
            <div class="space-y-3">
              <ActionItem
                name="John Doe"
                subtitle="3 days overdue"
                variant="urgent"
                action-text="Contact"
                @action="(data) => console.log('Action clicked:', data)"
              />
              <ActionItem
                name="Alice Smith"
                subtitle="Contacted yesterday"
                variant="recent"
                status-text="✓ Recent"
              />
              <ActionItem
                name="Sarah Johnson"
                subtitle="2 days overdue"
                variant="urgent"
                action-text="Follow Up"
                @action="(data) => console.log('Follow up clicked:', data)"
              />
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              <strong>Component:</strong> <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">ActionItem</code><br>
              <strong>Props:</strong> name, subtitle, variant (urgent/recent), actionText, statusText<br>
              <strong>Events:</strong> @action (urgent variant only)
            </div>
          </div>

          <!-- Priority Indicators -->
          <div class="space-y-4">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Priority Indicators</h3>
            <div class="space-y-3">
              <PriorityIndicator
                title="High Priority Task"
                description="Due in 1 day - requires immediate attention"
                priority="high"
                @click="(data) => console.log('High priority clicked:', data)"
              />
              <PriorityIndicator
                title="Medium Priority Task"
                description="Due in 3 days - plan accordingly"
                priority="medium"
                @click="(data) => console.log('Medium priority clicked:', data)"
              />
              <PriorityIndicator
                title="Normal Priority Task"
                description="Due next week - on track"
                priority="normal"
                @click="(data) => console.log('Normal priority clicked:', data)"
              />
            </div>
            <div class="text-sm" style="color: #A1A1AA;">
              <strong>Component:</strong> <code style="background: rgba(39, 39, 42, 0.8); padding: 2px 6px; border-radius: 4px; color: #F59E0B;">PriorityIndicator</code><br>
              <strong>Props:</strong> title, description, priority (high/medium/normal), clickable<br>
              <strong>Events:</strong> @click<br>
              <strong>Usage:</strong> Color-coded borders and backgrounds based on priority level
            </div>
          </div>
        </div>
      </div>

      <!-- Typography -->
      <div class="dashboard-card p-8">
        <div class="flex items-center mb-6">
          <div class="w-1 h-8 rounded-full mr-4" style="background: #884DFF;"></div>
          <h2 class="text-xl font-bold" style="color: #FAFAFA;">Typography</h2>
        </div>

        <div class="space-y-6">
          <div class="space-y-2">
            <h1 class="text-3xl font-bold" style="color: #FAFAFA;">Heading 1 - 3xl Bold</h1>
            <div class="text-sm" style="color: #A1A1AA;">Used for page titles and main headings</div>
          </div>

          <div class="space-y-2">
            <h2 class="text-xl font-bold" style="color: #FAFAFA;">Heading 2 - xl Bold</h2>
            <div class="text-sm" style="color: #A1A1AA;">Used for section headers</div>
          </div>

          <div class="space-y-2">
            <h3 class="text-lg font-semibold" style="color: #FAFAFA;">Heading 3 - lg Semibold</h3>
            <div class="text-sm" style="color: #A1A1AA;">Used for subsection headers</div>
          </div>

          <div class="space-y-2">
            <p class="text-base" style="color: #FAFAFA;">Body Text - Base Regular</p>
            <div class="text-sm" style="color: #A1A1AA;">Standard body text for content</div>
          </div>

          <div class="space-y-2">
            <p class="text-sm" style="color: #A1A1AA;">Small Text - sm Regular</p>
            <div class="text-xs" style="color: #71717A;">Used for captions and secondary information</div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import AppHeader from '@/Components/AppHeader.vue';
import MenuToggle from '@/Components/MenuToggle.vue';
import MorningBrief from '@/Components/MorningBrief.vue';
import DarkCard from '@/Components/DarkCard.vue';
import WorkstreamCard from '@/Components/WorkstreamCard.vue';
import MetricCard from '@/Components/MetricCard.vue';
import ActionItem from '@/Components/ActionItem.vue';
import PriorityIndicator from '@/Components/PriorityIndicator.vue';
</script>

<style scoped>
.dashboard-card {
  background: rgba(9, 9, 11, 0.8);
  border: 1px solid #27272A;
  border-radius: 16px;
  backdrop-filter: blur(12px);
}

.purple-gradient-button {
  background: linear-gradient(135deg, #884DFF 0%, #9333EA 100%);
  box-shadow: 0 4px 15px rgba(136, 77, 255, 0.3);
}

.purple-gradient-button:hover {
  background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
  box-shadow: 0 6px 20px rgba(136, 77, 255, 0.4);
  transform: translateY(-1px);
}

.dark-status-indicator {
  background: rgba(9, 9, 11, 0.8);
  border: 1px solid #27272A;
  border-radius: 10px;
  color: #A1A1AA;
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  backdrop-filter: blur(12px);
}

code {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
}

.dark-morning-brief {
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(12px);
}

.dark-card {
  background: rgba(9, 9, 11, 0.8);
  backdrop-filter: blur(12px);
}

.workstream-card {
  transition: all 0.3s ease;
  backdrop-filter: blur(8px);
}

.workstream-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}
</style>