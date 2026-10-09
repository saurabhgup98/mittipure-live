# MittiPure — WordPress test store (gramiyum.in markup copy)

A private, local WooCommerce store for testing the APTI tracking SDK. The
store is branded **MittiPure**, but its HTML (ids, classes, form field names),
URLs and login/checkout request/response shapes copy
[gramiyum.in](https://gramiyum.in) (GreenMart theme + Digits login). SDK rules
written for gramiyum.in's selectors should work here unchanged.

Not a real shop: free Unsplash product photos (not gramiyum's), test data only, `noindex`.

Product photos: `scripts/product-images.json` (Unsplash photo per product slug)
→ `scripts/images.php` swaps the seed's placeholder files for them, keeping the
attachment ids. If a download fails with a cURL TLS error, run it again.

## Run it

```bash
cp .env.example .env            # fill in passwords
docker compose up -d            # http://localhost:8081
set -a; . ./.env; set +a
# Git Bash: prefix docker commands with MSYS_NO_PATHCONV=1
docker compose run --rm -e ADMIN_PASSWORD="$WP_ADMIN_PASSWORD" cli bash /scripts/setup.sh
docker compose run --rm cli wp eval-file /scripts/seed.php
docker compose run --rm cli wp eval-file /scripts/images.php   # real product photos
```

The scripts can be run again safely. wp-admin: `/wp-admin/`, user `admin`,
password = `WP_ADMIN_PASSWORD` in `.env`.

Razorpay (test keys only, `rzp_test_…`):

```bash
docker compose run --rm -e RAZORPAY_KEY_ID=rzp_test_xxx -e RAZORPAY_KEY_SECRET=xxx \
  cli wp eval-file /scripts/store-config.php
```

Load the SDK: set `APTI_SDK_SRC` in `wp-config-extra.php` (printed in `<head>`
on every page). For ngrok, change `WP_HOME`/`WP_SITEURL` there and
`docker compose restart wordpress`.

## What matches gramiyum.in

| Area | URL | Notes |
|---|---|---|
| Header + mega-menu | every page | `#tbay-header`, nav ids, random `menu-1-XXXXX` ids, mega-menu filled on hover |
| Product | `/product/<slug>/` | variable (radio `pa_weight`/`pa_volume`) and simple; same post/variation ids |
| Category | `/product-category/<parent>/<child>/` | 10 categories, 2 products each |
| Shop | `/shop/` | default sort = popularity |
| Cart | `/cart/` | `wpr-shop-table`, coupon list link, `#prowc_empty_cart`, flat rate ₹50 (`flat_rate:4`), 5% GST included |
| Checkout | `/checkout/` | guests allowed; same fields, Digits hidden fields, `#place_order onclick="verifyOTPbilling(10)"`; payments: Razorpay, Easebuzz (placeholder), COD |
| Thank-you | `/checkout/order-received/<id>/?key=wc_order_…` | WooCommerce standard |
| Login | `/?login=true&redirect_to&page=1` | Digits look-alike (below) |

`node scripts/compare-dom.mjs` compares the page structure with gramiyum.in
(header, products, category, shop, cart empty/1 item, checkout). Last run:
everything the same except one LiteSpeed `<link>` in gramiyum's `<head>`.

Kept on purpose: `data-id="gramiyum-menu"` on the nav (it's a selector), and
comments that say which gramiyum.in markup a file copies.

## Login / signup (Digits look-alike)

- Page: `/?login=true&redirect_to&page=1` (logged-out `/my-account/` redirects there, 302).
- **OTP is always `123456`.** No SMS is sent.
- All steps POST to `/wp-admin/admin-ajax.php` with `action=digits_forms_ajax`,
  `type=login|register|forgot`, nonce field `digits_form`.
- Responses:
  - next step: `{success:true, data:{html, fill}}`
  - done: `{success:true, data:{process:true, process_type:"login"|"register", redirect, message, login_reg_success_msg:1}}`
  - error: `{success:false, data:{message, notice}}` (e.g. "Please signup before logging in.", "Invalid OTP!", "Mobile Number already in use!")
- After login the browser goes to `/?redirect_to` (301 from `//host/?redirect_to&page=1`, same as gramiyum.in);
  the header shows the user's first name in `.text-account`.
- Register fields: `digt_countrycode`, `phone`, `email`, `digits_reg_name`, `digits_reg_password`, then `sms_otp`.

## Test data

- Test phones: use `+91 90000000xx`. Emails: `*@example.test`.
- Existing test user: Ravi, +91 9000000002, sdk.test2@example.test (two COD test orders).
- Payment: Cash on delivery works now. Razorpay needs test keys (above). Easebuzz always fails with a notice.

## Files

- `wp-content/themes/mittipure-storefront/` — Storefront child theme with gramiyum's markup (header, product, archive, cart, checkout templates; `inc/checkout.php` for plugin markup).
- `wp-content/mu-plugins/gram-digits-login/` — Digits look-alike login.
- `wp-content/mu-plugins/sdk-tracking.php` — SDK `<script>` tag.
- `scripts/setup.sh`, `scripts/store-config.php`, `scripts/seed.php` (+ `seed-data.json` from `fetch-catalogue.mjs`), `scripts/compare-dom.mjs`.

## Not done yet

- Razorpay payment test (needs test Key ID/Secret).
- Logged-in My Account pages: gramiyum.in's logged-in markup is unknown.
- Wallet / multiple addresses plugins (skipped on purpose).
- ngrok / Pantheon hosting.
