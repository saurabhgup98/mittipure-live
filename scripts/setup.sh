#!/usr/bin/env bash
# One-time (re-runnable) site setup. Run from the project folder:
#   docker compose run --rm cli bash /scripts/setup.sh
# Admin credentials come from ADMIN_USER / ADMIN_PASSWORD / ADMIN_EMAIL env
# vars (pass with -e); a random password is generated and printed otherwise.
set -euo pipefail

SITE_URL="${SITE_URL:-http://localhost:8081}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.test}"

if ! wp core is-installed 2>/dev/null; then
  ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')}"
  wp core install --url="$SITE_URL" --title="MittiPure (SDK test site)" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="$ADMIN_EMAIL" --skip-email
  echo ">>> wp-admin user: $ADMIN_USER (password = WP_ADMIN_PASSWORD in .env, or random if unset)"
fi

wp plugin install woocommerce woo-razorpay wc-variations-radio-buttons --activate
wp theme install storefront
wp theme activate mittipure-storefront

# Remove default demo content / plugins.
wp plugin delete hello akismet 2>/dev/null || true
wp post delete 1 2 --force 2>/dev/null || true

# Private test site: hide from search engines.
wp option update blog_public 0
# Same tagline and title pattern as gramiyum.in, branded MittiPure (e.g. "Pure cow ghee – MittiPure – Online Store …").
wp option update blogname "MittiPure – Online Store for Cold Pressed Oil and Natural Food Products"
wp option update blogdescription "Nothing Added. Nothing Extracted"
wp option update timezone_string "Asia/Kolkata"

# Same URL shapes as gramiyum.in: /product/<slug>/, /product-category/<parent>/<child>/
wp rewrite structure '/%postname%/'
wp option update woocommerce_permalinks '{"product_base":"/product","category_base":"product-category","tag_base":"product-tag","attribute_base":"","use_verbose_page_rules":false}' --format=json

# Store basics.
wp option update woocommerce_currency INR
wp option update woocommerce_default_country "IN:TN"
wp option update woocommerce_store_city "Coimbatore"
# Taxes on (gives products the "taxable" class like gramiyum.in), prices tax-inclusive, no rates -> totals unchanged.
wp option update woocommerce_calc_taxes yes
wp option update woocommerce_prices_include_tax yes
wp option update woocommerce_tax_display_shop incl
wp option update woocommerce_tax_display_cart incl
wp option update woocommerce_stock_format no_amount  # "In stock", not "501 in stock"
wp option update woocommerce_enable_signup_and_login_from_checkout no
wp option update woocommerce_enable_myaccount_registration yes
wp option update woocommerce_coming_soon no
wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json
wp option update woocommerce_task_list_hidden yes || true  # errors when already set

# Classic (shortcode) cart/checkout pages, like gramiyum.in — not the block versions.
for page in cart checkout; do
  id=$(wp option get "woocommerce_${page}_page_id")
  wp post update "$id" --post_content="[woocommerce_${page}]"
done


# Static front page using Storefront's homepage template (gramiyum.in's front page is a Page too).
home_id=$(wp post list --post_type=page --name=home --field=ID)
if [ -z "$home_id" ]; then
  home_id=$(wp post create --post_type=page --post_title=Home --post_name=home --post_status=publish --porcelain)
fi
wp post meta update "$home_id" _wp_page_template template-homepage.php
wp option update show_on_front page
wp option update page_on_front "$home_id"

# Shipping, tax, guest checkout and payment gateways (see the file for details).
wp eval-file /scripts/store-config.php

wp rewrite flush --hard
echo ">>> setup done"
