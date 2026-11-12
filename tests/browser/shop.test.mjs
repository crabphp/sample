import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { mkdir } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';
import http from 'node:http';

const base = process.env.APP_URL;
assert.ok(base, 'Set APP_URL to an isolated seeded shop; these tests create accounts, products and orders.');
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
let browser;
before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

async function visit(t, javaScriptEnabled = false) {
  const context = await browser.newContext({ baseURL: base, javaScriptEnabled, viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
  const page = await context.newPage();
  page.setDefaultTimeout(12000);
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  t.after(async () => { await context.close(); assert.deepEqual(errors, [], 'No unexpected browser errors'); });
  return { page, context };
}
async function capture(page, name) {
  if (!process.env.SHOP_SCREENSHOTS) return;
  await mkdir(process.env.SHOP_SCREENSHOTS, { recursive: true });
  for (const colorScheme of ['light', 'dark']) {
    await page.emulateMedia({ colorScheme });
    await page.waitForTimeout(200);
    await page.screenshot({ path: `${process.env.SHOP_SCREENSHOTS}/shop-ui-${name}-${colorScheme}.png`, fullPage: true, animations: 'disabled' });
  }
  await page.emulateMedia({ colorScheme: 'light' });
}
async function login(page, email, password = 'password') {
  await page.goto('/login');
  await page.getByLabel('Email address', { exact: true }).fill(email);
  await page.getByLabel('Password', { exact: true }).fill(password);
  await Promise.all([page.waitForURL('**/cart'), page.getByRole('button', { name: 'Sign in →', exact: true }).click()]);
}
async function logout(page) {
  await page.locator('.account-menu > summary').click();
  await Promise.all([page.waitForURL(base + '/'), page.getByRole('button', { name: 'Sign out', exact: true }).click()]);
}
async function add(page, id, quantity = 1) {
  assert.equal((await page.goto('/products/' + id)).status(), 200, 'The isolated fixture must contain the requested product.');
  await page.getByLabel('Quantity', { exact: true }).fill(String(quantity));
  await Promise.all([page.waitForURL('**/cart'), page.getByRole('button', { name: /Add to cart/ }).click()]);
}
function ownOrigin() { return { Origin: base, 'Sec-Fetch-Site': 'same-origin' }; }

for (const javaScriptEnabled of [false, true]) {
  test(`Complete native shop and admin journey, JavaScript ${javaScriptEnabled ? 'on' : 'off'}`, { timeout: 180000 }, async t => {
    const { page, context } = await visit(t, javaScriptEnabled);
    const screen = async name => { if (!javaScriptEnabled) await capture(page, name); };
    let response = await page.goto('/');
    assert.equal(response.status(), 200);
    assert.match(response.headers()['content-type'], /^text\/html/);
    assert.match(await page.locator('main').innerText(), /Built with Crab\./);
    await screen('home');
    await page.goto('/products');
    await screen('catalogue');
    await page.locator('.filters [name=q]').fill('Mug');
    await page.locator('.filters [name=sort]').selectOption('price-asc');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Apply filters' }).click()]);
    assert.match(await page.locator('main').innerText(), /Stoneware Mug/);
    await page.goto('/products?category=kitchen&perPage=4');
    await page.getByRole('link', { name: '2', exact: true }).click();
    assert.equal(new URL(page.url()).searchParams.get('category'), 'kitchen');
    assert.equal(new URL(page.url()).searchParams.get('page'), '2');
    await page.goto('/products/1');
    await screen('product');
    await add(page, 1, 2);
    assert.match(await page.locator('main').innerText(), /Added to your cart/);
    assert.equal(await page.locator('[data-cart-count]').innerText(), '2');
    const guest = await session(context);
    assert.equal(guest.httpOnly, true);
    assert.equal(guest.sameSite, 'Lax');
    assert.match(sessionValues(guest).cart, /^[a-f0-9]{64}$/);
    await page.getByLabel('Quantity', { exact: true }).fill('3');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Update', exact: true }).click()]);
    assert.equal(await page.locator('[data-cart-count]').innerText(), '3');
    await screen('cart');
    await page.setViewportSize({ width: 390, height: 844 });
    await screen('cart-mobile');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Remove', exact: true }).click()]);
    assert.match(await page.locator('main').innerText(), /Your cart is waiting/);
    await screen('empty-cart');
    await add(page, 1, 2);
    await page.getByRole('link', { name: 'Continue to checkout' }).click();
    assert.equal(new URL(page.url()).pathname, '/login');
    await screen('login');
    await page.getByRole('link', { name: 'Create an account', exact: true }).click();
    await screen('register');
    const email = `browser-${randomUUID()}@example.com`;
    const password = 'browser-password';
    await page.getByLabel('Full name', { exact: true }).fill('Alex Morgan');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await Promise.all([page.waitForURL('**/cart'), page.getByRole('button', { name: 'Create account →', exact: true }).click()]);
    assert.equal(await page.locator('[data-cart-count]').innerText(), '2');
    assert.equal('cart' in sessionValues(await session(context)), false, 'The guest cart is merged and forgotten.');
    await logout(page);
    assert.equal(await page.locator('[data-cart-count]').innerText(), '0');
    await login(page, email, password);
    assert.equal(await page.locator('[data-cart-count]').innerText(), '2');
    await page.getByRole('link', { name: 'Continue to checkout' }).click();
    assert.match(await page.locator('.order-summary').innerText(), /\$28\.00/);
    const checkoutId = await page.locator('[name=checkoutId]').inputValue();
    assert.match(checkoutId, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
    await screen('checkout');
    await page.setViewportSize({ width: 390, height: 844 });
    await screen('checkout-mobile');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await Promise.all([page.waitForURL(/\/orders\/\d+$/), page.getByRole('button', { name: 'Place order →', exact: true }).click()]);
    const receipt = new URL(page.url()).pathname;
    assert.equal(await page.locator('[data-cart-count]').innerText(), '0');
    assert.match(await page.locator('[data-order-receipt]').innerText(), /Stoneware Mug/);
    await screen('order');
    const replay = await page.request.post('/checkout', { headers: ownOrigin(), form: { checkoutId }, maxRedirects: 0 });
    assert.equal(replay.status(), 303);
    assert.equal(replay.headers().location, receipt);
    const stranger = await browser.newContext({ baseURL: base, javaScriptEnabled: false });
    assert.equal((await stranger.request.get(receipt)).status(), 404);
    await stranger.close();
    await page.goto('/orders');
    assert.equal(await page.locator('tbody tr').count(), 1);
    await screen('orders');
    assert.equal((await page.goto('/admin/products')).status(), 403);
    await screen('forbidden');
    assert.equal((await page.goto('/not-a-page')).status(), 404);
    await screen('404');
    await page.goto('/products?minPrice=500&maxPrice=1');
    await screen('validation');
    // A guest asks once and gets an answer page; a signed-in shopper has a conversation instead.
    await logout(page);
    await page.goto('/assistant');
    await page.getByLabel('What are you looking for?').fill('a mug');
    if (javaScriptEnabled) {
      await page.getByRole('button', { name: /Ask the assistant/ }).click();
      // The live answer streams into the page's chat thread, with links to the products it names.
      await page.locator('.chat-turn-live a').first().waitFor();
      assert.match(await page.locator('.chat-turn-live').innerText(), /Stoneware Mug/);
      // A finished answer clears the form, so the question is asked again for the full answer.
      await page.getByLabel('What are you looking for?').fill('a mug');
      await Promise.all([page.waitForURL(/\/assistant\?reply=/), page.getByRole('button', { name: 'Read full answer', exact: true }).click()]);
    } else {
      await Promise.all([page.waitForURL(/\/assistant\?reply=/), page.getByRole('button', { name: /Ask the assistant/ }).click()]);
    }
    assert.match(await page.locator('.chat-thread').innerText(), /Stoneware Mug/);
    await screen('assistant');
    await login(page, email, password);
    await page.goto('/assistant');
    await page.getByLabel('What are you looking for?').fill('a mug');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Send message', exact: true }).click()]);
    assert.match(await page.locator('.chat-thread').innerText(), /Turn 1/i);
    await page.getByLabel('What are you looking for?').fill('another one?');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Send message', exact: true }).click()]);
    assert.match(await page.locator('.chat-thread').innerText(), /Turn 2/i);
    await logout(page);
    await login(page, 'admin@example.com');
    await page.locator('.account-menu > summary').click();
    await page.getByRole('link', { name: 'Administration', exact: true }).click();
    assert.equal(new URL(page.url()).pathname, '/admin/products');
    await screen('admin-products');
    const sku = 'UI-' + randomUUID().slice(0, 12).toUpperCase();
    await page.getByLabel('Product name', { exact: true }).fill('Studio Notebook');
    await page.getByLabel('SKU', { exact: true }).fill(sku);
    await page.getByLabel('Category', { exact: true }).fill('desk');
    await page.getByLabel('Price (cents)', { exact: true }).fill('1999');
    await page.getByLabel('Initial stock', { exact: true }).fill('50');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Create product', exact: true }).click()]);
    await page.locator('.inline-search [name=q]').fill('Studio Notebook');
    await Promise.all([page.waitForNavigation(), page.locator('.inline-search button').click()]);
    const row = page.locator('tbody tr').filter({ hasText: sku });
    await row.locator('[name=stock]').fill('77');
    // A row has a form for its stock and one for its tags.
    const stockForm = row.locator('form.stock-form');
    const stockPath = await stockForm.getAttribute('action');
    await Promise.all([page.waitForNavigation(), stockForm.getByRole('button', { name: 'Save', exact: true }).click()]);
    const productId = stockPath.match(/products\/(\d+)/)[1];
    const product = await page.request.get('/api/products/' + productId);
    assert.equal((await product.json()).stock, 77);
    await page.goto('/admin/orders');
    await screen('admin-orders');
    const ship = page.getByRole('button', { name: 'Ship order →', exact: true }).first();
    if (await ship.count()) {
      await Promise.all([page.waitForNavigation(), ship.click()]);
      assert.match(await page.locator('main').innerText(), /Order marked as shipped/);
    }
    await page.goto('/admin/reports');
    await page.getByLabel('Report date', { exact: true }).fill('2026-11-06');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Generate report', exact: true }).click()]);
    assert.match(await page.locator('main').innerText(), /Daily report generated/);
    await screen('admin-report');
    await page.goto('/');
    await page.setViewportSize({ width: 390, height: 844 });
    await screen('home-mobile');
    // Layout inspection is diagnostics; all functional steps above use native forms.
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await logout(page);
    t.diagnostic(`Completed ${receipt}, native forms, account persistence, assistant turns, and admin actions.`);
  });
}

test('Concurrent checkout requests create one order and stale forms preserve later cart items', { timeout: 45000 }, async t => {
  const { page } = await visit(t);
  const email = `race-${randomUUID()}@example.com`;
  await page.request.post('/register', { headers: ownOrigin(), form: { name: 'Race Customer', email, password: 'password' } });
  await add(page, 1, 2);
  await page.goto('/checkout');
  const checkoutId = await page.locator('[name=checkoutId]').inputValue();
  const results = await Promise.all([1, 2].map(() => page.request.post('/checkout', { headers: ownOrigin(), form: { checkoutId }, maxRedirects: 0 })));
  assert.deepEqual(results.map(result => result.status()), [303, 303]);
  assert.equal(results[0].headers().location, results[1].headers().location);
  await page.goto('/orders');
  assert.equal(await page.locator('tbody tr').count(), 1);
  await add(page, 2);
  await page.request.post('/checkout', { headers: ownOrigin(), form: { checkoutId }, maxRedirects: 0 });
  await page.goto('/cart');
  assert.equal(await page.locator('[data-cart-count]').innerText(), '1');
  await page.goto('/checkout');
  const stale = await page.locator('[name=checkoutId]').inputValue();
  await add(page, 1);
  const failure = await page.request.post('/checkout', { headers: ownOrigin(), form: { checkoutId: stale }, maxRedirects: 0 });
  assert.equal(failure.status(), 409);
  await page.goto('/cart');
  assert.equal(await page.locator('[data-cart-count]').innerText(), '2');
});

test('Stock SSE and cookie-authenticated WebSocket update the rendered fallback', { timeout: 40000 }, async t => {
  const { page } = await visit(t, true);
  const adminLogin = await page.request.post('/api/login', { data: { email: 'admin@example.com', password: 'password' } });
  const token = (await adminLogin.json()).token;
  await page.goto('/products/1');
  await page.locator('[data-stock-status]').filter({ hasText: 'connected' }).waitFor();
  await page.request.put('/api/products/1/stock', { headers: { Authorization: `Bearer ${token}` }, data: { stock: 222 } });
  await page.locator('[data-stock-value]').filter({ hasText: /^222$/ }).waitFor();
  await login(page, 'ada@example.com');
  await add(page, 1);
  await page.goto('/checkout');
  await page.getByRole('button', { name: 'Place order →', exact: true }).click();
  await page.waitForURL(/\/orders\/\d+$/);
  await page.locator('[data-order-connection]').filter({ hasText: 'connected' }).waitFor();
  let orderId = Number(new URL(page.url()).pathname.split('/').pop());
  // The sample intentionally leaves each tenth order unpaid. Exercise a paying order too.
  if (orderId % 10 === 0) {
    await add(page, 1);
    await page.goto('/checkout');
    await page.getByRole('button', { name: 'Place order →', exact: true }).click();
    await page.waitForURL(/\/orders\/\d+$/);
    orderId = Number(new URL(page.url()).pathname.split('/').pop());
    await page.locator('[data-order-connection]').filter({ hasText: 'connected' }).waitFor();
  }
  await page.waitForFunction(() => /Paid|Shipped/.test(document.querySelector('[data-order-status]').textContent), null, { timeout: 22000 });
  const shipment = await page.request.put('/api/orders/' + orderId + '/ship', { headers: { Authorization: `Bearer ${token}` } });
  assert.equal(shipment.status(), 200);
  await page.locator('[data-order-status]').filter({ hasText: 'Shipped' }).waitFor();
  t.diagnostic(`Order ${orderId} received payment and shipment updates over its live WebSocket.`);
});

test('Real foreign-origin forms are refused, including with ambient cookies', { timeout: 45000 }, async t => {
  const { page, context } = await visit(t);
  await login(page, 'ada@example.com');
  const auth = (await context.cookies()).find(cookie => cookie.name === 'auth');
  const attacker = http.createServer((req, res) => {
    const path = new URL(req.url, 'http://example.test').searchParams.get('path');
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(`<form method="post" action="${base}${path}"><input name="email" value="ada@example.com"><input name="password" value="password"><input name="checkoutId" value="${randomUUID()}"><input name="actionId" value="${randomUUID()}"><button>Send foreign form</button></form>`);
  });
  await new Promise(resolve => attacker.listen(0, '0.0.0.0', resolve));
  t.after(() => new Promise(resolve => attacker.close(resolve)));
  for (const host of ['127.0.0.1', 'localhost']) {
    for (const path of ['/login', '/logout', '/cart/1/add', '/checkout']) {
      await page.goto(`http://${host}:${attacker.address().port}/?path=${path}`);
      const [response] = await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Send foreign form' }).click()]);
      assert.equal(response.status(), 403, host + path);
    }
  }
  assert.equal((await context.cookies()).find(cookie => cookie.name === 'auth').value, auth.value);
  assert.equal((await page.goto(base + '/orders')).status(), 200);
});

test('Stored HTML remains text through catalogue, cart, checkout, receipt and admin', { timeout: 45000 }, async t => {
  const { page } = await visit(t, true);
  const auth = await page.request.post('/api/login', { data: { email: 'admin@example.com', password: 'password' } });
  const created = await page.request.post('/api/products', { headers: { Authorization: `Bearer ${(await auth.json()).token}` }, data: { sku: randomUUID().toUpperCase(), name: '<img src=x onerror="window.shopXss=1">', category: 'desk', price: 1234, stock: 100 } });
  assert.equal(created.status(), 201);
  const { id } = await created.json();
  await login(page, 'grace@example.com');
  await add(page, id);
  for (const path of ['/products?q=onerror', '/products/' + id, '/cart', '/checkout']) {
    await page.goto(path);
    assert.equal(await page.locator('img[onerror]').count(), 0);
    assert.match(await page.locator('main').innerText(), /<img src=x/);
  }
  await page.getByRole('button', { name: 'Place order →', exact: true }).click();
  await page.waitForURL(/\/orders\/\d+$/);
  assert.equal(await page.locator('img[onerror]').count(), 0);
  assert.match(await page.locator('[data-order-receipt]').innerText(), /<img src=x/);
  assert.equal(await page.evaluate(() => window.shopXss), undefined);
});

test('System themes, contrast, keyboard focus and small screens remain accessible', { timeout: 45000 }, async t => {
  const { page } = await visit(t);
  await page.goto('/');
  await page.keyboard.press('Tab');
  assert.equal(await page.locator('.skip-link').evaluate(element => element === document.activeElement), true);
  assert.notEqual(await page.locator('.skip-link').evaluate(element => getComputedStyle(element).outlineStyle), 'none');
  await page.keyboard.press('Enter');
  assert.equal(await page.evaluate(() => document.activeElement.id), 'main');
  const luminance = hex => {
    let s = hex.trim().replace('#', '');
    if (s.length === 3) s = [...s].map(c => c + c).join('');
    const rgb = [0, 2, 4].map(i => parseInt(s.slice(i, i + 2), 16) / 255).map(v => v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4);
    return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
  };
  for (const colorScheme of ['light', 'dark']) {
    await page.emulateMedia({ colorScheme });
    const tokens = await page.evaluate(() => Object.fromEntries(['text', 'muted', 'background', 'surface', 'surface-raised', 'primary', 'on-primary'].map(key => [key, getComputedStyle(document.documentElement).getPropertyValue('--' + key)])));
    for (const [text, background] of [['text', 'background'], ['muted', 'background'], ['muted', 'surface'], ['muted', 'surface-raised'], ['on-primary', 'primary']]) {
      const values = [luminance(tokens[text]), luminance(tokens[background])].sort((a, b) => b - a);
      assert.ok((values[0] + .05) / (values[1] + .05) >= 4.5, `${colorScheme}: ${text} on ${background}`);
    }
  }
  await page.setViewportSize({ width: 390, height: 844 });
  for (const path of ['/', '/products', '/products/1', '/cart', '/login', '/register', '/assistant', '/missing']) {
    await page.goto(path);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, path);
  }
});

// The shop's session cookie, which the cookie driver signs but does not hide.
async function session(context) {
    return (await context.cookies()).find(cookie => /^(__Host-)?session$/.test(cookie.name));
}

function sessionValues(cookie) {
    // A session left with nothing in it is deleted, cookie and all.
    if (cookie === undefined) return {};
    const payload = decodeURIComponent(cookie.value).split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
    return JSON.parse(Buffer.from(payload, 'base64').toString('utf8')).values;
}
