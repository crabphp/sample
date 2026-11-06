import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import { once } from 'node:events';
import { mkdtemp, rm } from 'node:fs/promises';
import net from 'node:net';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { test } from 'node:test';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = new URL('../../', import.meta.url);

for (const hostname of ['shop.checkout.test', '127.0.0.1']) {
    test(`S4: checkout works over plain HTTP at ${hostname}`, { timeout: 30000 }, async t => {
        const directory = await mkdtemp(join(tmpdir(), 'crab-http-checkout-'));
        let browser;
        let server;
        let exited;
        t.after(async () => {
            await browser?.close();
            if (server) {
                server.kill('SIGTERM');
                const kill = setTimeout(() => server.kill('SIGKILL'), 5000);
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
        const env = {
            ...process.env,
            CRAB_STORAGE: directory, DB_DSN: `sqlite:${directory}/shop.sqlite`, DB_ASYNC: 'false',
            APP_URL: base, SERVER_ADDRESS: `tcp://127.0.0.1:${port}`, SERVER_WORKERS: '1',
            CACHE_STORE: 'array', QUEUE_STORE: 'database', BROADCAST_CARRIER: 'local',
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
            } catch (_) {}
            if (server.exitCode !== null) break;
            await delay(50);
        }
        assert.ok(ready, output);

        browser = await chromium.launch({
            headless: true,
            args: ['--host-resolver-rules=MAP shop.checkout.test 127.0.0.1', '--no-proxy-server'],
        });
        const page = await browser.newPage({ baseURL: base });
        page.setDefaultTimeout(8000);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto('/shop/products/1');
        const crypto = await page.evaluate(() => ({
            secure: isSecureContext, randomUUID: typeof window.crypto.randomUUID,
            getRandomValues: typeof window.crypto.getRandomValues,
        }));
        assert.equal(crypto.secure, hostname === '127.0.0.1');
        assert.equal(crypto.randomUUID, crypto.secure ? 'function' : 'undefined');
        assert.equal(crypto.getRandomValues, 'function');
        t.diagnostic(`${base}: ${JSON.stringify(crypto)}`);
        await page.getByRole('button', { name: 'Add to cart' }).click();
        await page.goto('/shop/login');
        await page.getByLabel('Email', { exact: true }).fill('ada@example.com');
        await page.getByLabel('Password', { exact: true }).fill('password');
        await Promise.all([
            page.waitForURL(base + '/shop'),
            page.getByRole('button', { name: 'Log in', exact: true }).click(),
        ]);
        await page.goto('/shop/checkout');
        const [response] = await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }),
            page.getByRole('button', { name: 'Place order', exact: true }).click(),
        ]);
        assert.deepEqual(errors, [], 'Checkout must not throw before filling the form');
        assert.equal(response.status(), 200, await page.locator('main').innerText());
        assert.match(new URL(page.url()).pathname, /^\/shop\/orders\/\d+$/);
        const posted = response.request().redirectedFrom();
        assert.equal(posted.method(), 'POST');
        const form = new URLSearchParams(posted.postData());
        assert.deepEqual(JSON.parse(form.get('cart')), [{ productId: 1, quantity: 1 }]);
        assert.match(form.get('checkoutId'), /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
        assert.match(await page.locator('[data-order-receipt]').innerText(), /Stoneware Mug/);
        assert.equal(await page.locator('[data-cart-count]').innerText(), '0');
        assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('crab-shop-cart'))), []);
        assert.equal(await page.evaluate(() => sessionStorage.getItem('crab-shop-checkout')), null);

        // Even an unexpected preparation failure must not send an empty form.
        await page.goto('/shop/products/1');
        await page.getByRole('button', { name: 'Add to cart' }).click();
        await page.goto('/shop/checkout');
        await page.evaluate(() => {
            window.crypto.getRandomValues = () => { throw new Error('Random source unavailable'); };
        });
        const posts = [];
        page.on('request', request => { if (request.method() === 'POST') posts.push(request.url()); });
        await page.getByRole('button', { name: 'Place order', exact: true }).click();
        await page.getByRole('alert').waitFor();
        assert.match(await page.getByRole('alert').innerText(), /Your cart has been kept/);
        assert.deepEqual(posts, [], 'Preparation failure must cancel the native submission');
        assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('crab-shop-cart'))), [{ productId: 1, quantity: 1 }]);
        assert.deepEqual(errors, []);
    });
}
