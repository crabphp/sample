import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { mkdir } from 'node:fs/promises';

// Point this at an isolated, seeded shop: these tests create products and orders.
const base = process.env.APP_URL;
assert.ok(base, 'Set APP_URL to the isolated shop running php cua dev.');
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
let browser;
before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function visit(t) {
    const context = await browser.newContext({ baseURL: base, viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    page.setDefaultTimeout(8000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    t.after(async () => {
        await context.close();
        assert.deepEqual(errors, [], 'No browser JavaScript errors');
    });
    return page;
}

async function screenshot(page, name) {
    if (!process.env.SHOP_SCREENSHOTS) return;
    await mkdir(process.env.SHOP_SCREENSHOTS, { recursive: true });
    await page.screenshot({ path: `${process.env.SHOP_SCREENSHOTS}/${name}.png`, fullPage: true });
}

test('S1: actual filter submissions preserve search, category, colour and default results', async t => {
    const page = await visit(t);
    for (const filters of [{ q: 'Mug' }, { category: 'kitchen' }, { colour: 'blue' }, {}]) {
        await page.goto('/shop?' + new URLSearchParams(filters));
        const expected = await page.locator('.card h2').allTextContents();
        assert.ok(expected.length > 0 && !expected.includes('No products found'));
        if (filters.q) assert.ok(expected.includes('Stoneware Mug'));
        await page.goto('/shop');
        for (const [field, value] of Object.entries(filters)) {
            const input = page.locator(`[name=${field}]`);
            if (field === 'q') await input.fill(value); else await input.selectOption(value);
        }
        await Promise.all([
            page.waitForURL(url => url.searchParams.has('maxPrice')),
            page.getByRole('button', { name: 'Apply filters' }).click(),
        ]);
        assert.deepEqual(await page.locator('.card h2').allTextContents(), expected);
    }
    await screenshot(page, 'filter');
});
