# Crab Shop

A sample application on the [Crab framework](../framework): the API of a small shop, with data in it. It is here to try the framework as an application would use it, with everything switched on, and to have something real to run while a feature is being built.

It is not the skeleton to start a project from; that is [`crab`](../crab).

## Running it

Use PHP 8.4 with the required extensions and keep the `framework`, `redis` and `clockwerk` package folders beside this application for Composer's path repositories; the defaults need no external database or Redis server.

```sh
composer install
cp .env.example .env
php cua migrate
php cua shop:seed
```

Then one command runs it all: the API on <http://127.0.0.1:8080>, the queue's workers for the jobs orders leave behind, and the scheduler for what runs by the clock. That is `SERVER_QUEUE=true` and `SERVER_SCHEDULE=true` in `.env`; an application of your own serves HTTP alone until it switches them on, as it would in production.

```sh
php cua serve
```

```text
Crab Shop server running on http://127.0.0.1:8080

  Server    Workers  Runs
  --------  -------  -------------------------------
  http      2        http://127.0.0.1:8080
  queue     3        queue:work: mail x1, default x2
  schedule  1        schedule:work

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
| `GET` | `/products` | The catalogue, a page at a time: `?q=mug&category=kitchen&page=2&perPage=12` |
| `GET` | `/products/{id}` | One product |
| `GET` | `/categories` | Each category with its counts, remembered for a minute |
| `POST` | `/register` | `email`, `name`, `password`; answers with a token |
| `POST` | `/login` | `email`, `password`; answers with a token |
| `GET` | `/me` | Whoever the token belongs to |
| `GET` | `/orders` | Your orders |
| `POST` | `/orders` | `{"lines": [{"productId": 1, "quantity": 2}]}` |
| `GET` | `/orders/{id}` | One of your orders, with its lines |
| `POST` | `/products` | Add a product. Administrators |
| `PUT` | `/products/{id}/stock` | `{"stock": 40}`. Administrators |

```sh
TOKEN=$(curl -s -X POST localhost:8080/login -H 'Content-Type: application/json' \
    -d '{"email":"ada@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST localhost:8080/orders -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
    -d '{"lines":[{"productId":1,"quantity":2}]}'
```

## What it exercises

| Part of the framework | Where to look |
| --- | --- |
| Actions and validation by signature | `app/Actions/`; `PlaceOrder` describes a nested body with `#[Fields]`, with no class written for it |
| Routes, groups, middleware | `routes/web.php`, `app/Http/AdminsOnly.php` |
| Token authentication | `Login`, `Register`, `app/Users.php` |
| Query builder, transactions, pagination | `PlaceOrder`, `ListProducts`, `ShowOrder` |
| Migrations | `database/migrations/` |
| Cache | `ListCategories` remembers; an order or a change to the catalogue forgets |
| Events | `OrderPlaced`, and its two listeners in `app/Actions/` |
| Queue and supervisors | `app/Actions/`; `config/queue.php` gives mail workers of its own |
| One command for it all | `server.with` in `config/server.php` says which servers `serve` runs beside HTTP, each switched on from `.env`: here `queue` and `schedule` |
| Retries and failed jobs | The pretend mail server refuses some messages (`mail.fail_rate` in `config/mail.php`) |
| Scheduling | `AppProvider`: every ten seconds, every minute, daily |
| HTTP client | `SyncInventory` asks the "warehouse" over HTTP; `shop:traffic` is the customers |
| Console commands | `app/Console/` |
| Redis package | Set `CACHE_STORE=redis` or `QUEUE_STORE=redis` in `.env` |
| Dashboard package | `/clockwerk` |

What happens when an order is placed:

1. `PlaceOrder` takes the stock and saves the order in one transaction, and dispatches `OrderPlaced`.
2. A listener pushes `SendOrderConfirmation` onto the `mail` queue and `SyncInventory` onto `default`; another forgets the cached categories.
3. The queue's workers run both. The confirmation is a file in `storage/mail/`. Refused messages are tried again, up to four times.
4. The scheduler marks the order paid ten seconds or so later. One order in ten is never paid; `orders:cancel-stale` cancels it after two minutes and puts its stock back.
5. Every minute a "delivery" adds 60 to products with stock below 10; customers may find a product out of stock before the next delivery.

After the order commits, a listener failure is logged and the action still returns `201`; some jobs or cache invalidation may be skipped, with no outbox recovery in this sample.

## Commands

| | |
| --- | --- |
| `shop:seed` | Fill the shop; `--fresh` empties it first |
| `shop:traffic` | Pretend customers; `--seconds=30`, `--rate=200`, `--url=` |
| `orders:cancel-stale` | Cancel orders never paid; the scheduler runs it every minute |

## Tests

```sh
composer test
```

Each test has a database of its own with the seeded data, and sends requests as a client would.
