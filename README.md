# Crab Shop

A sample application on the [Crab framework](../framework): the API of a small shop, with data in it. It is here to try the framework as an application would use it, with everything switched on, and to have something real to run while a feature is being built.

It is not the skeleton to start a project from; that is [`crab`](../crab).

## Running it

Use PHP 8.4 with the required extensions and keep the `framework`, `redis` and `clockwerk` package folders beside this application for Composer's path repositories. The database is SQLite, so none needs installing, but `.env.example` carries broadcasts and actors over Redis, at `redis://127.0.0.1:6379`: start one (`docker run -d -p 6379:6379 redis:7-alpine`), or run without it by setting `BROADCAST_CARRIER=local`, `SERVER_ACTORS=false` and `SERVER_WORKERS=1` in `.env`, the local carrier serving one HTTP process.

```sh
composer install
cp .env.example .env
php cua migrate
php cua shop:seed
```

Then one command runs it all: the API on <http://127.0.0.1:8080>, the queue's workers for the jobs orders leave behind, and the scheduler for what runs by the clock. That is `SERVER_QUEUE=true` and `SERVER_SCHEDULE=true` in `.env`, and `SERVER_ACTORS=true` for the customers' assistants, which run in the `actors` server since the broadcast carrier is Redis; an application of your own serves HTTP alone until it switches them on, as it would in production.

```sh
php cua dev
```

```text
Crab Shop server running on http://127.0.0.1:8080

  Server    Workers  Runs
  --------  -------  -------------------------------
  http      2        http://127.0.0.1:8080
  queue     4        queue:work: mail x1, warehouse x1, default x2
  schedule  1        schedule:work
  actors    2        actors:work

Press Ctrl+C to stop
```

And, in another terminal, for something to look at:

```sh
php cua shop:traffic     # thirty seconds of pretend customers
```

Open the dashboard at <http://127.0.0.1:8080/clockwerk?token=sample>.

It uses SQLite. `shop:seed` makes 36 products, 12 people and about two weeks of orders, the same ones every time. Everyone's password is `password`: `ada@example.com` is a customer, `admin@example.com` an administrator.

## The API

| | | |
| --- | --- | --- |
| `GET` | `/up`, `/ready` | Whether a worker answers, and whether it reaches the database and the cache: a liveness and a readiness probe |
| `GET` | `/products` | The catalogue, a page at a time: `?q=mug&category=kitchen&colour=blue&minPrice=1000&maxPrice=5000&page=2&perPage=12`; the colour is read from each product's JSON details. An unknown filter, or a range the wrong way round, is a `422` |
| `GET` | `/products.csv` | The whole catalogue as a spreadsheet, the feed `shop:mirror-catalogue` downloads |
| `GET` | `/products/{id}` | One product |
| `GET` | `/products/{id}/stock` | Its stock live, as server-sent events: `curl -N` it and change the stock |
| `POST` | `/assistant` | `{"question": "a blue mug"}`; a shop assistant's answer as server-sent events, a `token` at a time and `done` with the products it spoke of. `curl -N` it |
| `POST` | `/model/chat` | The pretend language model the assistant asks: `{"prompt": "..."}`, answered word by word as server-sent events |
| `GET` | `/categories` | Each category with its counts, remembered for a minute |
| `POST` | `/register` | `email`, `name`, `password`; answers with a token |
| `POST` | `/login` | `email`, `password`; answers with a token |
| `POST` | `/assistant/chat` | `{"question": "a mug"}`, then `{"question": "another one?"}`: your own assistant, which remembers what it showed you and stays on the same shelf; answers with `answer`, `products` and `turn` |
| `GET` | `/me` | Whoever the token belongs to |
| `GET` | `/orders` | Your orders, newest first, with how many things each holds; ten at a time, then `?cursor=` with the `next_cursor` of the page before |
| `POST` | `/orders` | `{"lines": [{"productId": 1, "quantity": 2}]}` |
| `GET` | `/orders/{id}` | One of your orders, with its lines |
| `POST` | `/products` | Add a product. Administrators |
| `PUT` | `/products/{id}/stock` | `{"stock": 40}`. Administrators |
| `PUT` | `/orders/{id}/ship` | Ship a paid order. Administrators |

```sh
TOKEN=$(curl -s -X POST localhost:8080/login -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"email":"ada@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST localhost:8080/orders -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' \
    -d '{"lines":[{"productId":1,"quantity":2}]}'
```

## What it exercises

| Part of the framework | Where to look |
| --- | --- |
| Actions and validation by signature | `app/Actions/`; `PlaceOrder` takes `app/Http/OrderInput.php`, with `#[ListOf]` validating each `OrderLineInput` |
| Routes, groups, middleware | `routes/web.php`, `app/Http/AdminsOnly.php` |
| Token authentication | `Login`, `Register`, `app/Users.php` |
| Query builder, transactions, pagination | `PlaceOrder`, `ListProducts`, `ShowOrder` |
| Rows as records | `app/Order.php` is read with `->into(Order::class)`, its status a backed enum (`OrderStatus`) and its times dates; `ListMyOrders` and `ShipOrder` read orders that way |
| Migrations | `database/migrations/` |
| Cache | `ListCategories` remembers; an order or a change to the catalogue forgets |
| Events | `OrderPlaced`, and its two listeners in `app/Actions/` |
| Queue and supervisors | `app/Actions/`; `config/queue.php` gives mail and the warehouse workers of their own; `SyncInventory` has a `#[Timeout]` and passes its `Cancellation` to the HTTP client |
| One command for it all | `server.with` in `config/server.php` says which servers `start` runs beside HTTP, each switched on from `.env`: here `queue` and `schedule` |
| Streaming | `WatchStock` returns a generator, and each value it yields is a server-sent event, sent at once |
| Client streaming | `AskAssistant` reads the pretend model's answer with `$http->stream()` and `events()`, and relays each word as it comes; `PretendModel` stands in for a language model's API (`shop.model_url`). `tests/AssistantTest.php` fakes the model with `FakeClient` |
| Actors | `app/Actors/ShoppingAssistant` is an actor: one per customer, `ChatWithAssistant` asks it, and it keeps the conversation in its variables until a minute without a question. With Redis it runs in the `actors` server, so a customer has one assistant whichever HTTP worker their request lands on. `tests/AssistantChatTest.php` |
| Runs | `FollowUpOrder` waits for its order to ship, sleeps three days, then asks for a review; `ShipOrder` signals it. `php cua runs:list` |
| Retries and failed jobs | The pretend mail server refuses some messages (`mail.fail_rate` in `config/mail.php`) |
| Scheduling | `AppProvider`: every ten seconds, every minute, daily |
| Clock injection | Actions and file mail take `ClockInterface`; tests use `FrozenClock` |
| HTTP client | `SyncInventory` asks the "warehouse" over HTTP; `shop:traffic` is the customers; `shop:mirror-catalogue` downloads a feed into a file with `sink:`, `maxBytes:` and `decode: false` |
| Console commands | `app/Console/` |
| Redis package | Set `CACHE_STORE=redis` or `QUEUE_STORE=redis` in `.env` |
| Dashboard package | `/clockwerk` |
| Deploying | `/up` and `Ready` for probes. `start` refuses a route, listener or task whose services cannot be made, and `php cua describe app` lists them; break `app.php` and `php cua status` and `stop` still reach the running server |

The order input classes use PHP types for nested fields. An order has 1–20 lines, each with a `productId` and a quantity of 1–20. Leaving out `quantity` or sending it as `null` means one item.

What happens when an order is placed:

1. `PlaceOrder` takes the stock and saves the order in one transaction, and dispatches `OrderPlaced`.
2. A listener pushes `SendOrderConfirmation` onto the `mail` queue and `SyncInventory` onto `warehouse`; another forgets the cached categories.
3. The queue's workers run both. The confirmation is a file in `storage/mail/`. Refused messages are tried again, up to four times.
4. The scheduler marks the order paid ten seconds or so later. One order in ten is never paid; `orders:cancel-stale` cancels it after two minutes and puts its stock back.
5. Every minute a "delivery" adds 60 to products with stock below 10; customers may find a product out of stock before the next delivery.
6. The listener also starts a `FollowUpOrder` run. It waits, holding no worker, until an administrator ships the order, then three days for it to arrive, and then writes to ask for a review.

After the order commits, a listener failure is logged and the action still returns `201`; some jobs or cache invalidation may be skipped, with no outbox recovery in this sample.

## Commands

| | |
| --- | --- |
| `shop:seed` | Fill the shop; `--fresh` empties it first |
| `shop:traffic` | Pretend customers; `--seconds=30`, `--rate=200`, `--url=` |
| `orders:cancel-stale` | Cancel orders never paid; the scheduler runs it every minute |
| `shop:mirror-catalogue` | Download `/products.csv` into `storage/catalogue.csv`; `--url=` |

## Tests

```sh
composer test
```

Shop integration tests each use a temporary database, a small set of known records and a frozen clock. They send requests through the real application and do not run the demo seeder. `OrderInputTest` checks the nested input without a database.
