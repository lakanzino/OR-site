<?php
/**
 * Plugin Name: همیار جراحی — راهنمای علائم
 * Description: برگه مستقل قابل توسعه برای راهنمای علائم و منابع علمی.
 * Version: 0.5.0
 * Text Domain: hamyar-symptom-guide
 */
if (!defined('ABSPATH')) { exit; }
function hamyar_symptom_guide_activate() {
    $existing = get_option('hamyar_symptom_guide_page_id');
    if ($existing && get_post($existing)) { return; }
    $page_id = wp_insert_post(array(
        'post_title' => 'راهنمای علائم و بیماری‌های احتمالی',
        'post_name' => 'symptom-guide',
        'post_content' => '',
        'post_status' => 'draft',
        'post_type' => 'page',
    ), true);
    if (!is_wp_error($page_id)) { update_option('hamyar_symptom_guide_page_id', $page_id); }
}
register_activation_hook(__FILE__, 'hamyar_symptom_guide_activate');

/** The dedicated page uses a plugin template, not a shortcode or iframe. */
function hamyar_symptom_guide_template($template) {
    $page_id = (int) get_option('hamyar_symptom_guide_page_id');
    if (!$page_id || !is_page($page_id)) { return $template; }
    $base = plugin_dir_url(__FILE__) . 'app/';
    wp_enqueue_style('hamyar-symptom-guide', $base . 'css/style.css', array(), '0.5.0');
    wp_enqueue_script('hamyar-symptom-engine', $base . 'js/engine.js', array(), '0.5.0', true);
    wp_enqueue_script('hamyar-symptom-ui', $base . 'js/ui.js', array('hamyar-symptom-engine'), '0.5.0', true);
    wp_add_inline_script('hamyar-symptom-ui', 'window.hamyarDbUrl = ' . wp_json_encode($base . 'data/medical_db.json') . ';', 'before');
    // Only published articles are exposed. Drafts and private posts never leave WordPress.
    $links = array();
    $archive = post_type_exists('disease') ? get_post_type_archive_link('disease') : false;
    if ($archive) {
        $posts = get_posts(array('post_type' => 'disease', 'post_status' => 'publish', 'posts_per_page' => 5000, 'meta_key' => '_hj_disease_seed_id', 'fields' => 'ids', 'no_found_rows' => true));
        foreach ($posts as $id) {
            $key = get_post_meta($id, '_hj_disease_seed_id', true);
            $url = get_permalink($id);
            if ($key && $url) { $links[$key] = $url; }
        }
    }
    wp_add_inline_script('hamyar-symptom-ui', 'window.hamyarDiseaseLinks = ' . wp_json_encode((object) $links) . '; window.hamyarDiseaseArchive = ' . wp_json_encode($archive ? $archive : '') . ';', 'before');
    return plugin_dir_path(__FILE__) . 'page-symptom-guide.php';
}
add_filter('template_include', 'hamyar_symptom_guide_template', 99);
