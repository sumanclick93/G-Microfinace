# Kraved — Homemade Cookie E-Commerce

Custom PHP 8.1+ MVC storefront for a single-vendor UK-style dessert ordering site.

## Requirements

- PHP 8.1+
- Apache with `mod_rewrite` (or nginx / PHP built-in server)
- Writable `storage/data/` folder (JSON file storage — **no MySQL required**)

## Setup

1. Edit `config/app.php` and set `url` to your public document root, e.g.:

```php
'url' => 'https://gmicrofinancefoundation.com/Kraved/public',
```

Or locally with PHP's built-in server:

```bash
cd public
php -S localhost:8080
```

Then set `'url' => 'http://localhost:8080'`.

2. Ensure `storage/` (and `storage/data/`) is writable by the web server.

3. Point the web server document root to `/public` (or open `/Kraved/public/` on shared hosting).

On first visit, sample catalogue + admin user are seeded into JSON files under `storage/data/`.

## Default admin

- URL: `/admin/login`
- Email: `admin@kraved.local`
- Password: `password`

Change this immediately after first login.

## Features

- Collection / Delivery fulfillment bar with postcode checker
- Sticky category menu, product grid, customisation modal (add-ons + box deals)
- AJAX cart drawer with free-delivery meter
- Guest / registered checkout (COD + card stub)
- Live order status tracker
- Admin dashboard, products, categories, add-ons, delivery zones, order board with poll notifications & thermal receipt

## Storage notes

- Data lives in `storage/data/*.json` (users, products, orders, etc.).
- `config/database.php` is unused while JSON mode is active.
- To re-seed: delete `storage/data/.seeded` and the JSON files (or empty `users.json` rows).
