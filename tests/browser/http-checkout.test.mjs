import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import { once } from 'node:events';
import { mkdtemp, rm } from 'node:fs/promises';
import net from 'node:net';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { test } from 'node:test';
import { randomUUID } from 'node:crypto';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = new URL('../../', import.meta.url);
assert.ok(process.env.SHOP_TEST_REDIS_URL, 'Use an isolated Redis container through SHOP_TEST_REDIS_URL.');

for (const hostname of ['shop.checkout.test', '127.0.0.1']) {
  test(`Native checkout over plain HTTP at ${hostname}, with and without JavaScript`, { timeout: 45000 }, async t => {
    const directory = await mkdtemp(join(tmpdir(), 'crab-http-checkout-'));
    let browser;
    let server;
    let exited;
    t.after(async () => {
      await browser?.close();
      if (server) {
        server.kill('SIGTERM');
        const kill = setTimeout(() => server.kill('SIGKILL'), 7000);
        try { await exited; } finally { clearTimeout(kill); }
      }
      await rm(directory, { recursive: true, force: true });
    });
    const listener = net.createServer();
    listener.listen(0, '127.0.0.1');
    await once(listener, 'listening');
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    const base = `http://${hostname}:${port}`;
    const prefix = 'shop-http-checkout:' + randomUUID() + ':';
    const env = {
      ...process.env,
      CRAB_STORAGE: directory, DB_DSN: `sqlite:${directory}/shop.sqlite`, DB_ASYNC: 'false',
      APP_URL: base, SERVER_ADDRESS: `tcp://127.0.0.1:${port}`, SERVER_WORKERS: '1',
      CACHE_STORE: 'array', QUEUE_STORE: 'database', BROADCAST_CARRIER: 'redis',
      REDIS_URL: process.env.SHOP_TEST_REDIS_URL, REDIS_PREFIX: prefix, BROADCAST_PREFIX: prefix,
      LOG_PATH: join(directory, 'app.log'), MAIL_PATH: join(directory, 'mail'),
      LOGIN_LIMIT: '1000',
    };
    for (const command of [['migrate'], ['shop:seed', '--orders=1']]) {
      execFileSync('php', ['cua', ...command], { cwd: root, env });
    }
    server = spawn('php', ['cua', 'start', '--single'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'] });
    exited = once(server, 'exit');
    let output = '';
    server.stdout.on('data', data => { output += data; });
    server.stderr.on('data', data => { output += data; });
    let ready = false;
    for (let attempt = 0; attempt < 100; attempt++) {
      try {
        const response = await fetch(`http://127.0.0.1:${port}/up`, { signal: AbortSignal.timeout(500) });
        await response.text();
        if (response.ok) { ready = true; break; }
      } catch {}
      if (server.exitCode !== null) break;
      await delay(50);
    }
    assert.ok(ready, output);
    browser = await chromium.launch({ headless: true, args: ['--host-resolver-rules=MAP shop.checkout.test 127.0.0.1', '--no-proxy-server'] });
    for (const javaScriptEnabled of [false, true]) {
      const context = await browser.newContext({ baseURL: base, javaScriptEnabled });
      const page = await context.newPage();
      page.setDefaultTimeout(8000);
      await page.goto('/products/1');
      await page.getByRole('button', { name: /Add to cart/ }).click();
      await page.waitForURL('**/cart');
      await page.goto('/login');
      await page.getByLabel('Email address').fill('ada@example.com');
      await page.getByLabel('Password', { exact: true }).fill('password');
      await page.getByRole('button', { name: 'Sign in →', exact: true }).click();
      await page.waitForURL('**/cart');
      await page.goto('/checkout');
      const id = await page.locator('[name=checkoutId]').inputValue();
      assert.match(id, /^[a-f0-9-]{36}$/);
      if (javaScriptEnabled) {
        await page.evaluate(() => { window.crypto.getRandomValues = () => { throw new Error('No browser randomness'); }; });
      }
      const [response] = await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Place order →', exact: true }).click()]);
      assert.equal(response.status(), 200);
      assert.match(new URL(page.url()).pathname, /^\/orders\/\d+$/);
      const posted = response.request().redirectedFrom();
      assert.equal(posted.method(), 'POST');
      assert.equal(new URLSearchParams(posted.postData()).get('checkoutId'), id);
      assert.equal(await page.locator('[data-cart-count]').innerText(), '0');
      assert.match(await page.locator('[data-order-receipt]').innerText(), /Stoneware Mug/);
      await context.close();
    }
  });
}
