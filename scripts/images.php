<?php
/**
 * Replaces the generated placeholder product images with real photos
 * (free Unsplash photos listed in product-images.json).
 *
 *   docker compose run --rm cli wp eval-file /scripts/images.php
 *
 * Keeps every attachment id (so ids in the markup don't change); only the
 * file behind it is swapped for a JPEG. Safe to run again: attachments that
 * already have their photo are skipped.
 */

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

$photos = json_decode(file_get_contents(__DIR__ . '/product-images.json'), true);

$attachments = get_posts([
    'post_type'   => 'attachment',
    'numberposts' => -1,
    'meta_key'    => '_gram_placeholder',
]);

$done = 0;
foreach ($attachments as $a) {
    $key = get_post_meta($a->ID, '_gram_placeholder', true);
    // "<slug>" = main image, "<slug>-N" = gallery image N.
    $slug = $key;
    $n    = 1;
    if (!isset($photos[$slug]) && preg_match('/^(.+)-(\d+)$/', $key, $m) && isset($photos[$m[1]])) {
        $slug = $m[1];
        $n    = (int) $m[2];
    }
    if (empty($photos[$slug])) {
        WP_CLI::warning("no photo for $key");
        continue;
    }
    $photo = $photos[$slug][($n - 1) % count($photos[$slug])];
    if (get_post_meta($a->ID, '_mittipure_photo', true) === $photo) {
        continue;
    }

    $tmp = download_url("https://images.unsplash.com/$photo?w=800&h=800&fit=crop&q=80&fm=jpg");
    if (is_wp_error($tmp)) {
        WP_CLI::warning("$key: " . $tmp->get_error_message());
        continue;
    }

    // Remove the old file and its resized copies, then put the photo in its place.
    wp_delete_attachment_files($a->ID, wp_get_attachment_metadata($a->ID), get_post_meta($a->ID, '_wp_attachment_backup_sizes', true), get_attached_file($a->ID));
    $upload = wp_upload_dir();
    $file   = wp_unique_filename($upload['path'], "$key.jpg");
    $file   = trailingslashit($upload['path']) . $file;
    rename($tmp, $file);
    chmod($file, 0644);

    update_attached_file($a->ID, $file);
    wp_update_post(['ID' => $a->ID, 'post_mime_type' => 'image/jpeg', 'guid' => $upload['url'] . '/' . basename($file)]);
    wp_update_attachment_metadata($a->ID, wp_generate_attachment_metadata($a->ID, $file));
    update_post_meta($a->ID, '_wp_attachment_image_alt', get_the_title($a->ID));
    update_post_meta($a->ID, '_mittipure_photo', $photo);
    $done++;
}

WP_CLI::success("$done images replaced (" . count($attachments) . ' product images in total)');
