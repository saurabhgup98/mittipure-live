<?php
/**
 * Loads scripts/seed-data.json (from fetch-catalogue.mjs) into WooCommerce.
 * Idempotent: categories/attributes/products are matched by slug and updated.
 *
 *   docker compose run --rm cli wp eval-file /scripts/seed.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$data = json_decode(file_get_contents(__DIR__ . '/seed-data.json'), true);
if (!$data) {
    WP_CLI::error('seed-data.json missing or invalid');
}

/* ---------- categories (same slugs + parent structure as gramiyum.in) ---------- */

$cat_ids = [];
foreach ($data['categories'] as $order => $c) {
    $parent = $c['parent'] ? $cat_ids[$c['parent']] : 0;
    $args   = ['slug' => $c['slug'], 'parent' => $parent, 'description' => html_entity_decode($c['description'])];
    $term   = get_term_by('slug', $c['slug'], 'product_cat');
    $res    = $term
        ? wp_update_term($term->term_id, 'product_cat', $args + ['name' => $c['name']])
        : wp_insert_term($c['name'], 'product_cat', $args);
    if (is_wp_error($res)) {
        WP_CLI::error("category {$c['slug']}: " . $res->get_error_message());
    }
    $cat_ids[$c['slug']] = (int) $res['term_id'];
    update_term_meta($cat_ids[$c['slug']], 'order', $order);
}

/* ---------- global attributes: pa_weight, pa_volume ---------- */

function gram_attribute_taxonomy(string $taxonomy, string $label): string
{
    $slug = substr($taxonomy, 3); // strip "pa_"
    if (!wc_attribute_taxonomy_id_by_name($slug)) {
        $id = wc_create_attribute(['name' => $label, 'slug' => $slug, 'type' => 'select', 'order_by' => 'menu_order']);
        if (is_wp_error($id)) {
            WP_CLI::error("attribute $taxonomy: " . $id->get_error_message());
        }
        // Register now so terms can be inserted in this same request.
        register_taxonomy($taxonomy, ['product', 'product_variation'], ['hierarchical' => false]);
    }
    return $taxonomy;
}

function gram_term_id(string $taxonomy, string $name): int
{
    $slug = sanitize_title($name);
    $term = get_term_by('slug', $slug, $taxonomy);
    if ($term) {
        return (int) $term->term_id;
    }
    $res = wp_insert_term($name, $taxonomy, ['slug' => $slug]);
    if (is_wp_error($res)) {
        WP_CLI::error("term $taxonomy/$name: " . $res->get_error_message());
    }
    return (int) $res['term_id'];
}

/* ---------- placeholder product image (we don't copy gramiyum's photos) ---------- */

function gram_placeholder_image(string $slug, string $title, string $caption = 'MITTIPURE - SDK TEST SITE'): int
{
    $existing = get_posts(['post_type' => 'attachment', 'meta_key' => '_gram_placeholder', 'meta_value' => $slug, 'fields' => 'ids', 'numberposts' => 1]);
    if ($existing) {
        return (int) $existing[0];
    }

    $w   = 800;
    $img = imagecreatetruecolor($w, $w);
    imagefill($img, 0, 0, imagecolorallocate($img, 0xEE, 0xF7, 0xF0));
    imagefilledrectangle($img, 0, $w - 90, $w, $w, imagecolorallocate($img, 0x00, 0x97, 0x3E));
    $ink   = imagecolorallocate($img, 0x1F, 0x2D, 0x24);
    $lines = explode("\n", wordwrap($title, 26, "\n", true));
    $y     = (int) (($w - count($lines) * 40) / 2);
    foreach ($lines as $line) {
        // Built-in font 5 is 9x15px; scale up by drawing onto a small canvas.
        $small = imagecreatetruecolor(strlen($line) * 9, 15);
        imagefill($small, 0, 0, imagecolorallocate($small, 0xEE, 0xF7, 0xF0));
        imagestring($small, 5, 0, 0, $line, imagecolorallocate($small, 0x1F, 0x2D, 0x24));
        $sw = imagesx($small) * 2.6;
        imagecopyresampled($img, $small, (int) (($w - $sw) / 2), $y, 0, 0, (int) $sw, 39, imagesx($small), 15);
        imagedestroy($small);
        $y += 44;
    }
    imagestring($img, 5, 30, $w - 55, $caption, imagecolorallocate($img, 0xFF, 0xFF, 0xFF));

    $upload = wp_upload_dir();
    $file   = trailingslashit($upload['path']) . $slug . '.png';
    imagepng($img, $file);
    imagedestroy($img);

    $id = wp_insert_attachment(['post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit'], $file);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
    update_post_meta($id, '_gram_placeholder', $slug);
    return $id;
}

/* ---------- products ---------- */

// Create posts with gramiyum.in's own post IDs (products + variations), so
// ids in the markup (post-345628, data-product_id, add-to-cart=…) match.
$gram_next_id = 0;
$force_id     = function (array $data) use (&$gram_next_id) {
    if ($gram_next_id) {
        $data['import_id'] = $gram_next_id;
        $gram_next_id      = 0;
    }
    return $data;
};
add_filter('woocommerce_new_product_data', $force_id);
add_filter('woocommerce_new_product_variation_data', $force_id);

function gram_delete_product(int $id): void
{
    $product = wc_get_product($id);
    if ($product) {
        foreach ($product->get_children() as $child_id) {
            wp_delete_post($child_id, true);
        }
    }
    wp_delete_post($id, true);
}

$term_order = []; // attribute term display order = first appearance in seed data

foreach ($data['products'] as $index => $p) {
    $class = $p['type'] === 'variable' ? 'WC_Product_Variable' : 'WC_Product_Simple';

    $post = get_page_by_path($p['slug'], OBJECT, 'product');
    if ($post && ($post->ID !== (int) $p['source_id'] || get_class(wc_get_product($post->ID)) !== $class)) {
        gram_delete_product($post->ID);
        $post = null;
    }
    if (!$post && get_post($p['source_id'])) {
        WP_CLI::error("post #{$p['source_id']} already exists and is not {$p['slug']}");
    }
    if ($post) {
        $product = wc_get_product($post->ID);
    } else {
        $product      = new $class();
        $gram_next_id = (int) $p['source_id'];
    }

    $product->set_name($p['name']);
    $product->set_slug($p['slug']);
    $product->set_status('publish');
    $product->set_catalog_visibility('visible');
    $product->set_sku($p['sku']);
    $product->set_short_description($p['short_description']);
    $product->set_description($p['description']);
    $product->set_category_ids(array_map(fn ($s) => $cat_ids[$s], $p['categories']));
    $product->set_tag_ids(array_map(function ($t) {
        $term = get_term_by('slug', $t['slug'], 'product_tag') ?: (object) wp_insert_term($t['name'], 'product_tag', ['slug' => $t['slug']]);
        return (int) $term->term_id;
    }, $p['tags']));
    $product->set_image_id(gram_placeholder_image($p['slug'], $p['name']));
    // Extra gallery images (gramiyum.in cards use the 2nd one as hover image).
    $gallery = [];
    for ($i = 2; $i <= ($p['image_count'] ?? 1); $i++) {
        $gallery[] = gram_placeholder_image($p['slug'] . '-' . $i, $p['name'], 'GALLERY IMAGE ' . $i);
    }
    $product->set_gallery_image_ids($gallery);
    // seed-data lists products in gramiyum.in popularity order; keep "Sort by popularity" identical.
    $product->set_total_sales(1000 - $index);
    $product->set_stock_status('instock');
    $product->set_manage_stock($p['stock_quantity'] !== null);
    $product->set_stock_quantity($p['stock_quantity']);
    if ($p['weight'] !== '') {
        $product->set_weight($p['weight']);
    }

    if ($p['type'] === 'variable') {
        $attrs = [];
        foreach ($p['attributes'] as $i => $a) {
            $tax      = gram_attribute_taxonomy($a['taxonomy'], ucfirst($a['name']));
            $term_ids = array_map(fn ($o) => gram_term_id($tax, $o), $a['options']);
            foreach ($term_ids as $tid) {
                if (!isset($term_order[$tid])) {
                    $term_order[$tid] = count($term_order);
                    update_term_meta($tid, 'order', $term_order[$tid]);
                }
            }
            $attr = new WC_Product_Attribute();
            $attr->set_id(wc_attribute_taxonomy_id_by_name(substr($tax, 3)));
            $attr->set_name($tax);
            $attr->set_options($term_ids);
            $attr->set_position($i);
            $attr->set_visible(true);
            $attr->set_variation(true);
            $attrs[] = $attr;
        }
        $product->set_attributes($attrs);
        $product->set_default_attributes($p['default_attributes'] ?? []);
        $product_id = $product->save();

        // Rebuild variations from scratch each run, with gramiyum's variation ids.
        foreach ($product->get_children() as $child_id) {
            wp_delete_post($child_id, true);
        }
        $name_to_tax = array_column($p['attributes'], 'taxonomy', 'name');
        foreach ($p['variations'] as $v) {
            if (get_post($v['source_id'])) {
                WP_CLI::error("post #{$v['source_id']} already exists (variation of {$p['slug']})");
            }
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            $va = [];
            foreach ($v['attributes'] as $attr_name => $term_slug) {
                $va[$name_to_tax[$attr_name]] = $term_slug;
            }
            $variation->set_attributes($va);
            $variation->set_sku($v['sku']);
            $variation->set_regular_price($v['regular_price']);
            $variation->set_sale_price($v['sale_price']);
            $variation->set_manage_stock($v['stock_quantity'] !== null);
            $variation->set_stock_quantity($v['stock_quantity']);
            $variation->set_stock_status($v['in_stock'] ? 'instock' : 'outofstock');
            $gram_next_id = (int) $v['source_id'];
            $variation->save();
        }
        WC_Product_Variable::sync($product_id);
    } else {
        $product->set_regular_price($p['regular_price']);
        $product->set_sale_price($p['sale_price']);
        $product_id = $product->save();
    }

    if ($product_id !== (int) $p['source_id']) {
        WP_CLI::warning("{$p['slug']}: got #$product_id, expected #{$p['source_id']}");
    }
    WP_CLI::log(sprintf('%-50s #%d %s', $p['slug'], $product_id, $p['type']));
}

wc_delete_product_transients();
WP_CLI::success(count($data['categories']) . ' categories, ' . count($data['products']) . ' products seeded');
