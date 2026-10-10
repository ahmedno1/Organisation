import { test } from 'playwright/test';
import fs from 'fs';
import path from 'path';

const BASE_URL = 'http://127.0.0.1:8000';
const OUTPUT_DIR = path.join(process.cwd(), 'artifacts', 'ui-audit');

const viewports = [
  { name: '320', width: 320, height: 900 },
  { name: '375', width: 375, height: 900 },
  { name: '768', width: 768, height: 1024 },
  { name: '1024', width: 1024, height: 900 },
  { name: '1440', width: 1440, height: 1000 },
];

const locales = [
  { key: 'ar', switchPath: '/locale/ar' },
  { key: 'en', switchPath: '/locale/en' },
];

const pages = [
  { name: 'dashboard', path: '/dashboard' },
  { name: 'families', path: '/families' },
  { name: 'families-create', path: '/families/create' },
  { name: 'orphans', path: '/orphans' },
  { name: 'orphans-create', path: '/orphans/create' },
  { name: 'members-search', path: '/family-members/search' },
  { name: 'reports', path: '/reports' },
  { name: 'benefits', path: '/benefits' },
  { name: 'messages', path: '/messages' },
  { name: 'activity-logs', path: '/activity-logs' },
  { name: 'sessions', path: '/sessions' },
  { name: 'notifications', path: '/notifications' },
  { name: 'offline-queue', path: '/offline-queue' },
  { name: 'data-entry-users', path: '/admin/data-entry-users' },
  { name: 'data-entry-users-create', path: '/admin/data-entry-users/create' },
  { name: 'security', path: '/admin/security/password' },
];

function ensureDir(dirPath) {
  fs.mkdirSync(dirPath, { recursive: true });
}

async function loginAsAdmin(page) {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'password123');
  await Promise.all([
    page.waitForURL('**/dashboard', { timeout: 15000 }),
    page.click('button[type="submit"]'),
  ]);
}

async function collectOverflow(page) {
  return page.evaluate(() => {
    const items = [];
    const all = Array.from(document.querySelectorAll('*'));
    for (const el of all) {
      const style = window.getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      const sw = el.scrollWidth;
      const cw = el.clientWidth;
      if (sw > cw + 2) {
        items.push({
          tag: el.tagName.toLowerCase(),
          className: el.className || '',
          id: el.id || '',
          scrollWidth: sw,
          clientWidth: cw,
          overflowX: style.overflowX,
          text: (el.textContent || '').trim().slice(0, 120),
        });
      }
      if (items.length >= 40) break;
    }
    return {
      docClientWidth: document.documentElement.clientWidth,
      docScrollWidth: document.documentElement.scrollWidth,
      bodyClientWidth: document.body?.clientWidth ?? null,
      bodyScrollWidth: document.body?.scrollWidth ?? null,
      overflows: items,
    };
  });
}

test('Full UI screenshots and overflow audit', async ({ browser }) => {
  test.setTimeout(0);
  ensureDir(OUTPUT_DIR);
  const report = [];

  for (const locale of locales) {
    for (const vp of viewports) {
      const context = await browser.newContext({
        viewport: { width: vp.width, height: vp.height },
      });
      const page = await context.newPage();
      const bucketDir = path.join(OUTPUT_DIR, locale.key, vp.name);
      ensureDir(bucketDir);

      try {
        await loginAsAdmin(page);
      } catch (e) {
        const failPath = path.join(bucketDir, 'login-failed.png');
        try {
          await page.screenshot({ path: failPath, fullPage: true });
        } catch (_) {
          // ignore screenshot failures when page/context is already closed
        }
        report.push({
          locale: locale.key,
          viewport: vp.name,
          page: 'login',
          error: `Login failed: ${String(e.message || e)}`,
        });
        await context.close();
        continue;
      }

      await page.goto(`${BASE_URL}${locale.switchPath}`, { waitUntil: 'networkidle' });

      for (const p of pages) {
        const screenshotPath = path.join(bucketDir, `${p.name}.png`);
        const entry = {
          locale: locale.key,
          viewport: vp.name,
          page: p.name,
          path: p.path,
          screenshot: screenshotPath,
        };
        try {
          await page.goto(`${BASE_URL}${p.path}`, { waitUntil: 'networkidle', timeout: 20000 });
          await page.waitForTimeout(350);
          await page.screenshot({ path: screenshotPath, fullPage: true });
          entry.overflow = await collectOverflow(page);
          entry.title = await page.title();
          entry.url = page.url();
        } catch (e) {
          entry.error = String(e.message || e);
          await page.screenshot({ path: screenshotPath.replace('.png', '-error.png'), fullPage: true });
        }
        report.push(entry);
      }

      await context.close();
    }
  }

  fs.writeFileSync(
    path.join(OUTPUT_DIR, 'report.json'),
    JSON.stringify(report, null, 2),
    'utf8'
  );
});
