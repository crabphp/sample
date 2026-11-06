import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { mkdir } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';

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

async function createProduct(page, values = {}) {
    const login = await page.request.post('/login', { data: { email: 'admin@example.com', password: 'password' } });
    assert.equal(login.status(), 200);
    const { token } = await login.json();
    const response = await page.request.post('/products', {
        headers: { Authorization: `Bearer ${token}` },
        data: { sku: randomUUID().toUpperCase(), name: 'Browser product', category: 'browser', price: 1234, stock: 100, ...values },
    });
    assert.equal(response.status(), 201, await response.text());
    return response.json();
}

async function cart(page) {
    return page.evaluate(() => JSON.parse(localStorage.getItem('crab-shop-cart') || '[]'));
}

async function setCart(page, lines) {
    await page.evaluate(lines => localStorage.setItem('crab-shop-cart', JSON.stringify(lines)), lines);
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

test('S3: products beyond the first 50 and unavailable products stay in the cart', async t => {
    const page = await visit(t);
    await page.goto('/shop');
    // Ensure more than 50 names sort before the product we will add through the UI.
    const firstPage = await (await page.request.get('/products?perPage=50')).json();
    for (let i = firstPage.data.length; i <= 50; i++) {
        await createProduct(page, { name: `Browser filler ${i}` });
    }
    const product = await createProduct(page, { name: 'ZZZZ browser last product' });
    const first50 = await (await page.request.get('/products?perPage=50')).json();
    assert.ok(!first50.data.some(item => item.id === product.id));
    await page.goto(`/shop/products/${product.id}`);
    await page.getByRole('button', { name: 'Add to cart' }).click();
    const saved = await cart(page);
    await page.goto('/shop/cart');
    await page.waitForFunction(() => !document.querySelector('[data-cart-lines]').textContent.includes('Loading'));
    assert.deepEqual(await cart(page), saved);
    assert.ok((await page.locator('[data-cart-lines]').innerText()).includes(product.name));
    await screenshot(page, 'cart-beyond-50');

    for (const failure of ['missing', 'server', 'json', 'network']) {
        await page.route(`**/products/${product.id}`, route => {
            if (failure === 'network') return route.abort();
            return route.fulfill({
                status: failure === 'missing' ? 404 : failure === 'server' ? 500 : 200,
                contentType: 'application/json', body: failure === 'json' ? 'broken' : '{}',
            });
        });
        await page.reload();
        await page.waitForFunction(() => !document.querySelector('[data-cart-lines]').textContent.includes('Loading'));
        assert.deepEqual(await cart(page), saved, failure + ' must not delete the cart');
        const text = await page.locator('[data-cart-lines]').innerText();
        assert.match(text, failure === 'missing' ? /no longer available/ : /could not load/);
        if (failure === 'missing') assert.equal(await page.getByRole('button', { name: 'Remove', exact: true }).count(), 1);
        await page.unroute(`**/products/${product.id}`);
    }
});
