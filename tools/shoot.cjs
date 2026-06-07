// UI screenshot tool — Playwright. Login admin lalu capture halaman.
// Usage: node tools/shoot.cjs <path1> [path2 ...]
//   node tools/shoot.cjs /admin/cleanup /admin/settings
// Output: storage/app/screenshots/<slug>.png
// Password dibaca dari .env ADMIN_PASSWORD.

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = 'http://localhost:8000';
const OUT = path.join(__dirname, '..', 'storage', 'app', 'screenshots');

function readAdminPassword() {
  const env = fs.readFileSync(path.join(__dirname, '..', '.env'), 'utf8');
  const m = env.match(/^ADMIN_PASSWORD=(.*)$/m);
  if (!m) throw new Error('ADMIN_PASSWORD not in .env');
  return m[1].trim().replace(/^["']|["']$/g, '');
}

(async () => {
  const targets = process.argv.slice(2);
  if (!targets.length) {
    console.log('No paths given. Example: node tools/shoot.cjs /admin/cleanup');
    process.exit(1);
  }

  const password = readAdminPassword();
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  // Login
  await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle', timeout: 20000 });
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {});

  const results = [];
  for (const t of targets) {
    const slug = t.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'root';
    const file = path.join(OUT, `${slug}.png`);
    try {
      const resp = await page.goto(`${BASE}${t}`, { waitUntil: 'networkidle', timeout: 20000 });
      const status = resp ? resp.status() : 0;
      await page.screenshot({ path: file, fullPage: true });
      // Detect Laravel error page
      const isError = await page.locator('text=/Whoops|Exception|SQLSTATE|Stack trace/i').count();
      results.push(`${t} -> HTTP ${status} ${isError ? 'ERROR_PAGE' : 'OK'} -> ${slug}.png`);
    } catch (e) {
      results.push(`${t} -> FAILED: ${e.message}`);
    }
  }

  await browser.close();
  console.log(results.join('\n'));
})();
