# Shop browser regressions

Run against an isolated shop started with `php cua dev`, after migrations and
`shop:seed`. The tests use seeded accounts and create products and orders, so use
a separate `CRAB_STORAGE`, Redis prefix and free HTTP port. Match `APP_URL` to
`SERVER_ADDRESS` in the server environment. Set `LOGIN_LIMIT=1000` for repeated
test runs.

Install Playwright and its Chromium browser in your test environment, then run:

```sh
APP_URL=http://127.0.0.1:8080 node --test tests/browser/shop.test.mjs
```

`PLAYWRIGHT_MODULE` may point to an existing Playwright `index.mjs` outside the
repository. Set `SHOP_SCREENSHOTS` to an output directory to capture the fixed
flows. The PHP suite remains available without Node or a browser via `composer test`.
