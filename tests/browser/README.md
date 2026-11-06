# Browser verification

The shop is usable without JavaScript. These tests drive actual native forms
with Chromium scripting disabled, then repeat the complete journey with it
enabled. They cover guest carts, registration and login merges, updates and
removal, account persistence, checkout and duplicate submissions, receipts,
private order history, administrator actions, assistant fallback and actor
conversations. Additional cases check concurrent checkout requests, SSE stock,
WebSocket status changes, stored HTML escaping and real foreign-origin forms.

Use an **isolated**, migrated, seeded shop with its own Redis container and
`APP_URL` matching `SERVER_ADDRESS`. The tests create data. Never point them at
your normal development shop. Node and Playwright are verification tools only;
the application has no frontend package manager or build step.

```sh
APP_URL=http://127.0.0.1:48000 node --test tests/browser/shop.test.mjs
```

`assistant.test.mjs` focuses on the home page box, `/assistant/stream`, saved
answers, and two signed-in conversation turns with JavaScript on and off. Run
the server with `BROADCAST_CARRIER=redis` so the conversation goes through its
actors workers. Leave `MODEL_URL` unset to test the application's default model
address; the runtime helper does this too.

```sh
APP_URL=http://127.0.0.1:48000 node --test tests/browser/assistant.test.mjs
```

Set `PLAYWRIGHT_MODULE` to an existing Playwright `index.mjs` if it is installed
outside this repository. `SHOP_SCREENSHOTS` selects an output folder: the
JavaScript-disabled journey captures the main pages in light and dark themes,
including mobile views, with a `shop-ui-` prefix.

The optional helper creates a full isolated development process group and a
Redis container through Podman. It records its environment in ignored storage.
The server runs in the foreground; use another terminal for the browser tests.
After stopping **this helper's** server with Ctrl-C, remove its Redis container.
The user's existing server is never stopped or reconfigured.

```sh
python3 tests/browser/runtime.py init
python3 tests/browser/runtime.py serve
# In another terminal:
python3 tests/browser/runtime.py browser
python3 tests/browser/runtime.py redis-tests
# Stop the helper's server, then:
python3 tests/browser/runtime.py cleanup
```

`http-checkout.test.mjs` starts two short-lived HTTP workers with separate
SQLite databases and isolated Redis prefixes, one for an ordinary HTTP
hostname and one for loopback. Each completes checkout with scripting disabled
and enabled. The enabled case deliberately disables browser randomness:
checkout identifiers come from PHP, so this does not affect submission. Point
it at the same isolated test Redis container before cleanup:

```sh
SHOP_TEST_REDIS_URL=redis://127.0.0.1:48001 node --test tests/browser/http-checkout.test.mjs
```

The PHP suite, including form actions, authentication, administration and
escaping tests, needs no Node or browser: `composer test`.
