import { ref, computed, watch } from 'vue'

export function useComponentMinimization() {
  // State for which components are minimized
  const minimizedComponents = ref(new Set())

  // Component configurations for pills
  const componentConfigs = {
    'MorningBrief': {
      name: 'Morning Brief',
      icon: '🌅',
      color: '#3B82F6',
      description: 'Restore Morning Brief component'
    },
    'BrainDump': {
      name: 'Brain Dump',
      icon: '🧠',
      color: '#884DFF',
      description: 'Restore Brain Dump component'
    },
    'TopPriorities': {
      name: 'Top Priorities',
      icon: '⭐',
      color: '#F59E0B',
      description: 'Restore Top Priorities component'
    },
    'EndOfDaySummary': {
      name: 'End of Day',
      icon: '🌅',
      color: '#10B981',
      description: 'Restore End of Day Summary component'
    },
    'Workstreams': {
      name: 'Workstreams',
      icon: '🏗️',
      color: '#884DFF',
      description: 'Restore Workstreams component'
    },
    'Stakeholders': {
      name: 'Stakeholders',
      icon: '👥',
      color: '#EC4899',
      description: 'Restore Stakeholders component'
    }
  }

  // Check if a component can be minimized (greeting cannot be minimized)
  const canBeMinimized = (componentId) => {
    return componentId !== 'Greeting'
  }

  // Check if a component is minimized
  const isMinimized = (componentId) => {
    return minimizedComponents.value.has(componentId)
  }

  // Check if a component is expanded
  const isExpanded = (componentId) => {
    return !isMinimized(componentId)
  }

  // Minimize a component
  const minimizeComponent = (componentId) => {
    if (!canBeMinimized(componentId)) {
      return { success: false, message: 'Component cannot be minimized' }
    }

    minimizedComponents.value.add(componentId)
    saveState()
    return { success: true }
  }

  // Restore a component
  const restoreComponent = (componentId) => {
    minimizedComponents.value.delete(componentId)
    saveState()
    return { success: true }
  }

  // Get pill data for minimized components
  const pills = computed(() => {
    return Array.from(minimizedComponents.value).map(componentId => ({
      id: componentId,
      ...componentConfigs[componentId]
    }))
  })

  // Get pill row layout for different screen sizes
  const getPillRowLayout = (screenSize = 'desktop') => {
    const pillsPerRow = {
      mobile: 3,
      tablet: 5,
      desktop: 6
    }

    return {
      pillsPerRow: pillsPerRow[screenSize] || 5,
      rowCount: Math.ceil(pills.value.length / (pillsPerRow[screenSize] || 5))
    }
  }

  // Focus mode - minimize all except one component
  const activateFocusMode = (keepExpanded = null) => {
    const allComponents = Object.keys(componentConfigs)

    allComponents.forEach(componentId => {
      if (componentId !== keepExpanded && canBeMinimized(componentId)) {
        minimizedComponents.value.add(componentId)
      }
    })

    saveState()
  }

  // Deactivate focus mode - restore all components
  const deactivateFocusMode = () => {
    minimizedComponents.value.clear()
    saveState()
  }

  // Get lists of minimized and expanded components
  const getMinimizedComponents = () => {
    return Array.from(minimizedComponents.value)
  }

  const getExpandedComponents = () => {
    const allComponents = Object.keys(componentConfigs)
    return allComponents.filter(id => !minimizedComponents.value.has(id))
  }

  // State persistence
  const saveState = () => {
    const state = Array.from(minimizedComponents.value)
    localStorage.setItem('dashboard_minimized_components', JSON.stringify(state))
  }

  const loadState = () => {
    try {
      const saved = localStorage.getItem('dashboard_minimized_components')
      if (saved) {
        const state = JSON.parse(saved)
        minimizedComponents.value = new Set(state)
      }
    } catch (error) {
      console.warn('Failed to load minimization state:', error)
      minimizedComponents.value = new Set()
    }
  }

  // Animation helpers
  const createAnimationPromise = (duration = 300) => {
    let isAnimating = true

    const promise = new Promise(resolve => {
      setTimeout(() => {
        isAnimating = false
        resolve()
      }, duration)
    })

    return {
      isAnimating: () => isAnimating,
      promise
    }
  }

  // Minimize with animation
  const minimizeWithAnimation = async (componentId) => {
    const { promise } = createAnimationPromise(300)
    minimizeComponent(componentId)
    await promise
    return { success: true }
  }

  // Keyboard event handling
  const handleKeyboardEvent = (event, componentId) => {
    if (event.key === 'Escape' && canBeMinimized(componentId) && isExpanded(componentId)) {
      minimizeComponent(componentId)
      return { handled: true }
    }
    return { handled: false }
  }

  // Pill keyboard event handling
  const handlePillKeyboardEvent = (event, componentId) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      restoreComponent(componentId)
      return { handled: true }
    }
    return { handled: false }
  }

  // Screen reader announcements
  const getScreenReaderAnnouncement = (componentId, action) => {
    const componentName = componentConfigs[componentId]?.name || componentId

    if (action === 'minimize') {
      return `${componentName} minimized`
    } else if (action === 'restore') {
      return `${componentName} restored`
    }

    return ''
  }

  // ARIA attributes for components
  const getComponentAriaAttributes = (componentId) => {
    const componentName = componentConfigs[componentId]?.name || componentId

    return {
      'aria-label': `${componentName} component`,
      'aria-expanded': isExpanded(componentId) ? 'true' : 'false',
      'role': 'region'
    }
  }

  // ARIA attributes for pills
  const getPillAriaAttributes = (componentId) => {
    const componentName = componentConfigs[componentId]?.name || componentId

    return {
      'aria-label': `Restore ${componentName} component`,
      'role': 'button',
      'tabindex': '0'
    }
  }

  // Watch for changes to announce to screen readers
  watch(minimizedComponents, (newValue, oldValue) => {
    // In a real implementation, this would integrate with a screen reader announcement service
    const newSet = new Set(newValue)
    const oldSet = new Set(oldValue)

    // Find newly minimized components
    for (const componentId of newSet) {
      if (!oldSet.has(componentId)) {
        console.log('Screen reader announcement:', getScreenReaderAnnouncement(componentId, 'minimize'))
      }
    }

    // Find newly restored components
    for (const componentId of oldSet) {
      if (!newSet.has(componentId)) {
        console.log('Screen reader announcement:', getScreenReaderAnnouncement(componentId, 'restore'))
      }
    }
  }, { deep: true })

  // Initialize state on first use
  loadState()

  return {
    // State
    minimizedComponents: computed(() => minimizedComponents.value),
    pills,

    // Core functions
    canBeMinimized,
    isMinimized,
    isExpanded,
    minimizeComponent,
    restoreComponent,

    // Advanced functions
    minimizeWithAnimation,
    activateFocusMode,
    deactivateFocusMode,

    // Getters
    getMinimizedComponents,
    getExpandedComponents,
    getPillRowLayout,

    // Event handling
    handleKeyboardEvent,
    handlePillKeyboardEvent,

    // Accessibility
    getComponentAriaAttributes,
    getPillAriaAttributes,
    getScreenReaderAnnouncement,

    // Utilities
    loadState,
    saveState
  }
}