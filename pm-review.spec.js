import { test, expect } from '@playwright/test';
import fs from 'fs';
import path from 'path';

// Product Manager Review Test Suite
test.describe('Product Manager Comprehensive Review', () => {
  let context;
  let page;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      deviceScaleFactor: 1
    });
    page = await context.newPage();
  });

  test.afterAll(async () => {
    await context.close();
  });

  test('1. Dashboard - Initial Load and Visual Hierarchy', async () => {
    console.log('🔍 Testing Dashboard - Initial Load and Visual Hierarchy');

    // Navigate to dashboard
    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    // Take screenshot for review
    await page.screenshot({
      path: '/Users/aaronmiddleton/Documents/Dev/releaseit/pm-review-dashboard.png',
      fullPage: true
    });

    // Check for key elements that PMs need
    const header = await page.locator('header, .app-header').first();
    expect(await header.isVisible()).toBeTruthy();

    // Look for navigation elements
    const navElements = await page.locator('nav, .navigation, [role="navigation"]');
    console.log(`Navigation elements found: ${await navElements.count()}`);

    // Check for main content area
    const mainContent = await page.locator('main, .main-content, .dashboard').first();
    expect(await mainContent.isVisible()).toBeTruthy();

    // Look for Morning Brief component
    const morningBrief = await page.locator('.morning-brief, [data-component="morning-brief"]');
    if (await morningBrief.count() > 0) {
      console.log('✅ Morning Brief component found');
    }

    // Look for metric cards
    const metricCards = await page.locator('.metric-card, [data-component="metric-card"]');
    console.log(`Metric cards found: ${await metricCards.count()}`);

    // Look for workstream cards
    const workstreamCards = await page.locator('.workstream-card, [data-component="workstream-card"]');
    console.log(`Workstream cards found: ${await workstreamCards.count()}`);

    // Check for action items
    const actionItems = await page.locator('.action-item, [data-component="action-item"]');
    console.log(`Action items found: ${await actionItems.count()}`);
  });

  test('2. Mobile Responsiveness Test', async () => {
    console.log('📱 Testing Mobile Responsiveness');

    // Test iPhone viewport
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    await page.screenshot({
      path: '/Users/aaronmiddleton/Documents/Dev/releaseit/pm-review-mobile.png',
      fullPage: true
    });

    // Check if navigation collapses properly
    const hamburgerMenu = await page.locator('.hamburger, .mobile-menu-toggle, [aria-label*="menu"]');
    if (await hamburgerMenu.count() > 0) {
      console.log('✅ Mobile navigation found');
    }

    // Check if cards stack properly
    const cards = await page.locator('.card, .metric-card, .workstream-card');
    console.log(`Cards visible on mobile: ${await cards.count()}`);

    // Test tablet viewport
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.waitForTimeout(500);

    await page.screenshot({
      path: '/Users/aaronmiddleton/Documents/Dev/releaseit/pm-review-tablet.png',
      fullPage: true
    });

    // Reset to desktop
    await page.setViewportSize({ width: 1440, height: 900 });
  });

  test('3. Design System Evaluation', async () => {
    console.log('🎨 Testing Design System');

    await page.goto('http://localhost:8001/design-system');
    await page.waitForLoadState('networkidle');

    await page.screenshot({
      path: '/Users/aaronmiddleton/Documents/Dev/releaseit/pm-review-design-system.png',
      fullPage: true
    });

    // Check for component examples
    const componentSections = await page.locator('.component-section, .component-example');
    console.log(`Component sections found: ${await componentSections.count()}`);

    // Look for interactive examples
    const interactiveElements = await page.locator('button, .btn, .interactive');
    console.log(`Interactive elements found: ${await interactiveElements.count()}`);

    // Test some interactive elements
    const buttons = await page.locator('button').all();
    for (let i = 0; i < Math.min(buttons.length, 3); i++) {
      try {
        await buttons[i].click();
        console.log(`✅ Button ${i + 1} clickable`);
      } catch (e) {
        console.log(`⚠️ Button ${i + 1} not clickable: ${e.message}`);
      }
    }
  });

  test('4. Navigation and Information Architecture', async () => {
    console.log('🗺️ Testing Navigation and Information Architecture');

    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    // Try to find and test navigation links
    const navLinks = await page.locator('a[href*="/"], .nav-link, nav a').all();
    console.log(`Navigation links found: ${navLinks.length}`);

    const testedLinks = [];
    for (let i = 0; i < Math.min(navLinks.length, 5); i++) {
      try {
        const href = await navLinks[i].getAttribute('href');
        const text = await navLinks[i].textContent();
        if (href && !href.includes('mailto') && !href.includes('tel')) {
          testedLinks.push({ href, text: text?.trim() });
        }
      } catch (e) {
        console.log(`Link ${i} error: ${e.message}`);
      }
    }

    // Test a few key navigation paths
    for (const link of testedLinks.slice(0, 3)) {
      try {
        await page.goto(`http://localhost:8001${link.href}`);
        await page.waitForLoadState('networkidle');
        console.log(`✅ Successfully navigated to: ${link.text} (${link.href})`);

        // Take screenshot of each major page
        const safeName = link.text?.replace(/[^a-zA-Z0-9]/g, '-') || 'unknown';
        await page.screenshot({
          path: `/Users/aaronmiddleton/Documents/Dev/releaseit/pm-review-${safeName}.png`,
          fullPage: true
        });
      } catch (e) {
        console.log(`⚠️ Failed to navigate to: ${link.text} (${link.href}) - ${e.message}`);
      }
    }
  });

  test('5. ADHD-Focused UX Analysis', async () => {
    console.log('🧠 Testing ADHD-Focused UX Features');

    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    // Check for priority indicators
    const priorityIndicators = await page.locator('.priority, .priority-indicator, [data-priority]');
    console.log(`Priority indicators found: ${await priorityIndicators.count()}`);

    // Check for visual hierarchy elements
    const headings = await page.locator('h1, h2, h3, h4, h5, h6');
    console.log(`Headings for hierarchy: ${await headings.count()}`);

    // Check for clear sections/cards
    const cards = await page.locator('.card, .dark-card, .metric-card, .workstream-card');
    console.log(`Cards for information chunking: ${await cards.count()}`);

    // Check for action-oriented elements
    const actionElements = await page.locator('button, .action-item, .cta, [role="button"]');
    console.log(`Action elements: ${await actionElements.count()}`);

    // Look for loading states or progress indicators
    const progressElements = await page.locator('.progress, .loading, .spinner');
    console.log(`Progress indicators: ${await progressElements.count()}`);

    // Check color contrast and readability
    await page.screenshot({
      path: '/Users/aaronmiddleton/Documents/Dev/releaseit/pm-review-adhd-analysis.png',
      fullPage: true
    });
  });

  test('6. Component Interaction Testing', async () => {
    console.log('🔄 Testing Component Interactions');

    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');

    // Test hover states on cards
    const cards = await page.locator('.card, .metric-card, .workstream-card').all();
    for (let i = 0; i < Math.min(cards.length, 3); i++) {
      try {
        await cards[i].hover();
        console.log(`✅ Card ${i + 1} hover works`);
      } catch (e) {
        console.log(`⚠️ Card ${i + 1} hover failed: ${e.message}`);
      }
    }

    // Test any dropdowns or modals
    const dropdowns = await page.locator('.dropdown, [role="menu"]');
    console.log(`Dropdowns found: ${await dropdowns.count()}`);

    // Test form elements if any
    const formElements = await page.locator('input, textarea, select');
    console.log(`Form elements found: ${await formElements.count()}`);
  });

  test('7. Performance and Load Time Analysis', async () => {
    console.log('⚡ Testing Performance and Load Times');

    const startTime = Date.now();
    await page.goto('http://localhost:8001');
    await page.waitForLoadState('networkidle');
    const loadTime = Date.now() - startTime;

    console.log(`Page load time: ${loadTime}ms`);

    // Check for any JavaScript errors
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log(`❌ Console error: ${msg.text()}`);
      }
    });

    // Check for large images or assets
    const images = await page.locator('img');
    console.log(`Images found: ${await images.count()}`);
  });
});