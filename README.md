# Crab Shop

A sample application on the [Crab framework](../framework): a small shop with an API, Arc pages and data in it. It is here to try the framework as an application would use it, with everything switched on, and to have something real to run while a feature is being built.

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

Open the server-rendered shop at <http://127.0.0.1:8080/shop>. It has the
catalogue with search, category, colour and price filters, product pages, a
browser cart, checkout, private receipts and **My orders** at `/shop/orders`.
Choose **Log in** in the navigation, or open `/shop/login`, and use a seeded
account below. The layout shows your name and email, with a **Log out** button.
Checkout and My orders send signed-out visitors to the login page. After
logging in, open your cart and choose **Checkout**; the order belongs to your
logged-in account. A receipt is visible only to its owner; everyone else,
including signed-out visitors and other administrators, receives `404`.

The cart stays in browser local storage across login and logout. The form posts
its complete contents when you place an order. A successful checkout removes
only the submitted quantities; failed checkouts and visits to old receipts keep
the cart. `CookieAuth` uses the existing
token store and an HttpOnly, SameSite=Lax cookie; logout revokes that token and
clears the cookie. Every changing `/shop` route checks the request's origin,
including login and logout. Browsers send `Sec-Fetch-Site: same-origin` for
these forms; curl must send that header or a matching `Origin` too.

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
| `GET` | `/api/products` | The catalogue, a page at a time: `?q=mug&category=kitchen&colour=blue&minPrice=1000&maxPrice=5000&page=2&perPage=12`; the colour is read from each product's JSON details. An unknown filter, or a range the wrong way round, is a `422` |
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

## What it exercises

| Part of the framework | Where to look |
| --- | --- |
| Arc templates and components | `resources/views/`, `app/Actions/Shop*.php`, and `app.php`; the shop is built from capitalised Arc components and named slots |
| Actions and validation by signature | `app/Actions/`; `PlaceOrder` takes `app/Http/OrderInput.php`, with `#[ListOf]` validating each `OrderLineInput` |
| Routes, groups, middleware | `routes/web.php`, `app/Http/AdminsOnly.php` |
| Token authentication | `Login`, `Register`, `app/Users.php` |
| Cookie authentication | `ShopLogin`, `ShopLogout`, `ShopCheckout`, `ShopOrder`, `ShopOrders`, `config/auth.php` and `SiteLayout`; browser login, token revocation, origin checks and owner-only receipts. `tests/ShopAuthTest.php` and `tests/ShopPageTest.php` |
| Query builder, transactions, pagination | `PlaceOrder`, `ListProducts`, `ShowOrder` |
| Rows as records | `app/Order.php` is read with `->into(Order::class)`, its status a backed enum (`OrderStatus`) and its times dates; `ListMyOrders` and `ShipOrder` read orders that way |
| Migrations | `database/migrations/` |
| Cache | `ListCategories` remembers; an order or a change to the catalogue forgets |
| Events | `OrderPlaced`, and its two listeners in `app/Actions/` |
| Queue and supervisors | `app/Actions/`; `config/queue.php` gives mail and the warehouse workers of their own; `SyncInventory` has a `#[Timeout]` and passes its `Cancellation` to the HTTP client |
| One command for it all | `start` runs, beside HTTP, the server of every plugin listed in `app.php`: here `queue`, `schedule` and `actors`, and `dispatch` from `app/Setup.php` |
| Streaming | `WatchStock` returns a generator, and each value it yields is a server-sent event, sent at once |
| Client streaming | `AskAssistant` reads the pretend model's answer with `$http->stream()` and `events()`, and relays each word as it comes; `PretendModel` stands in for a language model's API (`shop.model_url`). `tests/AssistantTest.php` fakes the model with `FakeClient` |
| Actors package | `app/Actors/ShoppingAssistant` is an actor: one per customer, `ChatWithAssistant` asks it, and it keeps the conversation in its variables until a minute without a question. With Redis it runs in the `actors` server, so a customer has one assistant whichever HTTP worker their request lands on. `tests/AssistantChatTest.php` |
| Runs package | `FollowUpOrder` waits for its order to ship, sleeps three days, then asks for a review; `ShipOrder` queues `SignalOrderShipped` to signal it. `php cua runs:list` |
| Retries and failed jobs | The pretend mail server refuses some messages (`mail.fail_rate` in `config/mail.php`) |
| Scheduling | `app/Setup.php`: every ten seconds, every minute, daily |
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

Stop every server and worker before `shop:seed --fresh`, including any started separately. It discards tokens, WebSocket tickets, cached values, runs and queued work before replacing the shop. Sized fixtures (`--products=36 --users=12 --orders=200`) reuse IDs, so old credentials and jobs must not survive the reset. The queue is cleared through its store, including when it uses Redis.

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

Existing shops must run `php cua migrate` before starting the updated application. The stale-order command is now `order:cancel-stale`, following the singular command-area convention; update any external invocation of its old plural name. The new migration widens MySQL order totals to hold every accepted order; SQLite already uses 64-bit integers. Rolling that migration back refuses to narrow the column when a total would be lost.

`composer format` applies the shared Mago formatting rules. CI checks out the framework and packages on `main` beside the sample and runs these checks on PHP 8.4, with SQLite, MySQL and Redis stores. The application keeps its dependencies at `@dev` through path repositories; the packages require framework `^0.6`, which the framework's `0.6.x-dev` branch alias satisfies.
