# E-Commerce Order & Promotion API

A Laravel REST API for an e-commerce backend: product catalog, customer cart, promotional codes, a concurrency-safe checkout, order history and cancellation, plus admin endpoints.

**The focus is correctness under concurrent purchases.** Checkout locks rows in a fixed order inside one transaction. Stock is guarded three ways: a row lock, a conditional `UPDATE`, and an unsigned column. Parallel-process tests prove it can't be oversold.

| | |
|---|---|
| Stack | Laravel 13.35 · PHP 8.3 · MySQL 8 / MariaDB · Laravel Sanctum · spatie/laravel-query-builder · L5-Swagger · Pest 4 |
| Tests | 250 tests (244 feature/unit + 6 multi-process concurrency); every API route is exercised |
| API docs | Swagger UI at `/api/documentation` · OpenAPI JSON at `/docs` and in [`storage/api-docs/api-docs.json`](storage/api-docs/api-docs.json) |

## Contents

1. [Setup](#1-setup)
2. [Environment configuration](#2-environment-configuration)
3. [Database setup and demo data](#3-database-setup-and-demo-data)
4. [API documentation](#4-api-documentation)
5. [Architecture decisions](#5-architecture-decisions)
6. [Concurrency handling](#6-concurrency-handling)
7. [Database design and indexes](#7-database-design-and-indexes)
8. [Behaviour under high traffic](#8-behaviour-under-high-traffic)
9. [Trade-offs](#9-trade-offs)
10. [Testing](#10-testing)
11. [Requirements map](#11-requirements-map)

---

## 1. Setup

### Option A: local PHP and MySQL/MariaDB

Requirements: PHP 8.3+ with `pdo_mysql`, Composer, and MySQL 8 or MariaDB 10.4+.

```bash
git clone https://github.com/raedcodes/ecommerce-api.git
cd ecommerce-api
composer install
cp .env.example .env
php artisan key:generate

# Create the application and test databases (adjust the credentials to yours)
mysql -u root -e 'CREATE DATABASE `ecommerce-db`; CREATE DATABASE `ecommerce-db_testing`;'

php artisan migrate --seed
php artisan l5-swagger:generate   # optional: the generated docs are already committed
php artisan serve                 # http://localhost:8000
```

Order-confirmation emails are queued. To process them, run `php artisan queue:work` (with `MAIL_MAILER=log` they're written to `storage/logs/laravel.log`).

### Option B: Docker with Laravel Sail (MySQL 8.4 + Redis)

[`compose.yaml`](compose.yaml) defines the app (PHP 8.3), MySQL 8.4 and Redis. The MySQL container also creates `<DB_DATABASE>_testing` on first start ([`docker/mysql/create-testing-database.sh`](docker/mysql/create-testing-database.sh)).

```bash
cp .env.example .env
# In .env set: DB_HOST=mysql, DB_USERNAME=sail, DB_PASSWORD=password, REDIS_HOST=redis, CACHE_STORE=redis

# Install Composer dependencies without local PHP
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php83-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan test
```

The API is then at `http://localhost` (set `APP_PORT` to change the port).

---

## 2. Environment configuration

Only these keys matter beyond a stock Laravel `.env` ([`.env.example`](.env.example) has working local defaults):

| Key | Default | Purpose |
|---|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `mysql`, `127.0.0.1`, `3306`, `ecommerce-db`, `root`, *(empty)* | Application database. The spec requires MySQL/PostgreSQL; SQLite is not supported because it ignores row locks. |
| `CACHE_STORE` | `database` | Product listing cache. Use `redis` under Sail (bonus: Redis caching). Any store works. |
| `REDIS_HOST`, `REDIS_CLIENT` | `127.0.0.1`, `phpredis` | Only needed when `CACHE_STORE=redis`. |
| `QUEUE_CONNECTION` | `database` | Queue for the order-confirmation listener. |
| `MAIL_MAILER` | `log` | Confirmation emails go to the log locally. |
| `SANCTUM_EXPIRATION` | *(unset: tokens never expire)* | Token lifetime in minutes. |
| `L5_SWAGGER_GENERATE_ALWAYS` | `false` | Leave off; docs are generated explicitly and committed. |

The test environment is fixed in [`phpunit.xml`](phpunit.xml): MySQL database `ecommerce-db_testing`, array cache, sync queue, array mailer. Host and credentials come from `.env`.

---

## 3. Database setup and demo data

```bash
php artisan migrate --seed    # first time
php artisan db:seed           # safe to re-run: every seeder is idempotent
```

**Demo accounts** (local only, password `password` for all):

| Email | Role | Seeded state |
|---|---|---|
| `admin@example.com` | Admin | Can use `/api/admin/*` |
| `customer@example.com` | Customer | Cart: 2 × mug + 1 keyboard = **$154.00**, eligible for SUMMER20 |
| `customer2@example.com` | Customer | 5 past orders, one in each status (`pending` … `cancelled`) |

**Demo promotions**, one for each coupon rule:

| Code | Rule it demonstrates |
|---|---|
| `SUMMER20` | The spec example: 20%, $100 minimum, $50 cap, one use per customer |
| `WELCOME10` | $10 fixed, one use per customer |
| `EXPIRED5` | Expired → `coupon_expired` |
| `FUTURE15` | Not started → `coupon_invalid` |
| `PAUSED25` | Inactive → `coupon_invalid` |
| `LIMITED1` | Usage limit reached → `coupon_usage_limit_reached` |

**Products:** 50 in total. `LAMP-001` has stock 5 (to try the concurrency scenario by hand), `BAG-001` is out of stock, and `FAN-001` is inactive. 43 generated fillers include some inactive and out-of-stock products.

---

## 4. API documentation

- **Swagger UI:** `http://localhost:8000/api/documentation`. Click **Authorize** and paste a token from `POST /api/auth/login`.
- **Postman:** *Import → File* [`storage/api-docs/api-docs.json`](storage/api-docs/api-docs.json) (or *Import → Link* `http://localhost:8000/docs` in the desktop app), with **Folder organization: Tags**. Postman builds the whole collection from the OpenAPI document. Then two one-time steps on the collection:
  1. **Variables:** set `baseUrl` to `http://localhost:8000` (the docs use a relative server URL).
  2. **Scripts → Post-response:** paste this, so logging in or registering stores the token for every request, and logging out clears it:
     ```js
     if (pm.request.url.getPath().endsWith('/api/auth/logout') && pm.response.code === 204) {
         pm.collectionVariables.unset('bearerToken');
     } else {
         try {
             const token = pm.response.json()?.data?.token;
             if (token) pm.collectionVariables.set('bearerToken', token);
         } catch (e) { /* not JSON, e.g. 204 */ }
     }
     ```
- **Regenerate** after changing an endpoint: `php artisan l5-swagger:generate`. A test fails if the committed JSON is out of date or if any route is undocumented.

### Conventions

- **Auth:** `Authorization: Bearer <token>` (Sanctum personal access tokens). Products are public; cart, checkout and order endpoints require a token. Admin endpoints also require `is_admin`.
- **Money:** returned as decimal strings in dollars (`"149.99"`) and accepted the same way. Stored as integer cents.
- **Success envelope:** `{ "data": ... }`; paginated lists add `links` and `meta`.
- **Errors:** always `{ "message": "...", "code": "...", "details"?: {...} }`. Validation errors use code `validation_failed` and add `errors` keyed by field. JSON is returned for `/api/*` even without an `Accept` header.

### Endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/register` | – | Register; returns a token (5/min per IP) |
| POST | `/api/auth/login` | – | Log in; returns a token (5/min per email + IP) |
| POST | `/api/auth/logout` | Customer | Revoke the current token |
| GET | `/api/products` | – | Active products: `filter[name]`, `filter[min_price]`, `filter[max_price]`, `filter[in_stock]`, `sort` (`price`, `name`, `created_at`, `-` for descending), `per_page`, `page` |
| GET | `/api/products/{id}` | – | Product details (inactive → 404) |
| GET | `/api/cart` | Customer | Cart at current prices, with promotion, discount and total |
| POST | `/api/cart/items` | Customer | Add `{product_id, quantity}`; merges with an existing line |
| PATCH/PUT | `/api/cart/items/{id}` | Customer | Change the quantity |
| DELETE | `/api/cart/items/{id}` | Customer | Remove an item |
| POST | `/api/cart/promotion` | Customer | Apply `{code}` after an eligibility check |
| DELETE | `/api/cart/promotion` | Customer | Remove the code |
| POST | `/api/checkout` | Customer | Place an order from the cart. Optional `Idempotency-Key` header. |
| GET | `/api/orders` | Customer | My orders, newest first |
| GET | `/api/orders/{id}` | Customer | One of my orders |
| POST | `/api/orders/{id}/cancel` | Customer | Cancel (pending/processing); safe to repeat |
| GET/POST | `/api/admin/products` | Admin | List all (incl. inactive) / create |
| GET/PATCH/PUT | `/api/admin/products/{id}` | Admin | Show / update (deactivate via `status`) |
| GET/POST | `/api/admin/promotions` | Admin | List / create |
| GET/PATCH/PUT/DELETE | `/api/admin/promotions/{id}` | Admin | Show / update / delete |

### Error codes

| HTTP | `code` | When |
|---|---|---|
| 401 | `unauthenticated` | Missing or invalid token |
| 403 | `forbidden` | Another customer's order; non-admin on an admin route |
| 404 | `product_not_found`, `order_not_found`, `cart_item_not_found`, `promotion_not_found`, `not_found` | Missing (or, for products, inactive) resource |
| 409 | `invalid_order_status` | Cancelling a shipped or delivered order |
| 422 | `validation_failed` | Invalid input |
| 422 | `insufficient_stock` | Not enough stock; `details.items[]` lists `{product_id, name, requested, available}` for every short item |
| 422 | `cart_empty` | Checkout or applying a code with an empty cart |
| 422 | `coupon_invalid` | Unknown, inactive or not-yet-started code |
| 422 | `coupon_expired` | Past its end date |
| 422 | `coupon_usage_limit_reached` | Global usage limit reached |
| 422 | `coupon_customer_limit_reached` | This customer's limit reached |
| 422 | `coupon_minimum_not_met` | Subtotal below the minimum; `details` has `minimum` and `subtotal` |
| 429 | `too_many_requests` | Rate limited (`Retry-After` header) |

### Try it with curl

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"customer@example.com","password":"password"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->token;')

curl -s localhost:8000/api/cart -H "Authorization: Bearer $TOKEN"                                   # $154.00
curl -s -X POST localhost:8000/api/cart/promotion -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"code":"SUMMER20"}'                                      # -$30.80
curl -s -X POST localhost:8000/api/checkout -H "Authorization: Bearer $TOKEN" -H 'Idempotency-Key: demo-1'   # 201, total $123.20
curl -s -X POST localhost:8000/api/checkout -H "Authorization: Bearer $TOKEN" -H 'Idempotency-Key: demo-1'   # 200, same order
curl -s -X POST localhost:8000/api/orders/<id>/cancel -H "Authorization: Bearer $TOKEN"              # stock and coupon use returned
```

---

## 5. Architecture decisions

**Layering.** Standard Laravel with only the structure the domain needs; no repositories or DDD scaffolding.

```
routes/api.php, routes/admin.php → thin controllers → Form Requests (validation)
                                 → Actions (PlaceOrder, CancelOrder) / Services (CartService, PromotionEvaluator, ProductCatalog)
                                 → Eloquent models + PHP enums → API Resources
Domain exceptions (ApiException subclasses) render themselves as { message, code, details }
```

- **Actions for the two transactional operations.** [`PlaceOrder`](app/Actions/PlaceOrder.php) and [`CancelOrder`](app/Actions/CancelOrder.php) own their transaction and lock order, and are reused by the HTTP layer and the concurrency test workers.
- **Services:**
  - [`CartService`](app/Services/CartService.php): cart changes, each under a cart-row lock.
  - [`PromotionEvaluator`](app/Services/PromotionEvaluator.php): coupon eligibility and discount maths. It's the single place the rules live and is used by the cart, by applying a code, and by checkout.
  - [`ProductCatalog`](app/Services/ProductCatalog.php): the cached product listing.
- **Money as integer cents** in the database, formatted with [`Money`](app/Support/Money.php) only at the API boundary. Percentages round half-up with integer arithmetic (`intdiv(subtotal × percent + 50, 100)`), so there's no floating-point drift.
- **Native `ENUM` columns plus PHP backed enums** for product status, promotion type and order status. Allowed values live in the migrations; models cast to the enums. [`OrderStatus::isCancellable()`](app/Enums/OrderStatus.php) holds the cancellation rule.
- **Snapshots.** `order_items` stores `product_name`, `product_sku`, `unit_price` and `line_total` at purchase time. `product_id` is set to null if the product is deleted, so history is never affected by later product changes.
- **Server-side pricing only.** Checkout takes no body. Prices and totals come from the locked product rows; nothing the client sends is read.
- **Consistent errors.** Business-rule failures are [`ApiException`](app/Exceptions/ApiException.php) subclasses with a stable `code`. They implement `ShouldntReport`, so expected outcomes don't flood the error log. Framework errors (401/403/404/422/429) are normalised to the same shape in [`bootstrap/app.php`](bootstrap/app.php), which also hides model class names in 404 messages.
- **Authentication and authorization.** Sanctum tokens. [`OrderPolicy`](app/Policies/OrderPolicy.php) handles order ownership, cart items are always looked up inside the customer's own cart, and an `admin` gate covers [`routes/admin.php`](routes/admin.php). `is_admin` and `times_used` aren't mass-assignable.
- **Listing with spatie/laravel-query-builder.** `allowedFilters` and `allowedSorts`, fed **validated** input only. Spatie doesn't check values, so [`ProductIndexRequest`](app/Http/Requests/ProductIndexRequest.php) does. Comma-splitting of filter values is disabled so a search like "lamp, desk" stays literal.
- **Events after commit.** [`OrderPlaced`](app/Events/OrderPlaced.php) implements `ShouldDispatchAfterCommit`, so it's discarded on rollback. A queued listener sends the confirmation email, so mail never slows down or fails a checkout.
- **API docs separate from code.** OpenAPI attributes live in [`app/OpenApi/`](app/OpenApi/), and tests keep them in sync with the routes.

---

## 6. Concurrency handling

> *Stock = 5. Customer A buys 4 and customer B buys 3 at the same time. Inventory must never go negative.*

### How checkout prevents overselling

[`PlaceOrder`](app/Actions/PlaceOrder.php) runs everything in **one database transaction** and takes row locks (`SELECT … FOR UPDATE`) in a **fixed order**:

1. **The customer's cart row.** Serialises double clicks, parallel tabs and cart edits for that customer.
2. **Every product in the cart, in one query ordered by `id`.** Then it checks stock against the locked rows and calculates prices from them.
3. **The promotion row**, if a code is applied. Then it re-runs every eligibility rule, including the global and per-customer limits.
4. It reduces stock with `UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?`, requires exactly one affected row, then creates the order and items, increments `times_used`, clears the cart, and commits.

**The A/B scenario.** A and B both request the lock on the product row, and the database grants it to one of them. Say A gets it first. A sees 5 ≥ 4, sets stock to 1, and commits. B's `SELECT … FOR UPDATE` only returns after A commits, so B reads **1**, and 1 < 3, so B gets `422 insufficient_stock` and nothing changes. If B wins instead, stock ends at 2 and A is rejected. **Stock is never negative and never oversold.**

### Three layers of protection

| Layer | What it stops |
|---|---|
| Row lock (`FOR UPDATE`) | Two checkouts reading the same stale stock |
| Conditional `UPDATE … WHERE stock_quantity >= ?` | Overselling, even if a code path forgot the lock |
| `stock_quantity` is `UNSIGNED` | MySQL rejects any write that would go negative (error 1690) |

### Deadlocks, retries and idempotency

- **No lock cycles.** Checkout locks cart → products (ascending id) → promotion. Cancellation locks order → products (ascending id) → promotion. Neither locks rows the other locks in the opposite order.
- **Safe retries.** `DB::transaction(..., attempts: 3)` retries only when the database reports a deadlock or serialization failure, and the whole transaction has already rolled back by then.
- **`Idempotency-Key` (bonus).** The key is stored on the order **in the same transaction** (unique per customer). A client retrying after a timeout either gets the committed order back (200) or, if nothing committed, a fresh attempt. There's no "unknown" state on the server. A retry that arrives while the first attempt is still running waits on the cart lock, then gets the finished order.

### Safe cancellation

[`CancelOrder`](app/Actions/CancelOrder.php) locks the order row and **re-reads its status inside the transaction**. A repeated or simultaneous cancel waits for the first, then sees `cancelled` and changes nothing, so stock is restored exactly once. It also decrements `times_used`, so the customer gets the coupon use back.

### Proof

[`tests/Concurrency/CheckoutConcurrencyTest.php`](tests/Concurrency/CheckoutConcurrencyTest.php) starts **real, separate PHP processes** ([`worker.php`](tests/Concurrency/worker.php)) against MySQL at the same instant. Each worker pauses 150 ms after reading product/order rows, to widen the race window.

| Scenario | Result |
|---|---|
| Stock 5; A buys 4, B buys 3 | Exactly one order; the other gets `insufficient_stock`; stock = 5 − winner's quantity |
| 10 buyers × 1 unit, stock 5 | Exactly 5 orders; stock 0 |
| 5 buyers, coupon with `usage_limit` 1 | Redeemed exactly once |
| Same customer double-clicks checkout | One order; the second request gets `cart_empty` |
| 3 simultaneous cancels | Stock restored exactly once |
| Cancel and checkout on the same product | No deadlock; stock consistent whichever runs first |

**Mutation-checked:** removing the product lock **and** the conditional `UPDATE` makes the oversell test fail. Removing the order lock or the promotion lock makes the double-cancel and coupon tests fail. With only the product lock removed, the conditional `UPDATE` still prevents overselling, which confirms the layering.

---

## 7. Database design and indexes

```
users ─┬─ carts (1:1) ── cart_items ── products
       └─ orders ── order_items ── products (nullable, snapshot kept)
promotions ── carts.promotion_id, orders.promotion_id (null on delete; orders keep promotion_code)
```

Money columns are unsigned integers (cents). Order totals are `BIGINT` so `price × quantity` can't overflow.

**Indexes (bonus):** each one exists for a specific query.

| Table | Index | Query it serves |
|---|---|---|
| `products` | `UNIQUE(sku)` | SKU uniqueness and lookups |
| `products` | `(status, price)` | Catalog listing always filters `status = active`; serves price ranges and price sorting |
| `products` | `(status, name)` | Name sort |
| `products` | `(status, created_at)` | Default "newest first" sort |
| `carts` | `UNIQUE(user_id)` | One cart per customer; the row checkout locks |
| `cart_items` | `UNIQUE(cart_id, product_id)` | One line per product (merging), "items of this cart"; also covers the `cart_id` FK |
| `promotions` | `UNIQUE(code)` | Code lookup; codes are stored upper-case, so it's case-insensitive in practice |
| `orders` | `(user_id, created_at)` | "My orders, newest first"; also covers the `user_id` FK |
| `orders` | `UNIQUE(user_id, idempotency_key)` | Idempotent checkout per customer (multiple `NULL`s allowed) |
| `orders` | `(promotion_id, user_id, status)` | Per-customer coupon usage count (`status != cancelled`); also covers the FK |

Searching by name uses `LIKE '%term%'`, which no B-tree index can serve. That's fine at this size. At scale I'd use a `FULLTEXT` index or a search engine (Meilisearch/Typesense via Scout).

---

## 8. Behaviour under high traffic

- **Different products scale in parallel.** Locks are per product row, so checkouts only queue behind each other when they buy the **same** product. Each lock is held only for a few short queries.
- **A single very hot product (flash sale)** is serialised on its row lock. That's correct but limits throughput to one checkout per lock-hold time for that item. Next steps would be atomic stock reservation in Redis (`DECRBY`) in front of the database, a queue-based checkout that confirms orders asynchronously, or splitting a SKU's stock into buckets.
- **Lock waits.** Under extreme contention, requests wait up to MySQL's `innodb_lock_wait_timeout` (default 50 s) and then fail. In production I'd lower that timeout and map lock-timeout errors to a retryable `409`/`503`.
- **Reads.** The product listing is cached per filter combination, on Redis under Sail. Any product write changes the catalog cache version **after commit**, so stale stock is never served. The cost is that heavy checkout traffic lowers the listing cache hit rate (see trade-offs).
- **Rate limiting (bonus).** 60 requests/min per user (or per IP for guests) on every API route; login and register are limited to 5/min.
- **Side effects** (email) run on the queue after commit, so they add no time to checkout.
- **Horizontal scaling.** The app is stateless (token auth, shared cache and queue), so more app servers can be added behind a load balancer. The database is the shared bottleneck, so read replicas could serve listing queries.

---

## 9. Trade-offs

| Decision | Why | Cost |
|---|---|---|
| Pessimistic locking at checkout | Simple, provably correct, easy to reason about | Hot products serialise (see §8) |
| Stock checked but **not reserved** when added to the cart | No abandoned-cart reservations to expire | A cart can go out of stock; `GET /api/cart` shows `is_available: false` and checkout re-checks |
| A coupon that became invalid makes checkout **fail** rather than be dropped | The customer never pays more than they expected | One extra step: remove the code and retry |
| Cancelling returns the coupon use | Fair to customers; cancelled orders don't count toward limits | Cancelling and re-ordering lets a customer reuse a one-time code (the old order is cancelled) |
| 403 for another customer's order | Matches the spec's "unauthorized order access" | Confirms the order ID exists; changing the policy to 404 would hide it |
| Integer cents, whole-number percentages, one currency | Exact arithmetic | No fractional percentages or multi-currency |
| Native `ENUM` columns | The database enforces valid values | Adding a value needs a migration |
| Catalog cache invalidated by a version key on every product write | Never serves stale stock; works on every cache store | Frequent stock changes lower the hit rate. Alternative: cache product data and read stock live. |
| Admin sets **absolute** stock | Matches a physical stock count | A checkout at the same moment can be overwritten (last write wins); a `+/-` adjustment endpoint would avoid that |
| Products can't be deleted, only deactivated; promotions can be | Order history keeps product links; orders keep the code snapshot | Removed products stay in the table |
| Sanctum tokens don't expire by default | Simple for API clients | Set `SANCTUM_EXPIRATION` in production |
| Hand-written OpenAPI attributes | Exact control over every error code and example | More text to maintain; tests fail if the docs drift from the routes |
| Concurrency tests use real processes | Exercise real database locks | About 20 s extra; skippable with `--exclude-group=concurrency` |

Developed and tested locally on MariaDB 10.4; the Sail setup uses MySQL 8.4. Only features common to both are used.

---

## 10. Testing

Tests run against the MySQL database `ecommerce-db_testing` (not SQLite, which ignores `FOR UPDATE`).

```bash
php artisan test --compact                               # everything (~25 s)
php artisan test --compact --exclude-group=concurrency   # quick run (~5 s)
php artisan test --compact --group=concurrency           # parallel-process tests only
```

| Suite | Covers |
|---|---|
| `tests/Feature/Http/Controllers/Api/*` | Every endpoint: success and stored state, validation, 401/403/404/409/422/429, cross-customer access |
| `tests/Feature/Services/PromotionEvaluatorTest.php` | Every coupon rule, SUMMER20 examples, rounding, caps, boundaries to the second, rule priority |
| `tests/Feature/Policies/*` | Order ownership and the admin gate |
| `tests/Feature/Models/*`, `tests/Unit/*` | Database guards (negative stock, enum values), money formatting, order statuses |
| `tests/Feature/Notifications/*` | Confirmation email content and HTML escaping |
| `tests/Feature/Database/DatabaseSeederTest.php` | Seeders are idempotent; the seeded customer can apply SUMMER20 and check out |
| `tests/Feature/ApiDocumentationTest.php` | The committed OpenAPI document matches the code; every route is documented |
| `tests/Concurrency/*` | Real parallel checkouts and cancellations (§6) |

---

## 11. Requirements map

| Requirement | Where |
|---|---|
| Products table & listing (search, price filter, availability, sorting, pagination), product details | [`create_products_table`](database/migrations/2026_10_07_145707_create_products_table.php), [`ProductCatalog`](app/Services/ProductCatalog.php), [`ProductIndexRequest`](app/Http/Requests/ProductIndexRequest.php) |
| Customer authentication; cart and order endpoints protected | [`AuthController`](app/Http/Controllers/Api/AuthController.php), `auth:sanctum` in [`routes/api.php`](routes/api.php) |
| Cart endpoints; can't exceed stock | [`CartService`](app/Services/CartService.php), [`InsufficientStockException`](app/Exceptions/InsufficientStockException.php) |
| Promotions (all fields), eligibility | [`create_promotions_table`](database/migrations/2026_10_07_145708_create_promotions_table.php), [`PromotionEvaluator`](app/Services/PromotionEvaluator.php) |
| Checkout steps 1–11 in one transaction, server-side prices | [`PlaceOrder`](app/Actions/PlaceOrder.php) |
| Orders and order items with name/SKU/price snapshot | [`create_order_items_table`](database/migrations/2026_10_07_145712_create_order_items_table.php), [`OrderItemResource`](app/Http/Resources/OrderItemResource.php) |
| Cancellation: status rules, restore stock, safe to repeat | [`CancelOrder`](app/Actions/CancelOrder.php), [`OrderStatus`](app/Enums/OrderStatus.php) |
| Concurrency: never negative | §6, [`CheckoutConcurrencyTest`](tests/Concurrency/CheckoutConcurrencyTest.php) |
| Validation and meaningful errors | Form Requests in [`app/Http/Requests`](app/Http/Requests), [`app/Exceptions`](app/Exceptions), [`bootstrap/app.php`](bootstrap/app.php) |
| **Bonus:** Redis caching · queue/event · idempotency · rate limiting · Docker/Sail · indexes · admin endpoints | [`ProductCatalog`](app/Services/ProductCatalog.php) · [`OrderPlaced`](app/Events/OrderPlaced.php) + [`SendOrderConfirmation`](app/Listeners/SendOrderConfirmation.php) · [`PlaceOrder`](app/Actions/PlaceOrder.php) · [`AppServiceProvider`](app/Providers/AppServiceProvider.php) · [`compose.yaml`](compose.yaml) · §7 · [`routes/admin.php`](routes/admin.php) |
