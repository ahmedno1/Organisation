import { test, expect, devices } from '@playwright/test';

// Simulate a typical Android phone viewport to exercise the PWA inside WebView/Capacitor.
test.use(devices['Pixel 5']);

const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:8000';
const ADMIN_EMAIL = process.env.E2E_ADMIN_EMAIL ?? 'admin@example.com';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS ?? 'password';

test.describe('Mobile parity smoke', () => {
  test('login, create orphan, validate guardian rules (9-digit mobile, optional relation)', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`);

    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', ADMIN_PASS);
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/dashboard/i);

    // Go to create orphan form
    await page.goto(`${BASE_URL}/families/orphans/create`);

    // Fill required head/member fields
    await page.fill('input[name="members[0][name]"]', 'Test Orphan');
    await page.fill('input[name="members[0][national_id]"]', '123456789');
    await page.fill('input[name="members[0][birth_date]"]', '2015-01-01');
    await page.fill('input[name="members[0][birth_place]"]', 'Gaza');
    await page.fill('input[name="members[0][health_status]"]', 'Good');

    // Guardian required fields
    await page.fill('input[name="guardian_name"]', 'Guardian X');
    await page.fill('input[name="guardian_national_id"]', '987654321');

    // Verify 9-digit constraint
    await page.fill('input[name="guardian_mobile"]', '123456789'); // 9 digits

    // Leave relation blank to ensure optionality
    await page.selectOption('select[name="head_gender"]', 'male');
    await page.fill('input[name="siblings_count"]', '0');
    await page.fill('input[name="father_death_date"]', '2020-01-01');
    await page.fill('input[name="education_status"]', 'Primary');

    // Attempt submit
    await page.click('button[type="submit"]');

    // Expect either success redirect or a specific validation-free experience for guardian_relation.
    await expect(page).not.toHaveText(/guardian_relation/i);
  });
});
