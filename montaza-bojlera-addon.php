<?php
declare(strict_types=1);

/**
 * Plugin Name:       Montaža Bojlera
 * Description:       Dodaje montažu i dostavu bojlera kao dodatak uz proizvod. Checkbox na stranici
 *                    proizvoda dodaje uslugu u korpu. Administrator može da podesi cenu i vreme dostave.
 * Version:           2.0.0
 * Author:            Bojan Pavlovć
 * Text Domain:       wm-montaza-bojlera
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WM_MONTAZA_VERSION',    '2.0.0');
define('WM_MONTAZA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WM_MONTAZA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        // Classic shortcode-based checkout only; block-based checkout not supported yet.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            false
        );
    }
});

// Lifecycle hooks must be registered at file scope, before plugins_loaded.
register_activation_hook(__FILE__, ['WM_Montaza_Bojlera', 'activate']);
register_uninstall_hook(__FILE__, ['WM_Montaza_Bojlera', 'uninstall']);

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="error"><p>'
                . esc_html__('Plugin "Montaža Bojlera" zahteva WooCommerce. Molimo aktivirajte WooCommerce.', 'wm-montaza-bojlera')
                . '</p></div>';
        });
        return;
    }

    WM_Montaza_Bojlera::get_instance();
});

/**
 * Main plugin class — singleton.
 *
 * Responsibilities are intentionally kept in one file because the plugin is
 * small. Split into separate classes only when a feature area grows enough
 * to justify its own file.
 */
final class WM_Montaza_Bojlera {

    private static ?WM_Montaza_Bojlera $instance = null;

    /**
     * Returns the single instance, creating it on first call.
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_textdomain();
        $this->register_hooks();
    }

    /** Prevent cloning the singleton. */
    private function __clone() {}

    /** Prevent unserialization of the singleton. */
    public function __wakeup(): void {
        throw new \RuntimeException('Cannot unserialize singleton.');
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Runs on plugin activation.
     * add_option() is a no-op when the option already exists, so no existence
     * check is needed.
     */
    public static function activate(): void {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('Ovaj plugin zahteva WooCommerce. Molimo aktivirajte WooCommerce prvo.', 'wm-montaza-bojlera'),
                '',
                ['back_link' => true]
            );
        }

        add_option('wm_montaza_price', 7800);
        add_option('wm_montaza_delivery_min', 1);
        add_option('wm_montaza_delivery_max', 5);
    }

    /**
     * Runs on plugin uninstall — removes all stored options.
     */
    public static function uninstall(): void {
        delete_option('wm_montaza_price');
        delete_option('wm_montaza_delivery_min');
        delete_option('wm_montaza_delivery_max');
    }

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    private function load_textdomain(): void {
        load_plugin_textdomain(
            'wm-montaza-bojlera',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    private function register_hooks(): void {
        // Admin
        add_action('admin_menu',       [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Product page — price display + mandatory checkbox
        add_filter('woocommerce_get_price_html',       [$this, 'filter_price_html'], 10, 2);
        add_filter('woocommerce_available_variation',  [$this, 'modify_variation_data'], 10, 3);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_product_checkbox']);

        // Cart
        add_filter('woocommerce_add_cart_item_data',  [$this, 'save_cart_item_data'], 10, 2);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_montaza_fee']);
        add_filter('woocommerce_get_item_data',       [$this, 'filter_item_data'], 10, 2);
        add_filter('woocommerce_cart_subtotal',       [$this, 'filter_mini_cart_subtotal'], 10, 3);

        // Checkout
        add_action('woocommerce_review_order_before_cart_contents', [$this, 'render_checkout_checkboxes']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        // Order persistence
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Shortcode
        add_shortcode('montaza_bojlera', [$this, 'shortcode']);
    }

    // -------------------------------------------------------------------------
    // Asset enqueueing
    // -------------------------------------------------------------------------

    /**
     * Enqueue CSS on all frontend pages where WooCommerce content may appear.
     * The file is lightweight so there is no need to restrict it further.
     */
    public function enqueue_frontend_assets(): void {
        wp_enqueue_style(
            'wm-montaza-frontend',
            WM_MONTAZA_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            WM_MONTAZA_VERSION
        );
    }

    /**
     * Enqueue admin assets — only on this plugin's settings page.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_admin_assets(string $hook_suffix): void {
        if ('toplevel_page_wm-montaza-settings' !== $hook_suffix) {
            return;
        }
        // Room for admin-specific CSS/JS in the future.
    }

    // -------------------------------------------------------------------------
    // Admin settings page
    // -------------------------------------------------------------------------

    public function register_admin_menu(): void {
        add_menu_page(
            __('Montaža Bojlera', 'wm-montaza-bojlera'),
            __('Montaža Bojlera', 'wm-montaza-bojlera'),
            'manage_options',
            'wm-montaza-settings',
            [$this, 'render_settings_page'],
            'dashicons-admin-generic',
            56
        );
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $notice = '';

        if (isset($_POST['wm_montaza_save'])) {
            if (
                !isset($_POST['wm_montaza_settings_nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['wm_montaza_settings_nonce'])),
                    'wm_montaza_save_settings'
                )
            ) {
                $notice = '<div class="notice notice-error"><p>'
                    . esc_html__('Provera bezbednosti nije uspela. Pokušajte ponovo.', 'wm-montaza-bojlera')
                    . '</p></div>';
            } else {
                // Sanitize and constrain values server-side regardless of what the browser sends.
                $price        = max(0.0, (float) wp_unslash($_POST['wm_montaza_price'] ?? 0));
                $delivery_min = max(1, (int) wp_unslash($_POST['wm_montaza_delivery_min'] ?? 1));
                $delivery_max = (int) wp_unslash($_POST['wm_montaza_delivery_max'] ?? 1);

                $was_corrected = $delivery_max < $delivery_min;
                $delivery_max  = max($delivery_min, $delivery_max);

                update_option('wm_montaza_price', $price);
                update_option('wm_montaza_delivery_min', $delivery_min);
                update_option('wm_montaza_delivery_max', $delivery_max);

                $notice = '<div class="notice notice-success is-dismissible"><p>'
                    . esc_html__('Podešavanja su sačuvana.', 'wm-montaza-bojlera');

                if ($was_corrected) {
                    $notice .= ' <strong>'
                        . esc_html__('Napomena: maksimalno vreme dostave je podešeno na vrednost minimalnog jer je uneta vrednost bila manja.', 'wm-montaza-bojlera')
                        . '</strong>';
                }

                $notice .= '</p></div>';
            }
        }

        $price        = get_option('wm_montaza_price', 7800);
        $delivery_min = get_option('wm_montaza_delivery_min', 1);
        $delivery_max = get_option('wm_montaza_delivery_max', 5);
        $currency     = get_woocommerce_currency();

        echo wp_kses_post($notice);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Montaža Bojlera – Podešavanja', 'wm-montaza-bojlera'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=wm-montaza-settings')); ?>">
                <?php wp_nonce_field('wm_montaza_save_settings', 'wm_montaza_settings_nonce'); ?>
                <input type="hidden" name="wm_montaza_save" value="1" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="wm_montaza_price">
                                <?php echo esc_html__('Cena montaže:', 'wm-montaza-bojlera'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="wm_montaza_price"
                                   id="wm_montaza_price"
                                   value="<?php echo esc_attr($price); ?>"
                                   step="1"
                                   min="0"
                                   class="regular-text" />
                            <span class="description"><?php echo esc_html($currency); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wm_montaza_delivery_min">
                                <?php echo esc_html__('Vreme dostave – minimum (dana):', 'wm-montaza-bojlera'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="wm_montaza_delivery_min"
                                   id="wm_montaza_delivery_min"
                                   value="<?php echo esc_attr($delivery_min); ?>"
                                   step="1"
                                   min="1"
                                   class="small-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wm_montaza_delivery_max">
                                <?php echo esc_html__('Vreme dostave – maksimum (dana):', 'wm-montaza-bojlera'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   name="wm_montaza_delivery_max"
                                   id="wm_montaza_delivery_max"
                                   value="<?php echo esc_attr($delivery_max); ?>"
                                   step="1"
                                   min="1"
                                   class="small-text" />
                            <p class="description">
                                <?php esc_html_e('Mora biti veće ili jednako minimumu.', 'wm-montaza-bojlera'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit"
                           class="button button-primary"
                           value="<?php echo esc_attr__('Sačuvaj podešavanja', 'wm-montaza-bojlera'); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Product page checkbox
    // -------------------------------------------------------------------------

    public function render_product_checkbox(): void {
        global $product;
        if (!($product instanceof \WC_Product)) {
            return;
        }

        $price        = (float) get_option('wm_montaza_price', 7800);
        $delivery_min = (int) get_option('wm_montaza_delivery_min', 1);
        $delivery_max = (int) get_option('wm_montaza_delivery_max', 5);
        ?>
        <div class="wm-montaza-checkbox">
            <label>
                <?php
                /*
                 * Checkbox is always checked and disabled — montaza is mandatory.
                 * A hidden input ensures wm_montaza=1 is always submitted with the
                 * add-to-cart form (disabled inputs are not sent by browsers).
                 */
                ?>
                <input type="checkbox" id="wm_montaza_checkbox" value="1" checked disabled />
                <input type="hidden" name="wm_montaza" value="1" />
                <?php echo esc_html__('Montaža i dostava bojlera', 'wm-montaza-bojlera'); ?>
                +<?php echo wp_kses_post(wc_price($price)); ?>
                <small>
                    <strong>
                        <?php
                        echo esc_html(sprintf(
                            /* translators: 1: min days, 2: max days */
                            __('Vreme dostave: %1$s–%2$s radnih dana.', 'wm-montaza-bojlera'),
                            $delivery_min,
                            $delivery_max
                        ));
                        ?>
                    </strong>
                </small>
                <small>
                    <?php echo esc_html__('Montaža i dostava dostupne samo u Beogradu. U cenu uključena demontaža starog bojlera.', 'wm-montaza-bojlera'); ?>
                    <strong class="wm-montaza-highlight">
                        <?php echo esc_html__('Nosači i inox creva nisu uključeni.', 'wm-montaza-bojlera'); ?>
                    </strong>
                </small>
            </label>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Product page: price display with montaza included
    // -------------------------------------------------------------------------

    /**
     * Modifies the displayed price HTML on the single product page to include
     * the montaza price. Scoped strictly to the main product being viewed so
     * related products, upsells, and shop-page listings are unaffected.
     *
     * - Simple product: shows product_price + montaza_price (sale-aware)
     * - Variable product: shows (min_price + montaza) – (max_price + montaza)
     *   before a variation is selected; after selection, WC uses the modified
     *   variation data from modify_variation_data() below.
     *
     * @param string      $price_html Original price HTML.
     * @param \WC_Product $product    Product object.
     *
     * @return string
     */
    public function filter_price_html(string $price_html, \WC_Product $product): string {
        if (!is_singular('product') || $product->get_id() !== get_queried_object_id()) {
            return $price_html;
        }

        $montaza = (float) get_option('wm_montaza_price', 7800);

        if ($product->is_type('variable')) {
            /** @var \WC_Product_Variable $product */
            $prices = $product->get_variation_prices(true);

            if (empty($prices['price'])) {
                return $price_html;
            }

            $min = (float) min($prices['price']) + $montaza;
            $max = (float) max($prices['price']) + $montaza;

            if ($min === $max) {
                return wc_price($min) . $product->get_price_suffix();
            }

            return sprintf(
                /* translators: 1: price from, 2: price to */
                _x('%1$s – %2$s', 'Price range: from-to', 'woocommerce'),
                wc_price($min),
                wc_price($max)
            ) . $product->get_price_suffix();
        }

        // Simple / grouped / external product.
        $base = (float) wc_get_price_to_display($product);

        if (!$base) {
            return $price_html; // Free product — don't modify.
        }

        if ($product->is_on_sale()) {
            $regular = (float) wc_get_price_to_display($product, ['price' => $product->get_regular_price()]);
            return wc_format_sale_price($regular + $montaza, $base + $montaza)
                . $product->get_price_suffix();
        }

        return wc_price($base + $montaza) . $product->get_price_suffix();
    }

    /**
     * Modifies the variation data array sent to WooCommerce's frontend JS so
     * that when a shopper selects a variation, the displayed price already
     * includes the montaza price — with no client-side math or formatting needed.
     *
     * WooCommerce's own variation JS reads variation.price_html and renders it
     * directly into the DOM, so modifying it here is the canonical approach.
     *
     * @param array                  $data      Variation data for the JS payload.
     * @param \WC_Product_Variable   $product   Parent variable product.
     * @param \WC_Product_Variation  $variation Specific variation.
     *
     * @return array
     */
    public function modify_variation_data(array $data, \WC_Product_Variable $product, \WC_Product_Variation $variation): array {
        $montaza         = (float) get_option('wm_montaza_price', 7800);
        $display_price   = (float) ($data['display_price'] ?? 0);
        $display_regular = (float) ($data['display_regular_price'] ?? 0);

        // Update numeric values used in any JS-side price calculations.
        $data['display_price']         = $display_price + $montaza;
        $data['display_regular_price'] = $display_regular + $montaza;

        // Rebuild price_html — this is what WC injects into .single_variation .price.
        if ($display_price < $display_regular) {
            // Variation is on sale.
            $data['price_html'] = wc_format_sale_price(
                $display_regular + $montaza,
                $display_price   + $montaza
            ) . $variation->get_price_suffix();
        } else {
            $data['price_html'] = wc_price($display_price + $montaza)
                . $variation->get_price_suffix();
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Cart: data, fee, display
    // -------------------------------------------------------------------------

    /**
     * Attaches the montaza flag and the price locked at add-to-cart time.
     * Montaza is mandatory — it is always added regardless of how the product
     * reaches the cart (product page, direct URL, REST API, etc.).
     * Locking the price ensures admin changes don't silently alter open carts.
     *
     * @param array $cart_item_data Existing extra cart item data.
     * @param int   $_product_id    Product being added (unused but required by WC filter signature).
     *
     * @return array
     */
    public function save_cart_item_data(array $cart_item_data, int $_product_id): array {
        $cart_item_data['wm_montaza']       = true;
        $cart_item_data['wm_montaza_price'] = (float) get_option('wm_montaza_price', 7800);
        return $cart_item_data;
    }

    /**
     * Adds a WooCommerce cart fee for each item that has montaza selected.
     *
     * Using the Fees API instead of mutating the product's price gives us:
     * - a clean separate line item in cart, checkout, and order admin
     * - correct partial-refund support
     * - no double-add risk (WC resets all fees before every recalculation)
     *
     * @param \WC_Cart $cart Current cart instance.
     */
    public function add_montaza_fee(\WC_Cart $cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        $total = 0.0;
        foreach ($cart->get_cart() as $cart_item) {
            if (!empty($cart_item['wm_montaza'])) {
                $price  = isset($cart_item['wm_montaza_price'])
                    ? (float) $cart_item['wm_montaza_price']
                    : (float) get_option('wm_montaza_price', 7800);
                $total += $price * (int) $cart_item['quantity'];
            }
        }

        if ($total > 0.0) {
            // Third param: taxable. Set to true if your store charges VAT on installation services.
            $cart->add_fee(__('Montaža bojlera', 'wm-montaza-bojlera'), $total, false);
        }
    }

    /**
     * Modifies the displayed subtotal in the mini-cart widget to include the
     * montaza fee so that "Svega" reflects the actual amount the customer pays.
     *
     * Uses doing_action() to restrict the modification strictly to the mini-cart
     * widget context — the full cart/checkout page is unaffected because there
     * the fee already appears as a separate line item.
     *
     * @param string   $cart_subtotal Formatted subtotal HTML.
     * @param bool     $compound      Whether the subtotal is compound.
     * @param \WC_Cart $cart          Current cart instance.
     *
     * @return string
     */
    public function filter_mini_cart_subtotal(string $cart_subtotal, bool $compound, \WC_Cart $cart): string {
        if (!doing_action('woocommerce_widget_shopping_cart_total')) {
            return $cart_subtotal;
        }

        $fee_total = 0.0;
        foreach ($cart->get_cart() as $cart_item) {
            if (!empty($cart_item['wm_montaza'])) {
                $price      = isset($cart_item['wm_montaza_price'])
                    ? (float) $cart_item['wm_montaza_price']
                    : (float) get_option('wm_montaza_price', 7800);
                $fee_total += $price * (int) $cart_item['quantity'];
            }
        }

        if ($fee_total <= 0.0) {
            return $cart_subtotal;
        }

        $subtotal = $cart->get_subtotal();
        return wc_price($subtotal + $fee_total);
    }

    /**
     * Adds structured meta below the cart / checkout product row.
     *
     * @param array $item_data Existing meta rows.
     * @param array $cart_item Cart item data.
     *
     * @return array
     */
    public function filter_item_data(array $item_data, array $cart_item): array {
        if (!empty($cart_item['wm_montaza'])) {
            $price = isset($cart_item['wm_montaza_price'])
                ? (float) $cart_item['wm_montaza_price']
                : (float) get_option('wm_montaza_price', 7800);

            $item_data[] = [
                'name'  => __('Montaža bojlera', 'wm-montaza-bojlera'),
                'value' => wp_kses_post(wc_price($price)),
            ];
        }
        return $item_data;
    }

    // -------------------------------------------------------------------------
    // Checkout: inline offer for items added without montaza
    // -------------------------------------------------------------------------

    /**
     * Renders an informational, always-checked, disabled checkbox on the checkout
     * order review for each cart item that carries montaza (which is all items).
     * The checkbox is purely visual — it cannot be unchecked. Its purpose is to
     * clearly communicate to the customer that montaza is always included.
     */
    public function render_checkout_checkboxes(): void {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $price        = (float) get_option('wm_montaza_price', 7800);
        $delivery_min = (int) get_option('wm_montaza_delivery_min', 1);
        $delivery_max = (int) get_option('wm_montaza_delivery_max', 5);

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['wm_montaza'])) {
                continue;
            }
            ?>
            <tr class="wm-montaza-checkout-row">
                <td colspan="2">
                    <label>
                        <input type="checkbox" value="1" checked disabled />
                        <span>
                            <strong>
                                <?php echo esc_html__('Montaža i dostava bojlera je uključena', 'wm-montaza-bojlera'); ?>
                            </strong>
                            (<?php echo wp_kses_post(wc_price($price)); ?>)
                            <small>
                                <strong>
                                    <?php
                                    echo esc_html(sprintf(
                                        /* translators: 1: min days, 2: max days */
                                        __('Vreme dostave: %1$s–%2$s radnih dana.', 'wm-montaza-bojlera'),
                                        $delivery_min,
                                        $delivery_max
                                    ));
                                    ?>
                                </strong>
                            </small>
                            <small>
                                <?php echo esc_html__('Montaža i dostava dostupne samo u Beogradu. U cenu uključena demontaža starog bojlera.', 'wm-montaza-bojlera'); ?>
                                <strong class="wm-montaza-highlight">
                                    <?php echo esc_html__('Nosači i inox creva nisu uključeni.', 'wm-montaza-bojlera'); ?>
                                </strong>
                            </small>
                        </span>
                    </label>
                </td>
            </tr>
            <?php
        }
    }

    // -------------------------------------------------------------------------
    // Order: persist montaza metadata on the order line item
    // -------------------------------------------------------------------------

    /**
     * @param \WC_Order_Item_Product $item           Line item being created.
     * @param string                 $cart_item_key  Cart item hash.
     * @param array                  $values         Cart item data array.
     * @param \WC_Order              $order          Order being placed.
     */
    public function save_order_item_meta($item, string $cart_item_key, array $values, $order): void {
        if (!empty($values['wm_montaza'])) {
            $price = isset($values['wm_montaza_price'])
                ? (float) $values['wm_montaza_price']
                : (float) get_option('wm_montaza_price', 7800);

            // Store as plain text — shown in order emails and admin meta table.
            $item->add_meta_data(
                __('Montaža bojlera', 'wm-montaza-bojlera'),
                wp_strip_all_tags(wc_price($price))
            );
        }
    }

    // -------------------------------------------------------------------------
    // Shortcode [montaza_bojlera]
    // -------------------------------------------------------------------------

    /**
     * Renders a styled info block with the current montaza price and delivery window.
     * Usage: [montaza_bojlera]
     *
     * @param array|string $atts Shortcode attributes (currently unused).
     *
     * @return string HTML output.
     */
    public function shortcode($atts): string {
        $price        = (float) get_option('wm_montaza_price', 7800);
        $delivery_min = (int) get_option('wm_montaza_delivery_min', 1);
        $delivery_max = (int) get_option('wm_montaza_delivery_max', 5);

        ob_start();
        ?>
        <div class="wm-montaza-info">
            <p>
                <strong>
                    <?php echo esc_html__('Montaža i dostava bojlera:', 'wm-montaza-bojlera'); ?>
                    <?php echo wp_kses_post(wc_price($price)); ?>
                </strong>
            </p>
            <p>
                <strong>
                    <?php
                    echo esc_html(sprintf(
                        /* translators: 1: min days, 2: max days */
                        __('Vreme dostave: %1$s–%2$s radnih dana.', 'wm-montaza-bojlera'),
                        $delivery_min,
                        $delivery_max
                    ));
                    ?>
                </strong>
            </p>
            <p>
                <?php echo esc_html__('Montaža i dostava dostupne samo u Beogradu. U cenu uključena demontaža starog bojlera.', 'wm-montaza-bojlera'); ?>
                <strong class="wm-montaza-highlight">
                    <?php echo esc_html__('Nosači i inox creva nisu uključeni.', 'wm-montaza-bojlera'); ?>
                </strong>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

}