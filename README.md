# Crab Shop

A complete server-rendered store on the [Crab framework](../framework), with an Arc interface, a JSON API and working background services. The site uses the released framework **v0.6.2** and Arc **v0.1.1** through the sibling path repositories. Payment and warehouse fulfilment remain simulated; the checkout states this clearly and never collects payment details.

It is not the skeleton to start a project from; that is [`crab`](../crab).

## Running it

Use PHP 8.4 with `mbstring`, `pdo_sqlite`, `pcntl`, `posix` and `simplexml`. Keep the `framework`, `arc`, `runs`, `actors`, `redis`, `s3`, `clockwerk` and `openapi` package folders beside this application for Composer's path repositories. The database is SQLite, so none needs installing, but `.env.example` carries broadcasts and actors over Redis, at `redis://127.0.0.1:6379`: start one (`docker run -d -p 6379:6379 redis:7-alpine`). `php cua start` needs it: the queue and the scheduler run in processes of their own, and their broadcasts reach the sockets only through Redis, so the local carrier is refused. The tests run without it.

```sh
set -e
composer install
cp .env.example .env
php cua migrate
php cua shop:seed
```

Then one command runs it all: the API on <http://127.0.0.1:8080>, the queue's workers for the jobs orders leave behind, and the scheduler for what runs by the clock. Each runs because its plugin is listed in `app.php`, as do the customers' assistants, in the `actors` server since the broadcast carrier is Redis, and the dispatch desk `app/Setup.php` registers; an application that lists none of them serves HTTP alone.

```sh
php cua dev
```

And, in another terminal, for something to look at:

```sh
php cua shop:traffic     # thirty seconds of pretend customers
```

Open the dashboard at <http://127.0.0.1:8080/clockwerk?token=sample>.

## The shop

Open <http://127.0.0.1:8080/>. The shop is the main site:

| Page | What you can do |
| --- | --- |
| `/` | Featured products and categories, live stock, a shopping assistant, your latest order, and links to the Crab features behind the store |
| `/products` | Search; filter by category, colour and price in dollars (`12` or `12.50`); sort by name, price or newest; page through results |
| `/products/{id}` | Product details, current stock and an add-to-cart form |
| `/cart`, `/checkout` | Change quantities, remove items, review the server-rendered summary and place an order |
| `/orders`, `/orders/{id}` | Your order history and private receipts; only the owner can read a receipt, including when the other visitor is an administrator |
| `/login`, `/register` | Sign in or create a customer account; use the account menu to sign out |
| `/assistant` | Ask for product suggestions; signed-in customers can continue their actor-backed conversation |
| `/admin/products` | Administrators can create products and update stock |
| `/admin/orders`, `/admin/reports` | Administrators can ship paid orders and generate or read a daily report |

Catalogue price filters accept dollars with up to two decimal places, from
`0` to `9999999.99`. An empty bound leaves that end open. The server validates
the input and converts it exactly to cents; filter values and pagination
links stay in dollars. `/api/products` continues to accept integer cents.

Old `/shop/...` links redirect permanently with **301**. `/shop` redirects to
`/`; catalogue filters on `/shop?...` redirect to `/products?...`. The JSON API
is entirely under `/api`. `/up` and `/clockwerk` keep their original paths.

All shopping, account and administrator actions use native HTML forms and
POST/redirect/GET. JavaScript is optional: it adds live stock through SSE,
streamed assistant answers, and order status updates through a same-origin
CookieAuth WebSocket. Without it, stock and order status are server-rendered
and have refresh links; assistant forms wait for the answer and redirect to a
private result page. Personal conversations work through normal forms too.
The model is the existing deterministic product assistant, not an external AI
service. Its actor forgets after a minute of inactivity; saved answer pages
expire after one day.

The interface uses self-hosted **Geist Sans** and **Geist Mono**, a single
hand-written stylesheet, monochrome design tokens, 1px borders, 6–8px radii,
visible focus rings and system-selected light/dark themes. All controls,
layouts, cards, tables, empty states, flashes and pagination are Arc components.
Product artwork is local SVG. There is no CDN or frontend build step. Font
files carry the SIL OFL in `resources/assets/OFL.txt`. Fonts, CSS and optional
JavaScript are served by the application under `/assets/shop-v1/` with a one-year
immutable cache; change that version when deploying changed assets.

### Carts, cookies and feedback

Carts are application records in the database, **not sessions**. A signed-in
cart is keyed to its user. A guest receives a cryptographically random 256-bit
cart ID in the `shop_cart` HttpOnly, SameSite=Lax cookie, valid for 30 days.
Signing in or registering merges guest quantities into the account cart in a
transaction, deletes the guest cart and expires its cookie. Every merged item
is retained; if the combined quantities exceed the order's 20-product or
20-per-product limit, edit the cart before checking out. Signing out hides the
account cart; it remains available on the next login, including on another
device. Expired guest carts are cleaned daily.

Checkout uses a server-generated UUID in the existing `checkoutId` field. The
cart lock, order, stock decrement, cart consumption and saved checkout result
commit in **one transaction**. Repeated or concurrent submissions return the
same receipt. A changed cart invalidates an older checkout form; replaying a
successful form never consumes items added later. Add-to-cart forms also carry
an operation ID, retained for 30 days, so re-submission does not increment twice.
Updates and removals use the same protection. Missing products stay visible
and removable; stock failures keep the entire cart.

Feedback uses a **60-second HttpOnly flash cookie**, `shop_flash`. It contains
only a fixed message code such as `added`; the next HTML response maps the code
to application text, displays it and expires the cookie. It stores no identity,
price, cart content or arbitrary HTML. As with normal one-use flash messages,
the next page opened in another tab may consume it. The guest assistant has a
separate one-day random ownership cookie for private answer records. HTTPS
adds Secure and `__Host-` prefixes to these cookies.

`CookieAuth` uses the existing token store and its HttpOnly, SameSite=Lax auth
cookie. Sign out revokes the presented token. Every changing browser route
checks request origin, including guest cart forms, login, registration,
assistant forms and administrator actions. Browsers supply this automatically;
curl must send a matching `Origin` or `Sec-Fetch-Site: same-origin`. The JSON
API continues to use bearer TokenAuth, including its separate ticket-based
WebSocket, and never accepts the shop cookie as a bearer credential.

Set `APP_URL` to the address used in the browser, including any non-default
port, and set `SERVER_ADDRESS` to the listening address. Local defaults are
`http://127.0.0.1:8080` and `tcp://127.0.0.1:8080`. Use the public HTTPS URL
when deploying, including behind a TLS proxy: CookieAuth uses it for origin
checks and makes the cookie Secure for HTTPS.

It uses SQLite. `shop:seed` makes 36 products, 12 people and about two weeks of
orders, the same ones every time. Everyone's password is `password`:

| Account | Role |
| --- | --- |
| `ada@example.com` | Customer |
| `grace@example.com` | Another customer, useful for checking receipt privacy |
| `admin@example.com` | Administrator |

The other seeded customers are `alan`, `katherine`, `linus`, `margaret`,
`dennis`, `barbara`, `ken`, `radia` and `tim`, each at `@example.com`.

## The API

| | | |
| --- | --- | --- |
| `GET` | `/up`, `/api/ready` | Whether a worker answers, and whether it reaches the database and the cache: a liveness and a readiness probe |
| `GET` | `/api/products` | The catalogue, a page at a time: `?q=mug&category=kitchen&colour=blue&minPrice=1000&maxPrice=5000&page=2&perPage=12&sort=price-asc`; API prices and price filters are integer cents. Sort is `name`, `price-asc`, `price-desc` or `newest`; the colour is read from each product's JSON details. An unknown filter, or a range the wrong way round, is a `422` |
| `GET` | `/api/products.csv` | The whole catalogue as a spreadsheet, the feed `shop:mirror-catalogue` downloads |
| `GET` | `/api/products/{id}` | One product |
| `GET` | `/api/products/{id}/stock` | Its stock live, as server-sent events: `curl -N` it and change the stock |
| `POST` | `/api/assistant` | `{"question": "a blue mug"}`; a shop assistant's answer as server-sent events, a `token` at a time and `done` with the products it spoke of. `curl -N` it |
| `POST` | `/api/model/chat` | The pretend language model the assistant asks: `{"prompt": "..."}`, answered word by word as server-sent events |
| `GET` | `/api/categories` | Each category with its counts, remembered for a minute |
| `POST` | `/api/register` | `email`, `name`, `password`; answers with a token |
| `POST` | `/api/login` | `email`, `password`; answers with a token |
| `POST` | `/api/assistant/chat` | `{"question": "a mug"}`, then `{"question": "another one?"}`: your own assistant, which remembers what it showed you and stays on the same shelf; answers with `answer`, `products` and `turn` |
| `GET` | `/api/me` | Whoever the token belongs to |
| `POST` | `/api/ws/ticket` | A single-use ticket for a WebSocket connection. Requires a bearer token |
| `WS` | `/api/orders/{id}/live` | The owner’s order status. Authenticate with `?ticket=` or a bearer header; discard messages whose `version` is no newer than the last one seen |
| `GET` | `/api/orders` | Your orders, newest first, with how many things each holds; ten at a time, then `?cursor=` with the `next_cursor` of the page before |
| `POST` | `/api/orders` | `{"lines": [{"productId": 1, "quantity": 2}]}` |
| `GET` | `/api/orders/{id}` | One of your orders, with its lines |
| `POST` | `/api/products` | Add a product. Administrators |
| `PUT` | `/api/products/{id}/stock` | `{"stock": 40}`. Administrators |
| `PUT` | `/api/orders/{id}/ship` | Ship a paid order. Administrators. Repeating a shipped order keeps its version and shipment time |

The checked-in [OpenAPI 3.1 document](openapi.json) describes the HTTP routes, their input validation, authentication, errors and typed responses. The WebSocket route is listed above. Generate it without starting a server, then check that the committed copy is current:

```sh
set -e
php cua openapi:generate --strict
php cua openapi:generate --strict --check
```

Response DTOs can be inferred directly. Database-row actions declare their schemas with `#[Response]`; the shared shapes are in `app/Http/OpenApiSchemas.php`. The `openapi.security` and `openapi.middleware` settings in `config/openapi.php` document bearer authentication, rate limits and administrator-only responses. Raw product and order rows expose database timestamps without a timezone offset; product `details` is JSON text or null. The order-list records serialize their dates as RFC 3339 timestamps.

```sh
set -e
TOKEN=$(curl -s -X POST localhost:8080/api/login -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"email":"ada@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST localhost:8080/api/orders -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"lines":[{"productId":1,"quantity":2}]}'
```

Both assistants use the built-in deterministic model when `MODEL_URL` is
unset. To use a real OpenAI Responses-compatible model, set `MODEL_URL`,
`MODEL_API_KEY`, `MODEL_NAME` and `MODEL_ENDPOINT=responses`; the app sends a
Bearer token and streams `response.output_text.delta` events. Chat Completions
is also supported with `MODEL_ENDPOINT=chat/completions`. `APP_URL` still sets the public application
address; when omitted it follows `SERVER_ADDRESS`, including its port. A failed
model request writes one warning with the attempted URL and settings.

## What it exercises

| Part of the framework | Where to look |
| --- | --- |
| Arc templates and components | `resources/views/`, `app/Actions/Shop*.php`, and `app.php`; reusable Button, Input, Select, Badge, Card, Table, EmptyState, Flash, Pagination and SiteLayout components with named slots |
| Actions and validation by signature | `app/Actions/`; `PlaceOrder` takes `app/Http/OrderInput.php`, with `#[ListOf]` validating each `OrderLineInput` |
| Routes, groups, middleware | `routes/web.php`, `app/Http/AdminsOnly.php` |
| Token authentication | `Login`, `Register`, `app/Users.php` |
| Cookie authentication | `ShopLogin`, `ShopRegister`, `ShopLogout`, `config/auth.php`; native forms, origin checks, token revocation, owner-only receipts and a cookie-authenticated order socket. `tests/ShopAuthTest.php` |
| Durable carts and idempotency | `app/Shop/Carts.php`, `ShopCart`, `ShopCheckout`; database guest/user carts, login merge and atomic duplicate-safe checkout. `tests/ShopPageTest.php` and browser concurrency coverage |
| Sessionless feedback | `app/Shop/Pages.php`, `BrowserCookies.php`, `Flash`; a fixed-code HttpOnly cookie consumed on the next page |
| Server-side administration | `ShopAdmin`, `resources/views/admin/`, `tests/ShopAdminTest.php`; shared CreateProduct, SetStock, ShipOrder and BuildDailyReport actions |
| Responsive design and assets | `resources/assets/`, `ShopAssets`; self-hosted OFL fonts, one stylesheet, system light/dark themes |
| Query builder, transactions, pagination | `PlaceOrder`, `ListProducts`, `ShowOrder` |
| Rows as records | `app/Order.php` is read with `->into(Order::class)`, its status a backed enum (`OrderStatus`) and its times dates; `ListMyOrders` and `ShipOrder` read orders that way |
| Migrations | `database/migrations/` |
| Cache | `ListCategories` remembers; an order or a change to the catalogue forgets |
| Events | `OrderPlaced`, and its two listeners in `app/Actions/` |
| Queue and supervisors | `app/Actions/`; `config/queue.php` gives mail and the warehouse workers of their own; `SyncInventory` has a `#[Timeout]` and passes its `Cancellation` to the HTTP client |
| One command for it all | `start` runs, beside HTTP, the server of every plugin listed in `app.php`: here `queue`, `schedule` and `actors`, and `dispatch` from `app/Setup.php` |
| Streaming | `WatchStock` returns SSE; the home and product pages show its live stock with a server-rendered fallback. `resources/assets/shop.js` also connects order WebSockets |
| Client streaming | `AskAssistant` reads the configured model's answer with `$http->stream()` and `events()`, and relays each delta as it comes; `PretendModel` is the no-credential local fallback. `tests/AssistantTest.php` fakes the model with `FakeClient` |
| Actors package | `app/Actors/ShoppingAssistant` is an actor: one per customer, `ChatWithAssistant` asks it, and it keeps the conversation in its variables until a minute without a question. With Redis it runs in the `actors` server, so a customer has one assistant whichever HTTP worker their request lands on. `tests/AssistantChatTest.php`; `ShopAssistant` exposes native form conversations and private, expiring answer pages |
| Runs package | `FollowUpOrder` waits for its order to ship, sleeps three days, then asks for a review; `ShipOrder` queues `SignalOrderShipped` to signal it. `php cua runs:list` |
| Retries and failed jobs | The pretend mail server refuses some messages (`mail.fail_rate` in `config/mail.php`) |
| Scheduling | `app/Setup.php`: every ten seconds, every minute, daily; `PruneShopData` also removes expired guest carts, operation IDs and assistant replies |
| Clock injection | Actions and file mail take `ClockInterface`; tests use `FrozenClock` |
| HTTP client | `SyncInventory` asks the "warehouse" over HTTP; `shop:traffic` is the customers; `shop:mirror-catalogue` downloads a feed into a file with `sink:`, `maxBytes:` and `decode: false` |
| Console commands | `app/Console/` |
| Redis package | Set `CACHE_STORE=redis` or `QUEUE_STORE=redis` in `.env` |
| Storage and S3 package | `BuildDailyReport` writes whole JSON files through `Storage`; `config/storage.php` selects a local folder, memory or an S3 bucket. `ReportStorageTest` checks the signed S3 upload with `FakeClient` |
| Dashboard package | `/clockwerk` |
| OpenAPI package | `config/openapi.php`, `#[Response]` on actions, and the committed `openapi.json` |
| Deploying | `/up` and `Ready` for probes. `start` refuses a route, listener or task whose services cannot be made, and `php cua describe app` lists them; break `app.php` and `php cua status` and `stop` still reach the running server |

The order input classes use PHP types for nested fields. An order has 1–20 lines, each with a `productId` and a quantity of 1–20. Leaving out `quantity` or sending it as `null` means one item.

The conversational assistant keeps the last four questions and the first 500 characters of each answer, plus the last 256 product IDs it showed. Its turn counter continues until it idles out. A model response is limited to 64 KiB, so a conversation that stays active cannot retain an unbounded history. The streaming assistant returns `502` when its model cannot be reached before the stream starts.

Every `start` launches a scheduler. Tasks use `onOneServer()`; set `CACHE_STORE=redis` when several machines run the same shop so they share the scheduling locks. Use distinct `REDIS_PREFIX` and `BROADCAST_PREFIX` values for unrelated shops on one Redis server, and the same values for workers of one shop.

What happens when an order is placed:

1. `PlaceOrder` takes the stock and saves the order in one transaction, and dispatches `OrderPlaced`.
2. A listener pushes `SendOrderConfirmation` onto the `mail` queue and `SyncInventory` onto `warehouse`; another forgets the cached categories.
3. The queue's workers run both. The confirmation is a file in `storage/mail/`. Refused messages are tried again, up to four times.
4. The scheduler marks the order paid ten seconds or so later. One order in ten is never paid; `order:cancel-stale` cancels it after two minutes and puts its stock back.
5. Every minute a "delivery" adds 60 to products with stock below 10; customers may find a product out of stock before the next delivery.
6. The listener also starts a `FollowUpOrder` run. It waits, holding no worker, until an administrator or the dispatch desk ships the order, then three days for it to arrive, and then writes to ask for a review. The shipment and its signal job commit together on the database queue; a failed signal is retried by a worker. A listener delayed until after shipping queues the signal too.

After the order commits, a listener failure is logged and the action still returns `201`; some jobs or cache invalidation may be skipped, with no outbox recovery in this sample.

With the Redis queue, jobs are pushed after the transaction commits. A process that dies between commit and push can lose a job; repeating the shipping request queues its signal again. Mail is delivered at least once: a crash after sending but before recording completion can send it again, including a run's review request.

## Commands

| | |
| --- | --- |
| `shop:seed` | Fill the shop; `--fresh` empties it first |
| `shop:traffic` | Pretend customers; `--seconds=30`, `--rate=200`, `--url=` |
| `order:cancel-stale` | Cancel orders never paid; the scheduler runs it every minute |
| `shop:mirror-catalogue` | Download `/api/products.csv` into `storage/catalogue.csv`; `--url=` |
| `shop:report` | Write yesterday's report, or `--day=2026-01-31`, to the configured report disk |
| `shop:dispatch` | Ship paid orders until stopped; `--pause=3` seconds per handoff. `start` also runs it as the `dispatch` server |

Stop every server and worker before `shop:seed --fresh`, including any started separately. It discards carts, checkout records, assistant answers, tokens, WebSocket tickets, cached values, runs and queued work before replacing the shop. Sized fixtures (`--products=36 --users=12 --orders=200`) reuse IDs, so old credentials and jobs must not survive the reset. The queue is cleared through its store, including when it uses Redis.

Options with values use `--name=value`. Traffic takes 1–86400 whole seconds and a rate of 1–1000000 requests per second; the dispatch desk takes `--pause=0` through `--pause=3600`. Invalid options are rejected before requests or writes. `Setup` declares rules for `shop` and `mail` settings, and checks them before declaring middleware. Environment values stay uncast until checked, so a typo such as `SERVER_WORKERS=2workers` is refused.

## Reports and S3

The daily job and `shop:report` write `YYYY-MM-DD.json` to `shop.report_disk`, named `reports` by default. Its local root is `REPORTS_PATH`, or `storage/reports`. This replaces the old `shop.reports` path setting; `REPORTS_PATH` still works. Dates must be real calendar dates. Local writes refuse symlinks and replace a file only when all its bytes are written.

To write reports to an existing S3 bucket, set these values in `.env` and restart the application:

```dotenv
REPORTS_DRIVER=s3
S3_KEY=your-access-key
S3_SECRET=your-secret
S3_BUCKET=your-bucket
S3_REGION=us-east-1
# S3_ENDPOINT=http://127.0.0.1:9000
```

Reports go under `reports/` in the bucket. Set `S3_ENDPOINT` for MinIO or another S3 service; setting an endpoint selects path-style URLs. `REPORTS_DRIVER=memory` keeps reports only until the process ends and is for tests. Local reports need no S3 credentials or service.

## Tests

```sh
set -e
composer test
composer format:check
composer lint
composer openapi:check
```

Shop integration tests each use a temporary database, a small set of known records and a frozen clock. They send requests through the real application. Only `SeedTest` runs the demo seeder to check reset behavior. `OrderInputTest` checks the nested input without a database.

SQLite is the default. To run the same suite on MySQL, give it a server DSN and an account that can create and drop databases. Each test creates a random database of its own and drops it afterwards; it does not use the development shop:

```sh
SAMPLE_TEST_MYSQL_DSN='mysql:host=127.0.0.1;port=3306' SAMPLE_TEST_MYSQL_USER=root SAMPLE_TEST_MYSQL_PASSWORD='' composer test
```

To exercise Redis cache and queues, set `SAMPLE_TEST_REDIS_URL`. Each test uses and removes its own random Redis key prefix. Socket broadcasts stay local in these in-process tests, and HTTP requests run in fibers as they do in a worker:

```sh
SAMPLE_TEST_REDIS_URL=redis://127.0.0.1:6379 composer test
```

Existing shops must run `php cua migrate` before starting the updated application. New migrations add database carts, cart operation IDs, checkout receipts and private assistant answers. Legacy local-storage carts cannot be recovered by the server; users of the earlier browser-only UI must add those items again. The stale-order command is now `order:cancel-stale`, following the singular command-area convention; update any external invocation of its old plural name. The new migration widens MySQL order totals to hold every accepted order; SQLite already uses 64-bit integers. Rolling that migration back refuses to narrow the column when a total would be lost.

`composer format` applies the shared Mago formatting rules. CI checks out the framework and packages on `main` beside the sample and runs these checks on PHP 8.4, with SQLite, MySQL and Redis stores. The framework and Arc are pinned to their released versions through path repositories; the other optional packages retain their existing `@dev` constraints. Keep the sibling framework and Arc checkouts at v0.6.2 and v0.1.1 respectively.

Browser coverage and isolated server instructions are in [`tests/browser/README.md`](tests/browser/README.md). It runs the complete flow with JavaScript disabled and enabled, including real cross-origin forms and concurrent checkout.
