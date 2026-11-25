# laravel-boilerplate

A REST-only **Laravel 11** API starter (PHP 8.2+, PostgreSQL) with Sanctum token auth, versioned routes,
consistent list endpoints and JSON errors. Pairs with the
[react-boilerplate](https://github.com/kevoecodes/react-boilerplate) admin frontend.

> **New machine?** Follow [SETUP.md](SETUP.md): copy-paste PowerShell steps for a fresh Windows 10/11 PC
> (plus a WSL2 appendix). On a machine that already has PHP, Composer and PostgreSQL, run `.\setup.ps1`.

## Features

- **Auth:** Laravel Sanctum personal access tokens: `login`, `logout`, `me`
- **PostgreSQL** (`pgsql` driver) for the app and the test suite
- **Sample resources:** Users, Products, Orders, each with a migration, model, factory, seeder, form requests,
  API resource and controller
- **List endpoints** with pagination, search, sort and filters, all using one query format and one response envelope
- **Standard JSON errors** for every API failure (401, 403, 404, 409, 422, 429, 500)
- **CORS** limited to the frontend origin (`FRONTEND_URL`, default `http://localhost:5173`)
- **Versioned** under `/api/v1`
- **Feature tests** for auth and the Products CRUD (PHPUnit)
- **Laravel Pint** for code style

## Quick start (PHP, Composer and PostgreSQL already installed)

```bash
git clone https://github.com/kevoecodes/laravel-boilerplate.git
cd laravel-boilerplate
composer install
cp .env.example .env          # then set DB_* and FRONTEND_URL
php artisan key:generate
php artisan migrate --seed
php artisan serve             # http://localhost:8000
```

**Default login:** `admin@example.com` / `password` (admin). Also seeded: `user@example.com` / `password`.

## API

Base URL: `http://localhost:8000/api/v1`. Send `Authorization: Bearer <token>` to every route except login.

| Method | Path | Description |
| --- | --- | --- |
| POST | `/auth/login` | `{ email, password, device_name? }` → `{ token, token_type, user }` (rate limited to 10/min) |
| GET | `/auth/me` | Current user |
| POST | `/auth/logout` | Revoke the current token |
| GET | `/dashboard` | Counts, revenue, orders by status, recent orders |
| GET/POST | `/users` | List / create |
| GET/PUT/PATCH/DELETE | `/users/{id}` | Show / update / delete (you can't delete yourself) |
| GET/POST | `/products` | List / create |
| GET/PUT/PATCH/DELETE | `/products/{id}` | Show / update / delete (409 if the product has orders) |
| GET/POST | `/orders` | List / create (`unit_price` defaults to the product price; `total` is calculated) |
| GET/PUT/PATCH/DELETE | `/orders/{id}` | Show / update / delete |

### List query format (all index endpoints)

| Param | Example | Notes |
| --- | --- | --- |
| `page` | `page=2` | 1-based |
| `per_page` | `per_page=25` | default 15, max 100 |
| `search` | `search=widget` | case-insensitive (`ILIKE`) across the resource's searchable columns |
| `sort` | `sort=name`, `sort=-price` | `-` prefix = descending; unknown columns fall back to the default sort |
| `filter[x]` | `filter[status]=active`, `filter[status]=pending,processing` | comma list = `WHERE IN` |

What each resource supports:

| Resource | Searchable | Sortable | Filters |
| --- | --- | --- | --- |
| users | name, email | id, name, email, role, is_active, created_at | `role`, `is_active` |
| products | name, sku, category | id, name, sku, category, price, stock, status, created_at | `status`, `category`, `min_price`, `max_price`, `in_stock` |
| orders | order_number, user name/email, product name | id, order_number, quantity, total, status, created_at | `status`, `user_id`, `product_id`, `from`, `to` (dates) |

### List response envelope

```json
{
  "data": [ { "id": 1, "name": "..." } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 50, "last_page": 4 }
}
```

Single records come back as `{ "data": { ... } }`.

### Error format

Every API error is JSON in the same shape:

```json
{ "message": "The given data was invalid.", "errors": { "email": ["The email field is required."] } }
```

`errors` appears only on 422 validation failures. Other examples: `401 {"message":"Unauthenticated."}`,
`404 {"message":"Resource not found."}`. JSON is returned even when the client forgets the
`Accept: application/json` header.

### Example

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}' | jq -r .token)

curl -s "http://localhost:8000/api/v1/products?search=pro&sort=-price&filter[status]=active&per_page=5" \
  -H "Authorization: Bearer $TOKEN"
```

## Project layout

```
app/
  Http/
    Controllers/Api/V1/   AuthController, UserController, ProductController, OrderController, DashboardController
    Middleware/           ForceJsonResponse (API always answers JSON)
    Requests/             Store/Update form requests per resource, Auth/LoginRequest
    Resources/            UserResource, ProductResource, OrderResource
  Models/                 User, Product, Order
  Support/
    ListQuery.php         search / sort / filter / paginate helper used by every index()
    ApiResponse.php       { data, meta } envelope + message responses
bootstrap/app.php         routing, middleware and JSON exception rendering
config/cors.php           allowed origins come from FRONTEND_URL
database/                 migrations, factories, seeders
routes/api.php            /api/v1 routes
tests/Feature/            AuthTest, ProductCrudTest
setup.ps1                 Windows project setup script
```

### Adding a new resource

1. `php artisan make:model Thing -mfs` (migration, factory, seeder)
2. Add `StoreThingRequest` / `UpdateThingRequest` in `app/Http/Requests/Thing/`
3. `php artisan make:resource ThingResource`
4. Copy `ProductController` and change the `ListQuery` searchable, sortable and filters lists
5. `Route::apiResource('things', ThingController::class);` inside the `auth:sanctum` group in `routes/api.php`

## Configuration

| Variable | Default | Purpose |
| --- | --- | --- |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `5432` | PostgreSQL server |
| `DB_DATABASE` | `laravel_boilerplate` | App database (tests use `laravel_boilerplate_test`, see `phpunit.xml`) |
| `DB_USERNAME` / `DB_PASSWORD` | `laravel` / `secret` | Database credentials |
| `FRONTEND_URL` | `http://localhost:5173` | CORS allowed origin(s), comma-separated |

## Tests and code style

The tests run against a separate PostgreSQL database, `laravel_boilerplate_test`. Create it once
(SETUP.md step 4 does this), then:

```bash
php artisan test          # or: composer test
composer lint             # Pint: fix style
composer lint:check       # Pint: check only (CI)
```

## License

MIT
