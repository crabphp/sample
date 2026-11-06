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

async function login(page) {
    await page.goto('/shop/login');
    await page.getByLabel('Email', { exact: true }).fill('ada@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await Promise.all([
        page.waitForURL(base + '/shop'),
        page.getByRole('button', { name: 'Log in', exact: true }).click(),
    ]);
}

async function placeOrder(page) {
    await Promise.all([
        page.waitForURL(url => /^\/shop\/orders\/\d+$/.test(url.pathname)),
        page.getByRole('button', { name: 'Place order', exact: true }).click(),
    ]);
    await page.waitForLoadState('load');
    return new URL(page.url()).pathname;
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

test('S2: only a successful checkout removes the submitted purchase, once', async t => {
    const page = await visit(t);
    await page.goto('/shop');
    const product = await createProduct(page);
    const extra = await createProduct(page, { name: 'Later shopping' });
    const purchase = [{ productId: product.id, quantity: 2 }];
    await setCart(page, purchase);
    await login(page);
    assert.deepEqual(await cart(page), purchase, 'Login preserves the cart');
    await page.goto('/shop/checkout');
    const receipt = await placeOrder(page);
    assert.deepEqual(await cart(page), []);
    assert.equal(await page.locator('[data-cart-count]').innerText(), '0');
    await screenshot(page, 'receipt');
    await page.goto('/shop/cart');
    await page.getByText('Your cart is empty.', { exact: false }).waitFor();

    const later = [{ productId: product.id, quantity: 1 }, { productId: extra.id, quantity: 1 }];
    await setCart(page, later);
    await page.goto(receipt);
    await page.reload();
    assert.deepEqual(await cart(page), later, 'Old receipts never empty a new cart');

    await setCart(page, purchase);
    await page.goto('/shop/checkout');
    // Another tab adds more of the same product and a different product during checkout.
    const otherTab = await page.context().newPage();
    await otherTab.goto('/shop');
    await page.route('**/shop/checkout', async route => {
        if (route.request().method() === 'POST') {
            await setCart(otherTab, [{ productId: product.id, quantity: 3 }, { productId: extra.id, quantity: 1 }]);
        }
        await route.continue();
    });
    await placeOrder(page);
    assert.deepEqual(await cart(page), later, 'Only purchased quantities are removed');
    await page.unroute('**/shop/checkout');

    const failed = purchase;
    await setCart(page, failed);
    await page.goto('/shop/checkout');
    await page.route('**/shop/checkout', route => {
        const form = new URLSearchParams(route.request().postData());
        form.set('cart', '[{"productId":2147483647,"quantity":1}]');
        return route.continue({ postData: form.toString() });
    });
    const [response] = await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.getByRole('button', { name: 'Place order', exact: true }).click(),
    ]);
    assert.equal(response.status(), 422);
    await page.unroute('**/shop/checkout');
    assert.deepEqual(await cart(page), failed, 'Failed checkout preserves the cart');
    await page.goto(receipt);
    assert.deepEqual(await cart(page), failed, 'A failed attempt does not make an old receipt a success');
    await page.goto('/shop');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.getByRole('button', { name: 'Log out' }).click(),
    ]);
    assert.deepEqual(await cart(page), failed, 'Logout preserves the cart');
});

test('P3 errors: validation and stock errors retain shop HTML and the cart', async t => {
    const page = await visit(t);
    await page.goto('/shop/login');
    await page.getByLabel('Email', { exact: true }).fill('ada@example.com');
    await page.getByLabel('Password', { exact: true }).evaluate(input => input.removeAttribute('maxlength'));
    const password = 'private-password'.repeat(5);
    await page.getByLabel('Password', { exact: true }).fill(password);
    const [invalid] = await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.getByRole('button', { name: 'Log in', exact: true }).click(),
    ]);
    assert.equal(invalid.status(), 422);
    assert.match(invalid.headers()['content-type'], /text\/html/);
    assert.equal(await page.getByRole('navigation', { name: 'Shop', exact: true }).count(), 1);
    assert.ok(!(await page.content()).includes(password));
    await screenshot(page, 'login-validation');
    await login(page);

    const name = '<img src=x onerror="window.shopXss=1">';
    const product = await createProduct(page, { name, stock: 0 });
    const saved = [{ productId: product.id, quantity: 1 }];
    await setCart(page, saved);
    await page.goto('/shop/checkout');
    const [conflict] = await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }),
        page.getByRole('button', { name: 'Place order', exact: true }).click(),
    ]);
    assert.equal(conflict.status(), 409);
    assert.match(conflict.headers()['content-type'], /text\/html/);
    assert.equal(await page.getByRole('navigation', { name: 'Shop', exact: true }).count(), 1);
    assert.ok((await page.getByRole('alert').innerText()).includes(name));
    assert.ok((await page.locator('body').innerText()).includes('Logged in as Ada'));
    assert.equal(await page.locator('[onerror]').count(), 0);
    assert.deepEqual(await cart(page), saved);
    await screenshot(page, 'checkout-conflict');
});

test('P3 pagination: page links meet normal-text contrast on their actual backgrounds', async t => {
    const page = await visit(t);
    await page.goto('/shop');
    const colours = await page.locator('.pagination a, .topbar nav a').evaluateAll(links => links.map(link => {
        let background = 'rgb(255, 255, 255)';
        for (let element = link; element; element = element.parentElement) {
            const value = getComputedStyle(element).backgroundColor;
            if (value !== 'rgba(0, 0, 0, 0)' && value !== 'transparent') { background = value; break; }
        }
        return { label: link.textContent, foreground: getComputedStyle(link).color, background };
    }));
    const luminance = colour => {
        const rgb = colour.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => {
            value /= 255;
            return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
        });
        return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
    };
    assert.ok(await page.locator('.pagination a:not(.current)').count());
    for (const colour of colours) {
        const values = [luminance(colour.foreground), luminance(colour.background)].sort((a, b) => b - a);
        const contrast = (values[0] + 0.05) / (values[1] + 0.05);
        assert.ok(contrast >= 4.5, `${colour.label}: contrast ${contrast.toFixed(2)} must be at least 4.5`);
    }
    await screenshot(page, 'pagination');
});

test('P3 summary: checkout reviews quantities, unit prices, line totals and the total before ordering', async t => {
    const page = await visit(t);
    await login(page);
    const first = await createProduct(page, { name: '<b>Summary mug</b>', price: 1234 });
    const second = await createProduct(page, { name: 'Summary towel', price: 505 });
    const saved = [{ productId: first.id, quantity: 2 }, { productId: second.id, quantity: 3 }];
    await setCart(page, saved);
    await page.goto('/shop/checkout');
    const summary = page.getByRole('region', { name: 'Order summary', exact: true });
    assert.equal(await summary.count(), 1, 'Checkout must show a cart summary');
    const button = page.getByRole('button', { name: 'Place order', exact: true });
    await page.waitForFunction(() => !document.querySelector('[data-checkout-form] button').disabled);
    const lines = await summary.locator('[data-checkout-line]').allTextContents();
    assert.equal(lines.length, 2);
    assert.match(lines[0], /2 × <b>Summary mug<\/b>/);
    assert.match(lines[0], /Unit price: \$ 12\.34/);
    assert.match(lines[0], /\$ 24\.68/);
    assert.match(lines[1], /3 × Summary towel/);
    assert.match(lines[1], /Unit price: \$ 5\.05/);
    assert.match(lines[1], /\$ 15\.15/);
    assert.equal(await summary.locator('b').count(), 0, 'Product names are text');
    assert.equal(await page.locator('[data-checkout-total]').innerText(), '$ 39.83');
    const summaryBox = await summary.boundingBox();
    const buttonBox = await button.boundingBox();
    assert.ok(summaryBox.y + summaryBox.height <= buttonBox.y);
    await screenshot(page, 'checkout-summary');
    await page.setViewportSize({ width: 390, height: 844 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    await screenshot(page, 'checkout-summary-mobile');

    // A changed cart must be reviewed before it can be submitted, including changes without a storage event.
    const changed = [{ productId: first.id, quantity: 4 }, saved[1]];
    await setCart(page, changed);
    let posts = 0;
    page.on('request', request => { if (request.method() === 'POST') posts++; });
    await button.click();
    await page.waitForFunction(() => document.querySelector('[data-checkout-total]')?.textContent === '$ 64.51');
    assert.equal(posts, 0);
    const otherTab = await page.context().newPage();
    await otherTab.goto('/shop');
    await setCart(otherTab, saved);
    await page.waitForFunction(() => document.querySelector('[data-checkout-total]')?.textContent === '$ 39.83');

    for (const status of [500, 404]) {
        await page.route(`**/products/${first.id}`, route => route.fulfill({ status, body: '{}' }));
        await page.reload();
        await summary.getByRole('alert').waitFor();
        assert.equal(await button.isDisabled(), true);
        assert.equal(await page.locator('[data-checkout-total]').count(), 0);
        assert.deepEqual(await cart(page), saved);
        await page.unroute(`**/products/${first.id}`);
    }
    await setCart(page, []);
    await page.reload();
    await summary.getByText('Your cart is empty.', { exact: false }).waitFor();
    assert.equal(await button.isDisabled(), true);
});
