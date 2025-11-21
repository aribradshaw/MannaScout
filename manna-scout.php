<?php
/*
Plugin Name: MannaScout
Description: Shortcode-driven multitab display for cannabis strains with a simple admin page to manage strains.
Version: 1.0.0
Author: MannaScout
*/

if (!defined('ABSPATH')) {
    exit;
}

// Constants
define('MANNA_SCOUT_OPTION_KEY', 'manna_scout_strains');
define('MANNA_SCOUT_SETTINGS_KEY', 'manna_scout_settings');
define('MANNA_SCOUT_PLUGIN_FILE', __FILE__);
define('MANNA_SCOUT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Ensure option exists on activation
register_activation_hook(__FILE__, function () {
    $existing = get_option(MANNA_SCOUT_OPTION_KEY, null);
    if ($existing === null) {
        add_option(MANNA_SCOUT_OPTION_KEY, []);
    }
});

// Utilities
function manna_scout_get_all_strains(): array {
    $strains = get_option(MANNA_SCOUT_OPTION_KEY, []);
    if (!is_array($strains)) {
        $strains = [];
    }
    return $strains;
}

function manna_scout_save_strains(array $strains): void {
    update_option(MANNA_SCOUT_OPTION_KEY, $strains, false);
}

function manna_scout_slugify(string $text): string {
    $text = strtolower($text);
    $text = remove_accents($text);
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    if (empty($text)) {
        $text = uniqid('strain-');
    }
    return sanitize_title($text);
}

function manna_scout_effect_keys(): array {
    return [
        'relaxation' => 'Relaxation',
        'euphoria' => 'Euphoria',
        'focus' => 'Focus',
        'energy' => 'Energy',
        'pain_relief' => 'Pain Relief',
    ];
}

// Admin Menu with two pages (Add + All)
add_action('admin_menu', function () {
    add_menu_page(
        'MannaScout',
        'MannaScout',
        'manage_options',
        'manna-scout',
        'manna_scout_add_page',
        'dashicons-admin-generic',
        58
    );
    // Top-level points to Add Strain
    add_submenu_page('manna-scout', 'Add Strain', 'Add Strain', 'manage_options', 'manna-scout', 'manna_scout_add_page');
    add_submenu_page('manna-scout', 'All Strains', 'All Strains', 'manage_options', 'manna-scout-all', 'manna_scout_list_page');
    add_submenu_page('manna-scout', 'Settings', 'Settings', 'manage_options', 'manna-scout-settings', 'manna_scout_settings_page');
});

// Admin Assets
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos((string) $hook, 'manna-scout') === false) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('manna-scout-admin', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-admin.css', [], '1.0.0');
    wp_enqueue_script('manna-scout-admin', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-admin.js', ['jquery'], '1.0.0', true);
});

// Handle Create/Update/Delete
add_action('admin_init', function () {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    // Handle settings save (before checking manna_scout_action)
    if (isset($_POST['ms_save_settings']) && isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'manna_scout_settings')) {
        $logo_url = isset($_POST['ms_logo_url']) ? esc_url_raw(wp_unslash($_POST['ms_logo_url'])) : '';
        $settings = get_option(MANNA_SCOUT_SETTINGS_KEY, []);
        $settings['logo_url'] = $logo_url;
        update_option(MANNA_SCOUT_SETTINGS_KEY, $settings);
        add_settings_error('manna_scout', 'settings_saved', 'Settings saved.', 'updated');
        return;
    }

    if (!isset($_POST['manna_scout_action'])) {
        // Handle GET delete on Add or All pages
        if (isset($_GET['page']) && in_array($_GET['page'], ['manna-scout', 'manna-scout-all'], true) && isset($_GET['ms_action']) && $_GET['ms_action'] === 'delete') {
            check_admin_referer('manna_scout_delete');
            $slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';
            $strains = manna_scout_get_all_strains();
            if ($slug && isset($strains[$slug])) {
                unset($strains[$slug]);
                manna_scout_save_strains($strains);
                add_settings_error('manna_scout', 'deleted', 'Strain deleted.', 'updated');
                wp_safe_redirect(add_query_arg(['page' => 'manna-scout-all'], admin_url('admin.php')));
                exit;
            }
        }
        // Handle bulk edit for show_in_slider
        if (isset($_GET['page']) && $_GET['page'] === 'manna-scout-all' && isset($_GET['ms_action']) && $_GET['ms_action'] === 'bulk_edit_slider') {
            check_admin_referer('manna_scout_bulk_edit');
            $slugs = isset($_GET['slugs']) ? array_map('sanitize_title', explode(',', wp_unslash($_GET['slugs']))) : [];
            $value = isset($_GET['value']) && $_GET['value'] === '1' ? true : false;
            $strains = manna_scout_get_all_strains();
            $updated = 0;
            foreach ($slugs as $slug) {
                if (isset($strains[$slug])) {
                    $strains[$slug]['show_in_slider'] = $value;
                    $updated++;
                }
            }
            if ($updated > 0) {
                manna_scout_save_strains($strains);
                add_settings_error('manna_scout', 'bulk_updated', sprintf('%d strain(s) updated.', $updated), 'updated');
            }
            wp_safe_redirect(add_query_arg(['page' => 'manna-scout-all'], admin_url('admin.php')));
            exit;
        }
        return;
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'manna_scout_save')) {
        wp_die('Security check failed');
    }

    $action = sanitize_text_field(wp_unslash($_POST['manna_scout_action']));
    $strains = manna_scout_get_all_strains();

    if (in_array($action, ['create', 'update'], true)) {
        $name = isset($_POST['ms_name']) ? sanitize_text_field(wp_unslash($_POST['ms_name'])) : '';
        $slug = isset($_POST['ms_slug']) && $_POST['ms_slug'] !== ''
            ? sanitize_title(wp_unslash($_POST['ms_slug']))
            : manna_scout_slugify($name);
        $photo_url = isset($_POST['ms_photo_url']) ? esc_url_raw(wp_unslash($_POST['ms_photo_url'])) : '';
        $description = isset($_POST['ms_description']) ? wp_kses_post(wp_unslash($_POST['ms_description'])) : '';
        $type = isset($_POST['ms_type']) ? sanitize_text_field(wp_unslash($_POST['ms_type'])) : 'hybrid';
        $terpenes_raw = isset($_POST['ms_terpenes']) ? sanitize_text_field(wp_unslash($_POST['ms_terpenes'])) : '';
        $terpenes = array_values(array_filter(array_map(function ($t) {
            return sanitize_text_field(trim($t));
        }, explode(',', $terpenes_raw))));

        $thc = isset($_POST['ms_thc']) ? floatval(wp_unslash($_POST['ms_thc'])) : 0.0;
        if (!is_finite($thc)) { $thc = 0.0; }
        $thc = max(0.0, min(100.0, $thc));

        // Gallery JSON (array of URLs)
        $gallery_json = isset($_POST['ms_gallery']) ? wp_unslash($_POST['ms_gallery']) : '[]';
        $gallery_arr = json_decode($gallery_json, true);
        if (!is_array($gallery_arr)) { $gallery_arr = []; }
        $gallery_urls = [];
        foreach ($gallery_arr as $u) {
            $u2 = esc_url_raw($u);
            if (!empty($u2)) { $gallery_urls[] = $u2; }
        }

        $effects = [];
        foreach (manna_scout_effect_keys() as $ekey => $elabel) {
            $val = isset($_POST['ms_effect_' . $ekey]) ? intval($_POST['ms_effect_' . $ekey]) : 0;
            $val = max(0, min(10, $val));
            $effects[$ekey] = $val;
        }

        $show_in_slider = isset($_POST['ms_show_in_slider']) && $_POST['ms_show_in_slider'] === '1' ? true : false;

        if ($name === '') {
            add_settings_error('manna_scout', 'missing_name', 'Name is required.', 'error');
        } else {
            $strains[$slug] = [
                'name' => $name,
                'slug' => $slug,
                'photo_url' => $photo_url,
                'description' => $description,
                'type' => in_array($type, ['indica', 'sativa', 'hybrid'], true) ? $type : 'hybrid',
                'terpenes' => $terpenes,
                'thc' => $thc,
                'effects' => $effects,
                'gallery' => $gallery_urls,
                'show_in_slider' => $show_in_slider,
            ];
            manna_scout_save_strains($strains);
            add_settings_error('manna_scout', 'saved', 'Strain saved.', 'updated');
            // Redirect to avoid resubmission
            wp_safe_redirect(add_query_arg(['page' => 'manna-scout', 'strain' => $slug], admin_url('admin.php')));
            exit;
        }
    }
});

// Admin Page Renderer (Add/Edit)
function manna_scout_add_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    settings_errors('manna_scout');

    $strains = manna_scout_get_all_strains();
    $editing_slug = isset($_GET['strain']) ? sanitize_title(wp_unslash($_GET['strain'])) : '';
    $editing = $editing_slug && isset($strains[$editing_slug]) ? $strains[$editing_slug] : null;

    $effects = manna_scout_effect_keys();
    ?>
    <div class="wrap">
        <h1>MannaScout</h1>
        <div class="manna-scout-admin">
            <div class="ms-admin-left">
                <h2><?php echo $editing ? 'Edit Strain' : 'Create New Strain'; ?></h2>
                <form method="post">
                    <?php wp_nonce_field('manna_scout_save'); ?>
                    <input type="hidden" name="manna_scout_action" value="<?php echo $editing ? 'update' : 'create'; ?>" />

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="ms_name">Name</label></th>
                            <td><input name="ms_name" type="text" id="ms_name" value="<?php echo $editing ? esc_attr($editing['name']) : ''; ?>" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_slug">Slug/ID</label></th>
                            <td><input name="ms_slug" type="text" id="ms_slug" value="<?php echo $editing ? esc_attr($editing['slug']) : ''; ?>" class="regular-text" placeholder="auto-generated from name"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_photo_url">Photo</label></th>
                            <td>
                                <div class="ms-photo-field">
                                    <input name="ms_photo_url" type="url" id="ms_photo_url" value="<?php echo $editing ? esc_url($editing['photo_url']) : ''; ?>" class="regular-text" placeholder="https://...">
                                    <button type="button" class="button ms-select-image">Select Image</button>
                                </div>
                                <?php if ($editing && !empty($editing['photo_url'])): ?>
                                    <div class="ms-photo-preview"><img src="<?php echo esc_url($editing['photo_url']); ?>" style="max-width:200px;height:auto;border-radius:6px;"/></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_type">Type</label></th>
                            <td>
                                <select name="ms_type" id="ms_type">
                                    <?php $types = ['indica' => 'Indica', 'sativa' => 'Sativa', 'hybrid' => 'Hybrid'];
                                    $sel = $editing ? $editing['type'] : 'hybrid';
                                    foreach ($types as $tval => $tlabel): ?>
                                        <option value="<?php echo esc_attr($tval); ?>" <?php selected($sel, $tval); ?>><?php echo esc_html($tlabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Gallery</th>
                            <td>
                                <?php $gallery = $editing && isset($editing['gallery']) && is_array($editing['gallery']) ? $editing['gallery'] : []; ?>
                                <input type="hidden" id="ms_gallery" name="ms_gallery" value='<?php echo esc_attr(wp_json_encode($gallery)); ?>' />
                                <div class="ms-gallery-thumbs">
                                    <?php foreach ($gallery as $g): ?>
                                        <div class="ms-thumb" data-url="<?php echo esc_url($g); ?>">
                                            <img src="<?php echo esc_url($g); ?>" />
                                            <button type="button" class="button-link ms-thumb-remove" aria-label="Remove">×</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="margin-top:8px; display:flex; gap:8px;">
                                    <button type="button" class="button ms-add-gallery">Add Images</button>
                                    <button type="button" class="button ms-clear-gallery">Clear</button>
                                </div>
                                <p class="description">Add multiple images; click × on a thumbnail to remove or use Clear to reset.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_thc">THC (%)</label></th>
                            <td>
                                <?php $thc_val = $editing && isset($editing['thc']) ? (float) $editing['thc'] : 0; ?>
                                <input name="ms_thc" type="number" id="ms_thc" value="<?php echo esc_attr($thc_val); ?>" class="small-text" step="0.1" min="0" max="100">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_terpenes">Terpenes</label></th>
                            <td>
                                <input name="ms_terpenes" type="text" id="ms_terpenes" value="<?php echo $editing ? esc_attr(implode(', ', (array) $editing['terpenes'])) : ''; ?>" class="regular-text" placeholder="e.g., Myrcene, Limonene, Caryophyllene">
                                <p class="description">Comma-separated list.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_description">Description</label></th>
                            <td>
                                <textarea name="ms_description" id="ms_description" rows="6" class="large-text"><?php echo $editing ? esc_textarea($editing['description']) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="ms_show_in_slider">Show in Slider</label></th>
                            <td>
                                <label>
                                    <input name="ms_show_in_slider" type="checkbox" id="ms_show_in_slider" value="1" <?php echo ($editing && isset($editing['show_in_slider']) && $editing['show_in_slider']) ? 'checked' : ''; ?>>
                                    Display this strain in the strain slider
                                </label>
                            </td>
                        </tr>
                    </table>

                    <h3>Effects (1–10)</h3>
                    <table class="form-table" role="presentation">
                        <?php foreach ($effects as $ekey => $elabel):
                            $val = $editing && isset($editing['effects'][$ekey]) ? intval($editing['effects'][$ekey]) : 0; ?>
                            <tr>
                                <th scope="row"><label for="ms_effect_<?php echo esc_attr($ekey); ?>"><?php echo esc_html($elabel); ?></label></th>
                                <td>
                                    <input type="range" min="0" max="10" step="1" id="ms_effect_<?php echo esc_attr($ekey); ?>" name="ms_effect_<?php echo esc_attr($ekey); ?>" value="<?php echo esc_attr($val); ?>" oninput="this.nextElementSibling.value = this.value">
                                    <output style="margin-left:8px;vertical-align:middle;"><?php echo esc_html($val); ?></output>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <?php submit_button($editing ? 'Update Strain' : 'Create Strain'); ?>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// Admin Page Renderer (List)
function manna_scout_list_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    settings_errors('manna_scout');
    $strains = manna_scout_get_all_strains();
    ?>
    <div class="wrap">
        <h1>All Strains</h1>
        <?php if (empty($strains)): ?>
            <p>No strains yet. <a href="<?php echo esc_url(add_query_arg(['page' => 'manna-scout'], admin_url('admin.php'))); ?>">Add your first strain</a>.</p>
        <?php else: ?>
            <form method="get" id="ms-bulk-form">
                <input type="hidden" name="page" value="manna-scout-all" />
                <?php wp_nonce_field('manna_scout_bulk_edit'); ?>
                <input type="hidden" name="ms_action" value="bulk_edit_slider" />
                <input type="hidden" name="slugs" id="ms-bulk-slugs" value="" />
                <input type="hidden" name="value" id="ms-bulk-value" value="" />
            </form>
            <div style="margin-bottom: 12px;">
                <button type="button" class="button" id="ms-bulk-select-all">Select All</button>
                <button type="button" class="button" id="ms-bulk-deselect-all">Deselect All</button>
                <button type="button" class="button" id="ms-bulk-enable-slider">Enable in Slider</button>
                <button type="button" class="button" id="ms-bulk-disable-slider">Disable in Slider</button>
            </div>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="ms-select-all-checkbox" /></th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Type</th>
                        <th>THC (%)</th>
                        <th>In Slider</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($strains as $slug => $strain): ?>
                    <tr>
                        <td><input type="checkbox" class="ms-strain-checkbox" value="<?php echo esc_attr($slug); ?>" /></td>
                        <td style="width: 80px;">
                            <?php if (!empty($strain['photo_url'])): ?>
                                <img src="<?php echo esc_url($strain['photo_url']); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:6px;" />
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($strain['name']); ?></td>
                        <td><code><?php echo esc_html($strain['slug']); ?></code></td>
                        <td><?php echo esc_html(ucfirst($strain['type'])); ?></td>
                        <td><?php echo isset($strain['thc']) ? esc_html((float) $strain['thc']) : '—'; ?></td>
                        <td><?php echo (isset($strain['show_in_slider']) && $strain['show_in_slider']) ? '✓' : '—'; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page' => 'manna-scout', 'strain' => $slug], admin_url('admin.php'))); ?>">Edit</a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => 'manna-scout-all', 'ms_action' => 'delete', 'slug' => $slug], admin_url('admin.php')), 'manna_scout_delete')); ?>" onclick="return confirm('Delete this strain?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h3 style="margin-top:24px;">Shortcode Usage</h3>
            <p>Use: <code>[MannaScout strains="Blue Dream, GSC"]</code> or by slugs: <code>[MannaScout slugs="blue-dream,gsc"]</code>.</p>
            <p>If no attribute is provided, all strains will be shown.</p>
            <p>Use <code>[strain_slider]</code> to display a slider of strains marked "Show in Slider".</p>
        <?php endif; ?>
    </div>
    <?php
}

// Shortcode
add_shortcode('MannaScout', 'manna_scout_shortcode');
add_shortcode('manna_scout', 'manna_scout_shortcode');
add_shortcode('strain_slider', 'manna_scout_slider_shortcode');

function manna_scout_shortcode($atts): string {
    $atts = shortcode_atts([
        'strains' => '', // comma-separated names
        'slugs' => '',   // comma-separated slugs
    ], $atts, 'MannaScout');

    $all = manna_scout_get_all_strains();

    $requested_slugs = [];

    if (!empty($atts['slugs'])) {
        $requested_slugs = array_values(array_filter(array_map(function ($s) { return sanitize_title(trim($s)); }, explode(',', $atts['slugs']))));
    } elseif (!empty($atts['strains'])) {
        $names = array_values(array_filter(array_map('trim', explode(',', $atts['strains']))));
        // Map names to slugs (case-insensitive)
        foreach ($names as $name) {
            foreach ($all as $slug => $strain) {
                if (strcasecmp($name, $strain['name']) === 0) {
                    $requested_slugs[] = $slug;
                    break;
                }
            }
        }
    } else {
        $requested_slugs = array_keys($all);
    }

    // Filter to existing strains and preserve order
    $selected = [];
    foreach ($requested_slugs as $slug) {
        if (isset($all[$slug])) {
            $selected[$slug] = $all[$slug];
        }
    }

    if (empty($selected)) {
        return '<div class="manna-scout-empty">No matching strains configured.</div>';
    }

    // Enqueue frontend assets once
    static $enqueued = false;
    if (!$enqueued) {
        wp_enqueue_style('manna-scout-frontend', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-frontend.css', [], '1.0.0');
        wp_enqueue_script('manna-scout-frontend', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-frontend.js', [], '1.0.0', true);
        $enqueued = true;
    }

    $uid = 'ms-' . wp_generate_uuid4();

    ob_start();
    ?>
    <div class="manna-scout" id="<?php echo esc_attr($uid); ?>">
        <div class="ms-tab-list" role="tablist">
            <?php $first = true; foreach ($selected as $slug => $strain): ?>
                <button class="ms-tab<?php echo $first ? ' active' : ''; ?>" role="tab" data-target="<?php echo esc_attr($uid . '-' . $slug); ?>"><?php echo esc_html($strain['name']); ?></button>
            <?php $first = false; endforeach; ?>
        </div>
        <div class="ms-tab-panels">
            <?php $first = true; foreach ($selected as $slug => $strain): $panel_id = $uid . '-' . $slug; ?>
                <div class="ms-tab-panel<?php echo $first ? ' active' : ''; ?>" id="<?php echo esc_attr($panel_id); ?>" role="tabpanel">
                    <div class="ms-panel">
                        <?php if (!empty($strain['photo_url'])): ?>
                            <div class="ms-photo">
                                <img src="<?php echo esc_url($strain['photo_url']); ?>" alt="<?php echo esc_attr($strain['name']); ?>" />
                                <?php $gallery = isset($strain['gallery']) && is_array($strain['gallery']) ? $strain['gallery'] : []; if (!empty($gallery)): ?>
                                    <div class="ms-gallery">
                                        <?php foreach ($gallery as $g): ?>
                                            <div class="ms-thumb"><img src="<?php echo esc_url($g); ?>" alt="" /></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="ms-content">
                            <div class="ms-header">
                                <h3 class="ms-name"><?php echo esc_html($strain['name']); ?></h3>
                                <span class="ms-type ms-type--<?php echo esc_attr($strain['type']); ?>"><?php echo esc_html(ucfirst($strain['type'])); ?></span>
                                <?php if (isset($strain['thc']) && $strain['thc'] !== ''): ?>
                                    <span class="ms-badge ms-badge--thc">THC <?php echo esc_html((float) $strain['thc']); ?>%</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($strain['description'])): ?>
                                <div class="ms-description"><?php echo wp_kses_post(wpautop($strain['description'])); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($strain['terpenes'])): ?>
                                <div class="ms-terpenes">
                                    <?php foreach ($strain['terpenes'] as $terp): ?>
                                        <span class="ms-pill"><?php echo esc_html($terp); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($strain['effects'])): ?>
                                <div class="ms-effects">
                                    <?php foreach (manna_scout_effect_keys() as $ekey => $elabel):
                                        $val = isset($strain['effects'][$ekey]) ? intval($strain['effects'][$ekey]) : 0;
                                        $pct = max(0, min(100, $val * 10)); ?>
                                        <div class="ms-effect">
                                            <div class="ms-effect-label"><?php echo esc_html($elabel); ?></div>
                                            <div class="ms-effect-bar">
                                                <div class="ms-effect-fill" style="width: <?php echo esc_attr($pct); ?>%"></div>
                                                <div class="ms-effect-value"><?php echo esc_html($val); ?>/10</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function manna_scout_slider_shortcode($atts): string {
    $all = manna_scout_get_all_strains();
    
    // Filter to only strains with show_in_slider enabled
    $slider_strains = [];
    foreach ($all as $slug => $strain) {
        if (isset($strain['show_in_slider']) && $strain['show_in_slider']) {
            $slider_strains[$slug] = $strain;
        }
    }
    
    if (empty($slider_strains)) {
        return '<div class="manna-scout-slider-empty">No strains configured for the slider. Enable "Show in Slider" on strain edit pages.</div>';
    }
    
    // Enqueue frontend assets once
    static $enqueued = false;
    if (!$enqueued) {
        wp_enqueue_style('manna-scout-frontend', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-frontend.css', [], '1.0.0');
        wp_enqueue_script('manna-scout-frontend', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-frontend.js', [], '1.0.0', true);
        wp_enqueue_script('manna-scout-slider', MANNA_SCOUT_PLUGIN_URL . 'manna-scout-slider.js', [], '1.0.0', true);
        $enqueued = true;
    }
    
    $uid = 'ms-slider-' . wp_generate_uuid4();
    
    // Get logo from settings
    $settings = get_option(MANNA_SCOUT_SETTINGS_KEY, []);
    $logo_url = isset($settings['logo_url']) && !empty($settings['logo_url']) ? $settings['logo_url'] : '';
    
    ob_start();
    ?>
    <div class="manna-scout-slider" id="<?php echo esc_attr($uid); ?>">
        <div class="ms-slider-container">
            <button class="ms-slider-arrow ms-slider-arrow-left" aria-label="Previous strain">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <div class="ms-slider-track">
                <div class="ms-slider-wrapper">
                    <?php foreach ($slider_strains as $slug => $strain): ?>
                        <div class="ms-slider-card" data-slug="<?php echo esc_attr($slug); ?>">
                            <?php if (!empty($strain['photo_url'])): ?>
                                <div class="ms-slider-image-wrapper">
                                    <img src="<?php echo esc_url($strain['photo_url']); ?>" alt="<?php echo esc_attr($strain['name']); ?>" class="ms-slider-image" />
                                    <?php if (!empty($logo_url)): ?>
                                        <div class="ms-slider-logo">
                                            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" />
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="ms-slider-name"><?php echo esc_html($strain['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="ms-slider-arrow ms-slider-arrow-right" aria-label="Next strain">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>
        <div class="ms-slider-details">
            <?php $first = true; foreach ($slider_strains as $slug => $strain): ?>
                <div class="ms-slider-detail<?php echo $first ? ' active' : ''; ?>" data-slug="<?php echo esc_attr($slug); ?>">
                    <div class="ms-slider-detail-content">
                        <div class="ms-slider-detail-header">
                            <h3 class="ms-slider-detail-name"><?php echo esc_html($strain['name']); ?></h3>
                            <span class="ms-slider-type ms-slider-type--<?php echo esc_attr($strain['type']); ?>"><?php echo esc_html(ucfirst($strain['type'])); ?></span>
                        </div>
                        <?php if (!empty($strain['description'])): ?>
                            <div class="ms-slider-detail-description"><?php echo wp_kses_post(wpautop($strain['description'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function manna_scout_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    settings_errors('manna_scout');
    
    $settings = get_option(MANNA_SCOUT_SETTINGS_KEY, []);
    $logo_url = isset($settings['logo_url']) ? $settings['logo_url'] : '';
    ?>
    <div class="wrap">
        <h1>MannaScout Settings</h1>
        <form method="post">
            <?php wp_nonce_field('manna_scout_settings'); ?>
            <input type="hidden" name="ms_save_settings" value="1" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ms_logo_url">Slider Logo</label></th>
                    <td>
                        <div class="ms-photo-field">
                            <input name="ms_logo_url" type="url" id="ms_logo_url" value="<?php echo esc_url($logo_url); ?>" class="regular-text" placeholder="https://...">
                            <button type="button" class="button ms-select-logo">Select Image</button>
                        </div>
                        <p class="description">Logo image to display on the top right of each strain card in the slider. Recommended size: 100-150px wide.</p>
                        <?php if (!empty($logo_url)): ?>
                            <div class="ms-photo-preview" style="margin-top: 8px;">
                                <img src="<?php echo esc_url($logo_url); ?>" style="max-width:200px;height:auto;border-radius:6px;"/>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}


