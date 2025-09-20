import { chromium } from '@playwright/test';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  console.log('Analyzing design from preview sites...\n');

  try {
    // Visit landing page
    console.log('Visiting landing page...');
    await page.goto('https://preview--releaseit-mindful-flow.lovable.app/');
    await page.waitForLoadState('networkidle');

    // Take screenshot
    await page.screenshot({ path: 'landing-page.png', fullPage: true });

    // Extract CSS variables and styling information
    const landingStyles = await page.evaluate(() => {
      const root = document.documentElement;
      const computedStyle = getComputedStyle(root);

      // Look for CSS custom properties
      const cssVars = {};
      for (let i = 0; i < computedStyle.length; i++) {
        const prop = computedStyle[i];
        if (prop.startsWith('--')) {
          cssVars[prop] = computedStyle.getPropertyValue(prop);
        }
      }

      // Get background colors of main elements
      const body = document.body;
      const nav = document.querySelector('nav');
      const cards = document.querySelectorAll('[class*="card"], [class*="Card"]');

      return {
        cssVars,
        bodyBg: getComputedStyle(body).backgroundColor,
        navBg: nav ? getComputedStyle(nav).backgroundColor : null,
        cardBgs: Array.from(cards).slice(0, 3).map(card => getComputedStyle(card).backgroundColor),
        theme: document.body.className
      };
    });

    console.log('Landing page styles:', JSON.stringify(landingStyles, null, 2));

    // Visit dashboard page
    console.log('\nVisiting dashboard page...');
    await page.goto('https://preview--releaseit-mindful-flow.lovable.app/dashboard');
    await page.waitForLoadState('networkidle');

    // Take screenshot
    await page.screenshot({ path: 'dashboard-page.png', fullPage: true });

    // Extract dashboard styling
    const dashboardStyles = await page.evaluate(() => {
      const root = document.documentElement;
      const computedStyle = getComputedStyle(root);

      // Look for CSS custom properties
      const cssVars = {};
      for (let i = 0; i < computedStyle.length; i++) {
        const prop = computedStyle[i];
        if (prop.startsWith('--')) {
          cssVars[prop] = computedStyle.getPropertyValue(prop);
        }
      }

      // Get styling from key elements
      const body = document.body;
      const nav = document.querySelector('nav');
      const cards = document.querySelectorAll('[class*="card"], [class*="Card"], [class*="bg-"]');
      const buttons = document.querySelectorAll('button');

      return {
        cssVars,
        bodyBg: getComputedStyle(body).backgroundColor,
        navBg: nav ? getComputedStyle(nav).backgroundColor : null,
        cardBgs: Array.from(cards).slice(0, 5).map(card => ({
          bg: getComputedStyle(card).backgroundColor,
          border: getComputedStyle(card).borderColor,
          borderRadius: getComputedStyle(card).borderRadius,
          className: card.className
        })),
        buttonStyles: Array.from(buttons).slice(0, 3).map(btn => ({
          bg: getComputedStyle(btn).backgroundColor,
          color: getComputedStyle(btn).color,
          border: getComputedStyle(btn).borderColor,
          borderRadius: getComputedStyle(btn).borderRadius,
          className: btn.className
        })),
        theme: document.body.className
      };
    });

    console.log('\nDashboard page styles:', JSON.stringify(dashboardStyles, null, 2));

    // Extract text content for design patterns
    const designPatterns = await page.evaluate(() => {
      // Look for consistent spacing, typography, and layout patterns
      const headings = Array.from(document.querySelectorAll('h1, h2, h3')).map(h => ({
        tag: h.tagName,
        fontSize: getComputedStyle(h).fontSize,
        fontWeight: getComputedStyle(h).fontWeight,
        color: getComputedStyle(h).color,
        margin: getComputedStyle(h).margin,
        className: h.className
      }));

      const containers = Array.from(document.querySelectorAll('[class*="container"], [class*="max-w"], main')).map(c => ({
        maxWidth: getComputedStyle(c).maxWidth,
        padding: getComputedStyle(c).padding,
        margin: getComputedStyle(c).margin,
        className: c.className
      }));

      return {
        headings,
        containers
      };
    });

    console.log('\nDesign patterns:', JSON.stringify(designPatterns, null, 2));

  } catch (error) {
    console.error('Error analyzing sites:', error.message);
  }

  await browser.close();
  console.log('\nAnalysis complete! Screenshots saved as landing-page.png and dashboard-page.png');
})();