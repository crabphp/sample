import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { randomUUID } from 'node:crypto';

const base = process.env.APP_URL;
assert.ok(base, 'Set APP_URL to an isolated seeded shop with Redis and the actors server running.');
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
let browser;
before(async () => { browser = await chromium.launch({ headless: true }); });
after(async () => { await browser?.close(); });

function assertStream(text) {
  const frames = text.trim().split('\n\n');
  const messages = frames.map(frame => JSON.parse(frame.split('\n').find(line => line.startsWith('data: ')).slice(6)));
  const tokens = messages.filter(message => typeof message.token === 'string');
  assert.ok(tokens.length > 1, 'The model answer arrives as multiple tokens.');
  assert.match(tokens.map(message => message.token).join(''), /Stoneware Mug/);
  assert.match(frames.at(-1), /^event: done\n/);
  assert.ok(messages.at(-1).products.length > 0);
}

for (const javaScriptEnabled of [false, true]) {
  test(`Home assistant, stream and Redis actor conversation, JavaScript ${javaScriptEnabled ? 'on' : 'off'}`, { timeout: 60000 }, async t => {
    const context = await browser.newContext({ baseURL: base, javaScriptEnabled });
    const page = await context.newPage();
    page.setDefaultTimeout(12000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    t.after(async () => { await context.close(); assert.deepEqual(errors, []); });

    // The stream endpoint also remains usable when the page's JavaScript is disabled.
    const stream = await context.request.post('/assistant/stream', {
      headers: { Origin: base, 'Sec-Fetch-Site': 'same-origin', Accept: 'text/event-stream' },
      form: { question: 'a mug' }
    });
    assert.equal(stream.status(), 200);
    assert.match(stream.headers()['content-type'], /^text\/event-stream/);
    assertStream(await stream.text());

    for (const path of ['/', '/assistant']) {
      assert.equal((await page.goto(path)).status(), 200);
      await page.getByLabel('What are you looking for?').fill('a mug');
      const ask = page.getByRole('button', { name: /Ask the assistant/ });
      if (javaScriptEnabled) {
        const [answer] = await Promise.all([
          page.waitForResponse(response => response.url() === base + '/assistant/stream' && response.request().method() === 'POST'),
          ask.click()
        ]);
        assert.equal(answer.status(), 200);
        assertStream(await answer.text());
        await page.locator('[data-assistant-answer] a').first().waitFor();
        assert.match(await page.locator('[data-assistant-answer]').innerText(), /Stoneware Mug/);
        assert.equal(new URL(page.url()).pathname, path, 'The answer stays in the box on this page.');
        await Promise.all([
          page.waitForURL(/\/assistant\?reply=/),
          page.getByRole('button', { name: 'Read full answer', exact: true }).click()
        ]);
      } else {
        await Promise.all([page.waitForURL(/\/assistant\?reply=/), ask.click()]);
      }
      assert.match(await page.locator('.conversation').innerText(), /Stoneware Mug/);
    }

    await page.goto('/register');
    await page.getByLabel('Full name', { exact: true }).fill('Assistant Customer');
    await page.getByLabel('Email address', { exact: true }).fill(`assistant-${randomUUID()}@example.com`);
    await page.getByLabel('Password', { exact: true }).fill('assistant-password');
    await Promise.all([
      page.waitForURL('**/cart'),
      page.getByRole('button', { name: 'Create account →', exact: true }).click()
    ]);
    await page.goto('/');
    for (const [question, turn] of [['a mug', 1], ['next please', 2]]) {
      await page.getByLabel('What are you looking for?').fill(question);
      const [response] = await Promise.all([
        page.waitForNavigation(),
        page.getByRole('button', { name: 'Continue my conversation', exact: true }).click()
      ]);
      assert.equal(response.status(), 200);
      assert.match(page.url(), /\/assistant\?reply=/);
      const answer = await page.locator('.conversation').innerText();
      assert.match(answer, new RegExp('Turn ' + turn, 'i'));
      if (turn === 1) {
        assert.match(answer, /Stoneware Mug/);
      } else {
        assert.doesNotMatch(answer, /Stoneware Mug/);
        const products = await page.locator('.product-card h3').allTextContents();
        assert.ok(products.length > 0, 'The next turn suggests products not already shown.');
        for (const name of products) assert.ok(answer.includes(name.trim()));
        for (const category of await page.locator('.product-card .eyebrow').allTextContents()) {
          assert.equal(category.trim(), 'kitchen', 'The actor remembers the conversation’s shelf.');
        }
      }
    }
    t.diagnostic('Stream tokens, home and assistant forms, saved answers, and two personal conversation turns passed.');
  });
}
