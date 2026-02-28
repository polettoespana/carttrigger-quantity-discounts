<?php

/**
 * Plugin Name:  CartTrigger – Quantity Discounts
 * Plugin URI:   https://poletto.es/nuestros-servicios/eficiencia/ct-quantity-discount
 * Description:  Cart notice and automatic discount triggered by item quantity, configurable per rule, category, or SKU.
 * Version:      2.3.1
 * Author:       Poletto 1976 S.L.U.
 * Author URI:   https://poletto.es
 * License:      GPLv2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  carttrigger-quantity-discounts
 * Domain Path:  /languages
 * Requires Plugins: woocommerce
 * WC tested up to: 10.5.2
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PBD_VERSION',    '2.3.1');
define('PBD_OPTION_KEY', 'pbd_settings');

// ─────────────────────────────────────────────────────────────────────────────
// WOOCOMMERCE COMPATIBILITY – HPOS (Custom Order Tables)
// ─────────────────────────────────────────────────────────────────────────────

add_action('before_woocommerce_init', function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// TRANSLATION PLUGIN COMPATIBILITY (WPML, Polylang, generic filter)
// ─────────────────────────────────────────────────────────────────────────────

// Register translatable strings once translation plugins are ready.
// Priority 20 ensures WPML/Polylang are already initialised.
add_action('init', 'pbd_register_translatable_strings', 20);

function pbd_register_translatable_strings(): void
{
    $s        = pbd_get_settings();
    $template = $s['notice_template'];
    $product  = $s['product_notice_template'];

    // WPML – String Translation
    if (function_exists('icl_register_string')) {
        icl_register_string('carttrigger-quantity-discounts', 'notice_template', $template);
        if ($product) {
            icl_register_string('carttrigger-quantity-discounts', 'product_notice_template', $product);
        }
    }

    // Polylang – String translations
    if (function_exists('pll_register_string')) {
        pll_register_string('notice_template', $template, 'carttrigger-quantity-discounts');
        if ($product) {
            pll_register_string('product_notice_template', $product, 'carttrigger-quantity-discounts');
        }
    }
}

/**
 * Returns the notice_template translated into the current site language,
 * with support for WPML, Polylang, and a generic filter for other plugins.
 */
function pbd_get_translated_template(string $template): string
{
    if (function_exists('icl_t')) {
        return icl_t('carttrigger-quantity-discounts', 'notice_template', $template);
    }
    if (function_exists('pll__')) {
        return pll__($template);
    }
    return apply_filters('pbd_notice_template', $template);
}

/**
 * Returns the product_notice_template translated. Falls back to notice_template if empty.
 */
function pbd_get_translated_product_template(string $template, string $fallback): string
{
    if (empty($template)) {
        return pbd_get_translated_template($fallback);
    }
    if (function_exists('icl_t')) {
        return icl_t('carttrigger-quantity-discounts', 'product_notice_template', $template);
    }
    if (function_exists('pll__')) {
        return pll__($template);
    }
    return apply_filters('pbd_product_notice_template', $template);
}

// ─────────────────────────────────────────────────────────────────────────────
// SETTINGS
// ─────────────────────────────────────────────────────────────────────────────

function pbd_get_settings(): array
{
    $defaults = [
        'rules' => [
            [
                'trigger_qty'    => 4,
                'target_qty'     => 6,
                'discount_type'  => 'percent',
                'discount_value' => 10,
                'discount_scope' => 'cart',
                'category_ids'   => [],
                'skus'           => [],
            ],
            [
                'trigger_qty'    => 5,
                'target_qty'     => 6,
                'discount_type'  => 'percent',
                'discount_value' => 5,
                'discount_scope' => 'cart',
                'category_ids'   => [],
                'skus'           => [],
            ],
        ],
        'notice_template'         => 'You have {current} items in your cart. Add {missing} more and get {discount} off!',
        'notice_type'             => 'notice',
        'notice_class'            => '',
        'display_location'        => 'cart',  // 'cart' | 'product' | 'both'
        'product_notice_template' => '',      // empty = copy from cart template
        'product_notice_class'    => '',
        'conflict_mode'           => 'stack', // 'stack' | 'skip' | 'best'
    ];

    $saved = get_option(PBD_OPTION_KEY, []);
    if (empty($saved)) {
        return $defaults;
    }

    return [
        'rules'                   => ! empty($saved['rules']) ? $saved['rules'] : $defaults['rules'],
        'notice_template'         => $saved['notice_template']         ?? $defaults['notice_template'],
        'notice_type'             => $saved['notice_type']             ?? $defaults['notice_type'],
        'notice_class'            => $saved['notice_class']            ?? $defaults['notice_class'],
        'display_location'        => $saved['display_location']        ?? $defaults['display_location'],
        'product_notice_template' => $saved['product_notice_template'] ?? $defaults['product_notice_template'],
        'product_notice_class'    => $saved['product_notice_class']    ?? $defaults['product_notice_class'],
        'conflict_mode'           => $saved['conflict_mode']           ?? $defaults['conflict_mode'],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// CATEGORY LOGIC
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns all category IDs including children (recursive).
 *
 * @param int[] $cat_ids
 * @return int[]
 */
function pbd_expand_category_ids(array $cat_ids): array
{
    $all = $cat_ids;
    foreach ($cat_ids as $id) {
        $children = get_term_children((int) $id, 'product_cat');
        if (! is_wp_error($children) && ! empty($children)) {
            $all = array_merge($all, $children);
        }
    }
    return array_unique(array_map('intval', $all));
}

/**
 * Returns the normalised SKU list for a rule.
 * Accepts both an array and a CSV string. SKUs take priority over categories.
 *
 * @param array $rule
 * @return string[]
 */
function pbd_get_rule_skus(array $rule): array
{
    $raw = $rule['skus'] ?? [];
    if (is_string($raw)) {
        $raw = explode(',', $raw);
    }
    return array_values(array_filter(array_map('trim', (array) $raw)));
}

/**
 * Counts the cart units that match the rule's category/SKU filter.
 * SKUs take priority over category. An empty filter counts all products.
 *
 * @param array $rule
 * @return int
 */
function pbd_count_qty_for_rule(array $rule): int
{
    if (! WC()->cart) {
        return 0;
    }

    $skus = pbd_get_rule_skus($rule);

    if (! empty($skus)) {
        $total = 0;
        foreach (WC()->cart->get_cart() as $item) {
            $product_id = $item['variation_id'] ?: $item['product_id'];
            $product    = wc_get_product($product_id);
            if ($product && in_array($product->get_sku(), $skus, true)) {
                $total += (int) $item['quantity'];
            }
        }
        return $total;
    }

    $cat_ids      = array_filter(array_map('intval', (array) ($rule['category_ids'] ?? [])));
    $expanded_ids = empty($cat_ids) ? [] : pbd_expand_category_ids($cat_ids);

    $total = 0;
    foreach (WC()->cart->get_cart() as $item) {
        $qty = (int) $item['quantity'];

        if (empty($expanded_ids)) {
            $total += $qty;
        } else {
            $product_cats = wc_get_product_term_ids($item['product_id'], 'product_cat');
            if (! empty(array_intersect($product_cats, $expanded_ids))) {
                $total += $qty;
            }
        }
    }

    return $total;
}

/**
 * Calculates the subtotal for cart items that match the rule's category/SKU filter.
 * SKUs take priority over category.
 *
 * @param array $rule
 * @return float
 */
function pbd_get_category_subtotal(array $rule): float
{
    if (! WC()->cart) {
        return 0.0;
    }

    $skus = pbd_get_rule_skus($rule);

    if (! empty($skus)) {
        $total = 0.0;
        foreach (WC()->cart->get_cart() as $item) {
            $product_id = $item['variation_id'] ?: $item['product_id'];
            $product    = wc_get_product($product_id);
            if ($product && in_array($product->get_sku(), $skus, true)) {
                $total += (float) $item['line_subtotal'];
            }
        }
        return $total;
    }

    $cat_ids      = array_filter(array_map('intval', (array) ($rule['category_ids'] ?? [])));
    $expanded_ids = empty($cat_ids) ? [] : pbd_expand_category_ids($cat_ids);

    $total = 0.0;
    foreach (WC()->cart->get_cart() as $item) {
        if (empty($expanded_ids)) {
            $total += (float) $item['line_subtotal'];
        } else {
            $product_cats = wc_get_product_term_ids($item['product_id'], 'product_cat');
            if (! empty(array_intersect($product_cats, $expanded_ids))) {
                $total += (float) $item['line_subtotal'];
            }
        }
    }

    return $total;
}

// ─────────────────────────────────────────────────────────────────────────────
// FORMATTING
// ─────────────────────────────────────────────────────────────────────────────

function pbd_format_discount(array $rule): string
{
    if ($rule['discount_type'] === 'percent') {
        return $rule['discount_value'] . '%';
    }
    return wc_price($rule['discount_value']);
}

function pbd_calculate_discount_amount(array $rule, float $subtotal): float
{
    if ($rule['discount_type'] === 'percent') {
        return round($subtotal * ((float) $rule['discount_value'] / 100), 2);
    }
    return max(0.0, (float) $rule['discount_value']);
}

// ─────────────────────────────────────────────────────────────────────────────
// CART NOTICE
// ─────────────────────────────────────────────────────────────────────────────

add_action('woocommerce_before_cart',          'pbd_show_cart_notices');
add_action('woocommerce_before_checkout_form', 'pbd_show_cart_notices');

function pbd_show_cart_notices(): void
{
    $settings = pbd_get_settings();

    if ($settings['display_location'] === 'product') {
        return;
    }

    // In Exclusive or Best-discount mode: hide the notice when a coupon is active —
    // the notice would promise a discount that will not be applied.
    $conflict_mode = $settings['conflict_mode'] ?? 'stack';
    if ($conflict_mode !== 'stack' && WC()->cart && ! empty(WC()->cart->get_applied_coupons())) {
        return;
    }

    $template     = pbd_get_translated_template($settings['notice_template']);
    $type         = $settings['notice_type'];
    $notice_class = pbd_sanitize_html_class_list($settings['notice_class'] ?? '');
    $shown        = [];

    foreach ($settings['rules'] as $rule) {
        $trigger_qty = (int) $rule['trigger_qty'];
        $target_qty  = (int) $rule['target_qty'];
        $current_qty = pbd_count_qty_for_rule($rule);

        if ($current_qty !== $trigger_qty) {
            continue;
        }

        // Deduplicate when two rules share the same trigger, category, and SKU.
        $sku_key   = implode(',', pbd_get_rule_skus($rule));
        $dedup_key = $trigger_qty . '_' . ($sku_key ? 'sku:' . $sku_key : implode(',', (array) ($rule['category_ids'] ?? [])));
        if (isset($shown[$dedup_key])) {
            continue;
        }
        $shown[$dedup_key] = true;

        $missing = $target_qty - $current_qty;
        $text    = str_replace(
            ['{current}', '{missing}', '{target}', '{discount}'],
            [$current_qty, $missing, $target_qty, pbd_format_discount($rule)],
            $template
        );

        // Pass the custom CSS class to the template via $data (read server-side in notice.php).
        $data = $notice_class ? ['pbd_class' => $notice_class] : [];
        wc_add_notice(wp_kses_post($text), $type, $data);
    }
}

/**
 * Sanitises a space-separated list of CSS class names.
 */
function pbd_sanitize_html_class_list(string $classes): string
{
    $parts = preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', array_map('sanitize_html_class', $parts));
}

// ─────────────────────────────────────────────────────────────────────────────
// PRODUCT PAGE NOTICE
// ─────────────────────────────────────────────────────────────────────────────

add_action('woocommerce_before_add_to_cart_form', 'pbd_show_product_notices');

function pbd_show_product_notices(): void
{
    if (! is_product()) {
        return;
    }

    $settings = pbd_get_settings();

    if ($settings['display_location'] === 'cart') {
        return;
    }

    // In Exclusive or Best-discount mode: hide the notice when a coupon is active.
    $conflict_mode = $settings['conflict_mode'] ?? 'stack';
    if ($conflict_mode !== 'stack' && WC()->cart && ! empty(WC()->cart->get_applied_coupons())) {
        return;
    }

    $product = wc_get_product(get_the_ID());
    if (! $product) {
        return;
    }

    $product_term_ids = wc_get_product_term_ids($product->get_id(), 'product_cat');
    $product_sku      = $product->get_sku();

    $raw_tpl  = $settings['product_notice_template'];
    $fallback = $settings['notice_template'];
    $template = pbd_get_translated_product_template($raw_tpl, $fallback);

    $notice_class = pbd_sanitize_html_class_list($settings['product_notice_class'] ?? '');

    // Map notice type to WooCommerce CSS class.
    $type_class_map = [
        'notice'  => 'woocommerce-info',
        'success' => 'woocommerce-message',
        'error'   => 'woocommerce-error',
    ];
    $type_class = $type_class_map[$settings['notice_type']] ?? 'woocommerce-info';

    $shown = [];

    foreach ($settings['rules'] as $rule) {
        $trigger_qty = (int) $rule['trigger_qty'];
        $target_qty  = (int) $rule['target_qty'];
        $rule_skus   = pbd_get_rule_skus($rule);
        $cat_ids     = array_filter(array_map('intval', (array) ($rule['category_ids'] ?? [])));

        // Check match: SKU takes priority over category.
        if (! empty($rule_skus)) {
            if (! in_array($product_sku, $rule_skus, true)) {
                continue;
            }
        } elseif (! empty($cat_ids)) {
            $expanded = pbd_expand_category_ids($cat_ids);
            if (empty(array_intersect($product_term_ids, $expanded))) {
                continue;
            }
        }

        // Count current cart qty for this rule.
        $current_qty = pbd_count_qty_for_rule($rule);

        // Show only within the trigger_qty ≤ current_qty < target_qty window.
        if ($current_qty < $trigger_qty || $current_qty >= $target_qty) {
            continue;
        }

        // Dedup by target + SKU/category (show one promotion per threshold).
        $sku_key   = implode(',', $rule_skus);
        $cat_key   = implode(',', array_map('intval', $cat_ids));
        $dedup_key = $target_qty . '_' . ($sku_key ? 'sku:' . $sku_key : $cat_key);
        if (isset($shown[$dedup_key])) {
            continue;
        }
        $shown[$dedup_key] = true;

        $missing = $target_qty - $current_qty;
        $text    = str_replace(
            ['{current}', '{missing}', '{target}', '{discount}'],
            [$current_qty, $missing, $target_qty, pbd_format_discount($rule)],
            $template
        );

        $classes = trim($type_class . ($notice_class ? ' ' . $notice_class : ''));
        echo '<div class="' . esc_attr($classes) . '" role="alert">';
        echo wp_kses_post($text);
        echo '</div>';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE OVERRIDE – NOTICE WITH CUSTOM CSS CLASS
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Uses the plugin's notices/notice.php and notices/success.php templates
 * only when the theme does NOT already have its own override.
 * The plugin templates add support for the $pbd_class variable
 * passed via wc_add_notice( $msg, $type, ['pbd_class' => 'my-class'] ).
 */
add_filter('woocommerce_locate_template', 'pbd_locate_notice_template', 10, 3);

function pbd_locate_notice_template(string $template, string $template_name, string $template_path): string
{
    static $our_templates = ['notices/notice.php', 'notices/success.php'];

    if (! in_array($template_name, $our_templates, true)) {
        return $template;
    }

    // If the theme already has an override, do not interfere.
    $theme_template = locate_template([
        trailingslashit($template_path) . $template_name,
        $template_name,
    ]);
    if ($theme_template) {
        return $template;
    }

    $plugin_template = plugin_dir_path(__FILE__) . 'woocommerce/' . $template_name;
    if (file_exists($plugin_template)) {
        return $plugin_template;
    }

    return $template;
}

/**
 * Checks whether the theme has notice template overrides and whether they support $pbd_class.
 * Returns an array with status for each template.
 *
 * @return array{ template: string, has_override: bool, has_pbd_support: bool, path: string }[]
 */
function pbd_check_theme_notice_templates(): array
{
    $results = [];
    foreach (['notices/notice.php', 'notices/success.php'] as $tpl) {
        $theme_file = locate_template('woocommerce/' . $tpl);
        if (! $theme_file) {
            $results[$tpl] = ['has_override' => false, 'has_pbd_support' => true, 'path' => ''];
            continue;
        }
        $content       = file_get_contents($theme_file); // phpcs:ignore WordPress.WP.AlternativeFunctions
        $results[$tpl] = [
            'has_override'    => true,
            'has_pbd_support' => $content !== false && strpos($content, 'pbd_class') !== false,
            'path'            => $theme_file,
        ];
    }
    return $results;
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTOMATIC DISCOUNT
// ─────────────────────────────────────────────────────────────────────────────

add_action('woocommerce_cart_calculate_fees', 'pbd_apply_quantity_discounts');

function pbd_apply_quantity_discounts(WC_Cart $cart): void
{
    if (is_admin() && ! defined('DOING_AJAX')) {
        return;
    }

    $settings = pbd_get_settings();
    $subtotal = $cart->get_subtotal();

    // For each group (target_qty + category/SKU + scope) apply only the best discount,
    // preventing unintended stacking when multiple rules share the same threshold.
    $best = [];

    foreach ($settings['rules'] as $rule) {
        $target_qty  = (int) $rule['target_qty'];
        $current_qty = pbd_count_qty_for_rule($rule);

        if ($current_qty < $target_qty) {
            continue;
        }

        $scope     = $rule['discount_scope'] ?? 'cart';
        $base      = ($scope === 'category') ? pbd_get_category_subtotal($rule) : $subtotal;
        $cat_key   = implode(',', array_map('intval', (array) ($rule['category_ids'] ?? [])));
        $sku_key   = implode(',', pbd_get_rule_skus($rule));
        $group_key = $target_qty . '_' . ($sku_key ? 'sku:' . $sku_key : 'cat:' . $cat_key) . '_' . $scope;
        $amount    = pbd_calculate_discount_amount($rule, $base);

        if (! isset($best[$group_key]) || $amount > $best[$group_key]['amount']) {
            $best[$group_key] = [
                'amount' => $amount,
                'label'  => sprintf(
                    /* translators: %d = target quantity */
                    __('Discount for %d items', 'carttrigger-quantity-discounts'),
                    $target_qty
                ),
            ];
        }
    }

    if (! empty($best)) {
        $conflict_mode   = $settings['conflict_mode'] ?? 'stack';
        $applied_coupons = $cart->get_applied_coupons();

        if ($conflict_mode !== 'stack' && ! empty($applied_coupons)) {
            if ($conflict_mode === 'skip') {
                return;
            }

            if ($conflict_mode === 'best') {
                $our_total       = array_sum(array_column($best, 'amount'));
                $coupon_discount = (float) $cart->get_discount_total();
                if ($our_total <= $coupon_discount) {
                    return;
                }
            }
        }
    }

    foreach ($best as $entry) {
        if ($entry['amount'] > 0) {
            $cart->add_fee($entry['label'], -$entry['amount'], true);
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN – MENU
// ─────────────────────────────────────────────────────────────────────────────

add_action('admin_menu', 'pbd_register_admin_menu');

function pbd_register_admin_menu(): void
{
    add_submenu_page(
        'woocommerce',
        __('Quantity Discounts', 'carttrigger-quantity-discounts'),
        __('Quantity Discounts', 'carttrigger-quantity-discounts'),
        'manage_woocommerce',
        'pbd-settings',
        'pbd_render_settings_page'
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN – SETTINGS LINK IN PLUGIN LIST
// ─────────────────────────────────────────────────────────────────────────────

add_filter('plugin_action_links_poletto-bottle-discount/poletto-bottle-discount.php', 'pbd_add_settings_link');

function pbd_add_settings_link(array $links): array
{
    $url  = admin_url('admin.php?page=pbd-settings');
    $link = '<a href="' . esc_url($url) . '">' . __('Settings', 'carttrigger-quantity-discounts') . '</a>';
    array_unshift($links, $link);
    return $links;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN – SAVE
// ─────────────────────────────────────────────────────────────────────────────

add_action('admin_init', 'pbd_handle_save');

function pbd_handle_save(): void
{
    if (
        ! isset($_POST['pbd_nonce']) ||
        ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pbd_nonce'])), 'pbd_save') ||
        ! current_user_can('manage_woocommerce')
    ) {
        return;
    }

    $settings = pbd_get_settings();

    // Notice text and type.
    $settings['notice_template'] = wp_kses_post(
        wp_unslash($_POST['pbd_notice_template'] ?? $settings['notice_template'])
    );
    $notice_type = sanitize_text_field(wp_unslash($_POST['pbd_notice_type'] ?? 'notice'));
    $settings['notice_type'] = in_array($notice_type, ['notice', 'success', 'error'], true)
        ? $notice_type
        : 'notice';

    $settings['notice_class'] = pbd_sanitize_html_class_list(
        sanitize_text_field(wp_unslash($_POST['pbd_notice_class'] ?? ''))
    );

    // Notice position.
    $location = sanitize_text_field(wp_unslash($_POST['pbd_display_location'] ?? 'cart'));
    $settings['display_location'] = in_array($location, ['cart', 'product', 'both'], true)
        ? $location
        : 'cart';

    // Coupon conflict behaviour.
    $conflict_mode = sanitize_text_field(wp_unslash($_POST['pbd_conflict_mode'] ?? 'stack'));
    $settings['conflict_mode'] = in_array($conflict_mode, ['stack', 'skip', 'best'], true)
        ? $conflict_mode
        : 'stack';

    // Product page template and class.
    $settings['product_notice_template'] = wp_kses_post(
        wp_unslash($_POST['pbd_product_notice_template'] ?? '')
    );
    $settings['product_notice_class'] = pbd_sanitize_html_class_list(
        sanitize_text_field(wp_unslash($_POST['pbd_product_notice_class'] ?? ''))
    );

    // Rules.
    $raw_rules = isset($_POST['pbd_rules']) && is_array($_POST['pbd_rules'])
        ? wp_unslash($_POST['pbd_rules']) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitized individually below
        : [];

    $new_rules = [];
    foreach ($raw_rules as $raw) {
        $trigger = (int) ($raw['trigger_qty'] ?? 0);
        $target  = (int) ($raw['target_qty']  ?? 0);

        // Discard invalid rows: trigger and target must be > 0 and trigger < target.
        if ($trigger <= 0 || $target <= 0 || $trigger >= $target) {
            continue;
        }

        $discount_type = sanitize_text_field($raw['discount_type'] ?? 'percent');
        $discount_type = in_array($discount_type, ['percent', 'fixed'], true)
            ? $discount_type
            : 'percent';

        $discount_scope = sanitize_text_field($raw['discount_scope'] ?? 'cart');
        $discount_scope = in_array($discount_scope, ['cart', 'category'], true)
            ? $discount_scope
            : 'cart';

        $discount_value = (float) str_replace(
            ',',
            '.',
            sanitize_text_field($raw['discount_value'] ?? '0')
        );

        // category_ids: array of ints, filter out zeros ("all products" option).
        $raw_cats = isset($raw['category_ids']) && is_array($raw['category_ids'])
            ? $raw['category_ids']
            : [];
        $cat_ids = array_values(array_filter(array_map('intval', $raw_cats)));

        // skus: CSV text, normalised to an array of strings.
        $raw_skus = sanitize_text_field($raw['skus'] ?? '');
        $skus     = array_values(array_filter(array_map('trim', explode(',', $raw_skus))));

        $new_rules[] = [
            'trigger_qty'    => $trigger,
            'target_qty'     => $target,
            'discount_type'  => $discount_type,
            'discount_value' => max(0.0, $discount_value),
            'discount_scope' => $discount_scope,
            'category_ids'   => $cat_ids,
            'skus'           => $skus,
        ];
    }

    $settings['rules'] = $new_rules;
    update_option(PBD_OPTION_KEY, $settings);

    // Re-register the string in translation plugins after saving.
    pbd_register_translatable_strings();

    add_settings_error('pbd_settings', 'pbd_saved', __('Settings saved.', 'carttrigger-quantity-discounts'), 'updated');
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN – SETTINGS PAGE
// ─────────────────────────────────────────────────────────────────────────────

function pbd_render_settings_page(): void
{
    if (! current_user_can('manage_woocommerce')) {
        return;
    }

    settings_errors('pbd_settings');

    // Warn if the theme overrides notice templates without $pbd_class support.
    $tpl_status = pbd_check_theme_notice_templates();
    $missing     = array_filter($tpl_status, fn($t) => $t['has_override'] && ! $t['has_pbd_support']);
    if (! empty($missing)) : ?>
        <div class="notice notice-warning" style="margin:15px 0;padding:12px 14px;">
            <p style="margin:0 0 6px;">
                <strong>&#9888; <?php esc_html_e('Warning: custom notice templates detected.', 'carttrigger-quantity-discounts'); ?></strong>
            </p>
            <p style="margin:0 0 8px;">
                <?php
                echo wp_kses_post( sprintf(
                    /* translators: %s = list of file paths */
                    __('Your theme already overrides: %s. The plugin cannot automatically add support for the custom CSS class in these files.', 'carttrigger-quantity-discounts'),
                    '<code>' . implode('</code>, <code>', array_map(fn($t) => esc_html(str_replace(get_theme_root() . '/', '', $t['path'])), $missing)) . '</code>'
                ) );
                ?>
            </p>
            <p style="margin:0 0 6px;">
                <?php esc_html_e('To enable the custom CSS class in notices, add these two lines inside the foreach loop of the template:', 'carttrigger-quantity-discounts'); ?>
            </p>
            <pre style="background:#f6f7f7;padding:10px 12px;margin:0;font-size:12px;border-left:3px solid #dba617;overflow-x:auto;"><?php echo esc_html(
                '$pbd_class = ! empty( $notice[\'data\'][\'pbd_class\'] ) ? \' \' . esc_attr( $notice[\'data\'][\'pbd_class\'] ) : \'\';
// then add <?php echo $pbd_class; ?> to the class="" attribute of the container'
            ); ?></pre>
        </div>
    <?php endif;

    $s = pbd_get_settings();

    $all_cats = get_terms([
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
    ]);
    if (is_wp_error($all_cats)) {
        $all_cats = [];
    }

    // Category options HTML with no selection (used in the JS template for new rows).
    $cat_options_base = pbd_build_cat_options($all_cats, []);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Quantity Discounts', 'carttrigger-quantity-discounts'); ?></h1>
        <p class="description">
            <?php esc_html_e('Each rule defines when to show the notice (trigger) and which discount to apply when the customer reaches the threshold (target). Selecting a parent category automatically includes its subcategories.', 'carttrigger-quantity-discounts'); ?>
        </p>

        <form method="post">
            <?php wp_nonce_field('pbd_save', 'pbd_nonce'); ?>

            <h2 style="margin-top:1.5em;"><?php esc_html_e('Discount rules', 'carttrigger-quantity-discounts'); ?></h2>

            <div class="notice notice-info inline" style="margin:0 0 1em;padding:10px 14px;">
                <p style="margin:0;">
                    <strong><?php esc_html_e('How the discount scope works:', 'carttrigger-quantity-discounts'); ?></strong>
                    <?php esc_html_e('"Entire cart" applies the percentage to the full cart subtotal. "Category only" applies it only to the subtotal of matching items — useful for targeted discounts. Fixed amounts are not affected by scope. When multiple rules share the same Target and Scope, only the highest discount is applied automatically.', 'carttrigger-quantity-discounts'); ?>
                    <br><em><?php esc_html_e('Tip: for rules with the same Target and Scope, use the same discount value so the {discount} variable shown in the notice always matches what is actually applied.', 'carttrigger-quantity-discounts'); ?></em>
                </p>
            </div>

            <table class="wp-list-table widefat fixed" id="pbd-rules-table" style="border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="width:105px;padding:8px 10px;"><?php esc_html_e('Trigger (qty)', 'carttrigger-quantity-discounts'); ?></th>
                        <th style="width:105px;padding:8px 10px;"><?php esc_html_e('Target (qty)', 'carttrigger-quantity-discounts'); ?></th>
                        <th style="width:135px;padding:8px 10px;"><?php esc_html_e('Discount type', 'carttrigger-quantity-discounts'); ?></th>
                        <th style="width:90px;padding:8px 10px;"><?php esc_html_e('Value', 'carttrigger-quantity-discounts'); ?></th>
                        <th style="padding:8px 10px;"><?php esc_html_e('Category / SKU', 'carttrigger-quantity-discounts'); ?></th>
                        <th style="width:50px;padding:8px 10px;"></th>
                    </tr>
                </thead>
                <tbody id="pbd-rules-body">
                    <?php foreach ($s['rules'] as $i => $rule) : ?>
                        <tr class="pbd-rule-row" style="vertical-align:top;">
                            <td style="padding:8px 10px;">
                                <input type="number"
                                    name="pbd_rules[<?php echo (int) $i; ?>][trigger_qty]"
                                    value="<?php echo esc_attr($rule['trigger_qty']); ?>"
                                    min="1" class="small-text" required />
                                <p class="description" style="margin:3px 0 0;font-size:11px;"><?php esc_html_e('show notice', 'carttrigger-quantity-discounts'); ?></p>
                            </td>
                            <td style="padding:8px 10px;">
                                <input type="number"
                                    name="pbd_rules[<?php echo (int) $i; ?>][target_qty]"
                                    value="<?php echo esc_attr($rule['target_qty']); ?>"
                                    min="1" class="small-text" required />
                                <p class="description" style="margin:3px 0 0;font-size:11px;"><?php esc_html_e('apply discount', 'carttrigger-quantity-discounts'); ?></p>
                            </td>
                            <td style="padding:8px 10px;">
                                <select name="pbd_rules[<?php echo (int) $i; ?>][discount_type]" style="width:100%;">
                                    <option value="percent" <?php selected($rule['discount_type'], 'percent'); ?>>% <?php esc_html_e('Percentage', 'carttrigger-quantity-discounts'); ?></option>
                                    <option value="fixed" <?php selected($rule['discount_type'], 'fixed'); ?>>€ <?php esc_html_e('Fixed', 'carttrigger-quantity-discounts'); ?></option>
                                </select>
                                <select name="pbd_rules[<?php echo (int) $i; ?>][discount_scope]" style="width:100%;margin-top:5px;">
                                    <option value="cart" <?php selected($rule['discount_scope'] ?? 'cart', 'cart'); ?>><?php esc_html_e('Entire cart', 'carttrigger-quantity-discounts'); ?></option>
                                    <option value="category" <?php selected($rule['discount_scope'] ?? 'cart', 'category'); ?>><?php esc_html_e('Category only', 'carttrigger-quantity-discounts'); ?></option>
                                </select>
                                <p class="description" style="margin:3px 0 0;font-size:11px;"><?php esc_html_e('discount scope', 'carttrigger-quantity-discounts'); ?></p>
                            </td>
                            <td style="padding:8px 10px;">
                                <input type="text"
                                    name="pbd_rules[<?php echo (int) $i; ?>][discount_value]"
                                    value="<?php echo esc_attr($rule['discount_value']); ?>"
                                    class="small-text" required />
                            </td>
                            <td style="padding:8px 10px;">
                                <select name="pbd_rules[<?php echo (int) $i; ?>][category_ids][]"
                                    multiple
                                    style="width:100%;min-height:90px;">
                                    <option value="0" <?php echo empty($rule['category_ids']) ? 'selected' : ''; ?>>
                                        — <?php esc_html_e('All products', 'carttrigger-quantity-discounts'); ?> —
                                    </option>
                                    <?php echo pbd_build_cat_options($all_cats, $rule['category_ids']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output is pre-escaped with esc_html() ?>
                                </select>
                                <p class="description" style="margin:3px 0 0;font-size:11px;">
                                    <?php esc_html_e('Ctrl+click for multiple categories. Subcategories are included automatically.', 'carttrigger-quantity-discounts'); ?>
                                </p>
                                <input type="text"
                                    name="pbd_rules[<?php echo (int) $i; ?>][skus]"
                                    value="<?php echo esc_attr(implode(', ', (array) ($rule['skus'] ?? []))); ?>"
                                    placeholder="<?php esc_attr_e('SKU (e.g. SKU1, SKU2)', 'carttrigger-quantity-discounts'); ?>"
                                    class="widefat"
                                    style="margin-top:8px;" />
                                <p class="description" style="margin:3px 0 0;font-size:11px;">
                                    <?php esc_html_e('Comma-separated SKUs. Takes priority over category when set.', 'carttrigger-quantity-discounts'); ?>
                                </p>
                            </td>
                            <td style="padding:8px 10px;text-align:center;">
                                <button type="button" class="button pbd-remove-row"
                                    title="<?php esc_attr_e('Remove', 'carttrigger-quantity-discounts'); ?>"
                                    style="color:#b32d2e;">&#10005;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p style="margin-top:10px;">
                <button type="button" id="pbd-add-rule" class="button button-secondary">
                    &#43; <?php esc_html_e('Add rule', 'carttrigger-quantity-discounts'); ?>
                </button>
            </p>

            <div id="pbd-conflict-warning" class="notice notice-warning inline" style="display:none;margin:10px 0 0;padding:10px 14px;">
                <p style="margin:0;">
                    <strong>&#9888; <?php esc_html_e('Warning: inconsistent discounts for the same Target.', 'carttrigger-quantity-discounts'); ?></strong>
                    <?php esc_html_e('Two or more rules with the same Target value have different discount amounts. The highest discount will be applied automatically, but the {discount} variable in the notice will reflect the active trigger\'s value — which may not match the actual discount applied. Recommendation: use the same discount type and value for all rules sharing the same Target.', 'carttrigger-quantity-discounts'); ?>
                </p>
            </div>

            <hr style="margin:2em 0;">

            <h2><?php esc_html_e('Behaviour with other discounts', 'carttrigger-quantity-discounts'); ?></h2>

            <div class="notice notice-info inline" style="margin:0 0 1em;padding:10px 14px;">
                <p style="margin:0;">
                    <strong><?php esc_html_e('Discount and notices go together:', 'carttrigger-quantity-discounts'); ?></strong>
                    <?php esc_html_e('The selected mode controls both the automatic discount and the visibility of promotional notices in the cart and on the product page. In Exclusive and Best modes, notices are hidden when a coupon is active — so customers are never shown a discount promise that will not be applied.', 'carttrigger-quantity-discounts'); ?>
                </p>
            </div>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Conflict mode', 'carttrigger-quantity-discounts'); ?></th>
                    <td>
                        <?php
                        $conflict_modes = [
                            'stack' => [
                                'label' => __('Stack', 'carttrigger-quantity-discounts'),
                                'desc'  => __('The quantity discount is always applied and notices are always shown, regardless of any active coupons (default behaviour).', 'carttrigger-quantity-discounts'),
                            ],
                            'skip' => [
                                'label' => __('Exclusive', 'carttrigger-quantity-discounts'),
                                'desc'  => __('If at least one coupon is active, the quantity discount is skipped and notices are hidden to avoid misleading the customer.', 'carttrigger-quantity-discounts'),
                            ],
                            'best' => [
                                'label' => __('Best discount wins', 'carttrigger-quantity-discounts'),
                                'desc'  => __('The quantity discount is applied only if its amount exceeds the total coupon discount; otherwise coupons take precedence and notices are hidden.', 'carttrigger-quantity-discounts'),
                            ],
                        ];
                        foreach ($conflict_modes as $val => $info) : ?>
                            <label style="display:block;margin-bottom:10px;">
                                <input type="radio" name="pbd_conflict_mode"
                                    value="<?php echo esc_attr($val); ?>"
                                    <?php checked($s['conflict_mode'] ?? 'stack', $val); ?> />
                                <strong><?php echo esc_html($info['label']); ?></strong>
                                <span style="color:#666;margin-left:6px;font-size:13px;"><?php echo esc_html($info['desc']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <hr style="margin:2em 0;">

            <h2><?php esc_html_e('Notice position', 'carttrigger-quantity-discounts'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Where to show', 'carttrigger-quantity-discounts'); ?></th>
                    <td>
                        <?php
                        $locations = [
                            'cart'    => __('Cart / checkout only', 'carttrigger-quantity-discounts'),
                            'product' => __('Product page only', 'carttrigger-quantity-discounts'),
                            'both'    => __('Cart and product page', 'carttrigger-quantity-discounts'),
                        ];
                        foreach ($locations as $val => $label) :
                        ?>
                            <label style="margin-right:20px;">
                                <input type="radio" name="pbd_display_location"
                                    value="<?php echo esc_attr($val); ?>"
                                    <?php checked($s['display_location'] ?? 'cart', $val); ?> />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>

            <h2 id="pbd-h2-cart"><?php esc_html_e('Cart / checkout notice', 'carttrigger-quantity-discounts'); ?></h2>
            <table class="form-table" role="presentation" id="pbd-cart-fields">
                <tr>
                    <th scope="row">
                        <label for="pbd_notice_template"><?php esc_html_e('Notice text', 'carttrigger-quantity-discounts'); ?></label>
                    </th>
                    <td>
                        <textarea id="pbd_notice_template" name="pbd_notice_template"
                            rows="3" class="large-text"><?php echo esc_textarea($s['notice_template']); ?></textarea>
                        <?php if (function_exists('icl_register_string') || function_exists('pll_register_string')) : ?>
                            <p class="description" style="color:#2271b1;">
                                &#128279; <?php esc_html_e('Translation plugin detected: this text is registered as a translatable string. Translate it from your plugin\'s interface (WPML → String Translation / Polylang → String Translations).', 'carttrigger-quantity-discounts'); ?>
                            </p>
                        <?php endif; ?>
                        <p class="description">
                            <?php esc_html_e('Available variables:', 'carttrigger-quantity-discounts'); ?>
                            <code>{current}</code> <?php esc_html_e('= current qty', 'carttrigger-quantity-discounts'); ?> &nbsp;
                            <code>{missing}</code> <?php esc_html_e('= items still needed', 'carttrigger-quantity-discounts'); ?> &nbsp;
                            <code>{target}</code> <?php esc_html_e('= target qty', 'carttrigger-quantity-discounts'); ?> &nbsp;
                            <code>{discount}</code> <?php esc_html_e('= formatted discount (e.g. 10% or €5.00)', 'carttrigger-quantity-discounts'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Notice type', 'carttrigger-quantity-discounts'); ?></th>
                    <td>
                        <?php
                        $notice_types = [
                            'notice'  => __('Info (blue)',     'carttrigger-quantity-discounts'),
                            'success' => __('Success (green)', 'carttrigger-quantity-discounts'),
                            'error'   => __('Warning (red)',   'carttrigger-quantity-discounts'),
                        ];
                        foreach ($notice_types as $val => $label) :
                        ?>
                            <label style="margin-right:20px;">
                                <input type="radio" name="pbd_notice_type"
                                    value="<?php echo esc_attr($val); ?>"
                                    <?php checked($s['notice_type'], $val); ?> />
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">
                            <?php esc_html_e('Determines WooCommerce semantic behaviour (placement, aria attributes). Use the custom CSS class below to control the visual style.', 'carttrigger-quantity-discounts'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pbd_notice_class"><?php esc_html_e('Custom CSS class', 'carttrigger-quantity-discounts'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="pbd_notice_class" name="pbd_notice_class"
                            value="<?php echo esc_attr($s['notice_class']); ?>"
                            class="regular-text" placeholder="e.g. pbd-notice my-store-notice" />
                        <p class="description">
                            <?php esc_html_e('One or more space-separated classes. They are added to the notice container in the frontend so you can style it freely via CSS.', 'carttrigger-quantity-discounts'); ?>
                        </p>
                        <?php if (! empty($s['notice_class'])) : ?>
                            <p class="description" style="margin-top:6px;">
                                <?php esc_html_e('Example CSS in your theme:', 'carttrigger-quantity-discounts'); ?>
                                <code>.<?php echo esc_html(explode(' ', trim($s['notice_class']))[0]); ?> { background: #f5e6d3; border-color: #c87941; color: #7a4a1e; }</code>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2 id="pbd-h2-product"><?php esc_html_e('Product page notice', 'carttrigger-quantity-discounts'); ?></h2>
            <table class="form-table" role="presentation" id="pbd-product-fields">
                <tr>
                    <th scope="row">
                        <label for="pbd_product_notice_template"><?php esc_html_e('Product page notice text', 'carttrigger-quantity-discounts'); ?></label>
                    </th>
                    <td>
                        <textarea id="pbd_product_notice_template" name="pbd_product_notice_template"
                            rows="3" class="large-text"
                            placeholder="<?php echo esc_attr($s['notice_template']); ?>"><?php echo esc_textarea($s['product_notice_template']); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Leave empty to reuse the cart notice text. Available variables: {current} {missing} {target} {discount}.', 'carttrigger-quantity-discounts'); ?>
                        </p>
                        <p class="description">
                            <?php esc_html_e('Note: {current} and {missing} are calculated from the current cart (0 if the cart is empty).', 'carttrigger-quantity-discounts'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="pbd_product_notice_class"><?php esc_html_e('Product page CSS class', 'carttrigger-quantity-discounts'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="pbd_product_notice_class" name="pbd_product_notice_class"
                            value="<?php echo esc_attr($s['product_notice_class']); ?>"
                            class="regular-text" placeholder="e.g. pbd-promo-product" />
                        <p class="description">
                            <?php esc_html_e('One or more space-separated classes, added to the notice container on the product page.', 'carttrigger-quantity-discounts'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(__('Save settings', 'carttrigger-quantity-discounts')); ?>
        </form>
    </div>

    <!-- Row template for new rules (populated via JS) -->
    <template id="pbd-rule-tpl">
        <tr class="pbd-rule-row" style="vertical-align:top;">
            <td style="padding:8px 10px;">
                <input type="number" name="pbd_rules[__IDX__][trigger_qty]" value="4" min="1" class="small-text" required />
                <p class="description" style="margin:3px 0 0;font-size:11px;"><?php esc_html_e('show notice', 'carttrigger-quantity-discounts'); ?></p>
            </td>
            <td style="padding:8px 10px;">
                <input type="number" name="pbd_rules[__IDX__][target_qty]" value="6" min="1" class="small-text" required />
                <p class="description" style="margin:3px 0 0;font-size:11px;"><?php esc_html_e('apply discount', 'carttrigger-quantity-discounts'); ?></p>
            </td>
            <td style="padding:8px 10px;">
                <select name="pbd_rules[__IDX__][discount_type]" style="width:100%;">
                    <option value="percent">% <?php esc_html_e('Percentage', 'carttrigger-quantity-discounts'); ?></option>
                    <option value="fixed">€ <?php esc_html_e('Fixed', 'carttrigger-quantity-discounts'); ?></option>
                </select>
                <select name="pbd_rules[__IDX__][discount_scope]" style="width:100%;margin-top:5px;">
                    <option value="cart"><?php esc_html_e('Entire cart', 'carttrigger-quantity-discounts'); ?></option>
                    <option value="category"><?php esc_html_e('Category only', 'carttrigger-quantity-discounts'); ?></option>
                </select>
                <p class="description" style="margin:3px 0 0;font-size:11px;"><?php esc_html_e('discount scope', 'carttrigger-quantity-discounts'); ?></p>
            </td>
            <td style="padding:8px 10px;">
                <input type="text" name="pbd_rules[__IDX__][discount_value]" value="10" class="small-text" required />
            </td>
            <td style="padding:8px 10px;">
                <select name="pbd_rules[__IDX__][category_ids][]" multiple style="width:100%;min-height:90px;">
                    <option value="0" selected>— <?php esc_html_e('All products', 'carttrigger-quantity-discounts'); ?> —</option>
                    <?php echo $cat_options_base; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output is pre-escaped with esc_html() ?>
                </select>
                <p class="description" style="margin:3px 0 0;font-size:11px;">
                    <?php esc_html_e('Ctrl+click for multiple categories. Subcategories are included automatically.', 'carttrigger-quantity-discounts'); ?>
                </p>
                <input type="text"
                    name="pbd_rules[__IDX__][skus]"
                    value=""
                    placeholder="<?php esc_attr_e('SKU (e.g. SKU1, SKU2)', 'carttrigger-quantity-discounts'); ?>"
                    class="widefat"
                    style="margin-top:8px;" />
                <p class="description" style="margin:3px 0 0;font-size:11px;">
                    <?php esc_html_e('Comma-separated SKUs. Takes priority over category when set.', 'carttrigger-quantity-discounts'); ?>
                </p>
            </td>
            <td style="padding:8px 10px;text-align:center;">
                <button type="button" class="button pbd-remove-row"
                    title="<?php esc_attr_e('Remove', 'carttrigger-quantity-discounts'); ?>"
                    style="color:#b32d2e;">&#10005;</button>
            </td>
        </tr>
    </template>

    <script>
        (function() {
            var idx = <?php echo count($s['rules']); ?>;
            var tbody = document.getElementById('pbd-rules-body');
            var tpl = document.getElementById('pbd-rule-tpl');
            var conflictWarning = document.getElementById('pbd-conflict-warning');

            function pbd_check_conflicts() {
                var rows = tbody.querySelectorAll('.pbd-rule-row');
                var groups = {};

                rows.forEach(function(row) {
                    var targetEl = row.querySelector('input[name$="[target_qty]"]');
                    var typeEl = row.querySelector('select[name$="[discount_type]"]');
                    var valueEl = row.querySelector('input[name$="[discount_value]"]');
                    var scopeEl = row.querySelector('select[name$="[discount_scope]"]');
                    var skusEl = row.querySelector('input[name$="[skus]"]');
                    if (!targetEl || !typeEl || !valueEl) {
                        return;
                    }

                    var targetVal = targetEl.value.trim();
                    if (!targetVal) {
                        return;
                    }

                    var scopeVal = scopeEl ? scopeEl.value : 'cart';
                    var skusVal = skusEl ? skusEl.value.trim() : '';
                    var key = targetVal + '_' + scopeVal + '_' + skusVal;

                    if (!groups[key]) {
                        groups[key] = [];
                    }
                    var normalized = typeEl.value + ':' + parseFloat(valueEl.value.replace(',', '.') || '0');
                    groups[key].push(normalized);
                });

                var hasConflict = false;
                Object.keys(groups).forEach(function(key) {
                    var unique = groups[key].filter(function(v, i, a) {
                        return a.indexOf(v) === i;
                    });
                    if (unique.length > 1) {
                        hasConflict = true;
                    }
                });

                conflictWarning.style.display = hasConflict ? '' : 'none';
            }

            document.getElementById('pbd-add-rule').addEventListener('click', function() {
                var html = tpl.innerHTML.replace(/__IDX__/g, idx++);
                var tmp = document.createElement('tbody');
                tmp.innerHTML = html;
                tbody.appendChild(tmp.firstElementChild);
                pbd_check_conflicts();
            });

            tbody.addEventListener('click', function(e) {
                if (e.target.classList.contains('pbd-remove-row')) {
                    if (tbody.querySelectorAll('.pbd-rule-row').length > 1) {
                        e.target.closest('tr').remove();
                        pbd_check_conflicts();
                    } else {
                        alert('<?php echo esc_js(__('You must keep at least one rule.', 'carttrigger-quantity-discounts')); ?>');
                    }
                }
            });

            tbody.addEventListener('input', pbd_check_conflicts);
            tbody.addEventListener('change', pbd_check_conflicts);

            // Initial check on page load.
            pbd_check_conflicts();

            // ── Show/hide cart and product sections based on display location ──
            (function() {
                var radios = document.querySelectorAll('input[name="pbd_display_location"]');
                var cartH2 = document.getElementById('pbd-h2-cart');
                var cartFields = document.getElementById('pbd-cart-fields');
                var prodH2 = document.getElementById('pbd-h2-product');
                var prodFields = document.getElementById('pbd-product-fields');

                function syncVisibility() {
                    var val = (document.querySelector('input[name="pbd_display_location"]:checked') || {}).value || 'cart';
                    var showCart = val === 'cart' || val === 'both';
                    var showProd = val === 'product' || val === 'both';

                    [cartH2, cartFields].forEach(function(el) {
                        if (el) el.style.display = showCart ? '' : 'none';
                    });
                    [prodH2, prodFields].forEach(function(el) {
                        if (el) el.style.display = showProd ? '' : 'none';
                    });
                }

                radios.forEach(function(r) {
                    r.addEventListener('change', syncVisibility);
                });
                syncVisibility();
            })();
        })();
    </script>
<?php
}

// ─────────────────────────────────────────────────────────────────────────────
// UTILITY – CATEGORY TREE
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Generates <option> elements for the product category tree.
 * Visually indents subcategories and shows the product count.
 *
 * @param WP_Term[] $all_cats
 * @param int[]     $selected_ids
 * @return string
 */
function pbd_build_cat_options($all_cats, array $selected_ids = []): string
{
    if (empty($all_cats)) {
        return '';
    }

    $children_map = [];
    $roots        = [];

    foreach ($all_cats as $cat) {
        if ((int) $cat->parent === 0) {
            $roots[] = $cat;
        } else {
            $children_map[$cat->parent][] = $cat;
        }
    }

    $html = '';

    $render = function (array $cats, int $depth) use (&$render, &$html, $children_map, $selected_ids) {
        foreach ($cats as $cat) {
            $prefix  = str_repeat("\xc2\xa0\xc2\xa0\xc2\xa0", $depth);
            $prefix .= $depth > 0 ? "\xe2\x86\xb3 " : '';
            $label   = $prefix . $cat->name . ' (' . $cat->count . ')';
            $sel     = in_array((int) $cat->term_id, $selected_ids, true) ? ' selected' : '';

            $html .= sprintf(
                '<option value="%d"%s>%s</option>',
                $cat->term_id,
                $sel,
                esc_html($label)
            );

            if (! empty($children_map[$cat->term_id])) {
                $render($children_map[$cat->term_id], $depth + 1);
            }
        }
    };

    $render($roots, 0);

    return $html;
}
