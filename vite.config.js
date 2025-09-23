/// <reference types="vitest/config" />
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/auth.js'],
      refresh: true
    }),
    vue({
      template: {
        transformAssetUrls: {
          base: null,
          includeAbsolute: false
        }
      }
    })
  ],
  resolve: {
    alias: {
      '@': '/resources/js',
      'vue': 'vue/dist/vue.esm-bundler.js'
    }
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          // Split vendor code for better caching
          'vendor': ['vue', '@inertiajs/vue3'],
          'ui-components': [
            '@/Components/MorningBrief.vue',
            '@/Components/DarkCard.vue',
            '@/Components/WorkstreamCard.vue',
            '@/Components/MetricCard.vue',
            '@/Components/ActionItem.vue',
            '@/Components/PriorityIndicator.vue'
          ]
        }
      }
    },
    // Optimize chunk size limit for faster loading
    chunkSizeWarningLimit: 1000,
    // Enable source maps for easier debugging
    sourcemap: true
  },
  // Performance optimizations for dev server
  server: {
    hmr: {
      overlay: false // Disable error overlay for better UX during development
    }
  },
  // Optimize dependencies
  optimizeDeps: {
    include: ['vue', '@inertiajs/vue3', 'axios']
  },
  test: {
    environment: 'jsdom'
  }
});