<?php
/*
Plugin Name: Montaža Bojlera
Description: Dodaje montažu i dostavu bojlera kao dodatak uz proizvod. Checkbox na stranici proizvoda dodaje uslugu u korpu. Administrator može da podesi cenu i vreme dostave.
Version: 1.0
Author: Bojan Pavlovć
*/

if (!defined('ABSPATH')) exit;

// Check if WooCommerce is active
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . esc_html__('Plugin "Montaža Bojlera" zahteva WooCommerce. Molimo aktivirajte WooCommerce.', 'wm-montaza-bojlera') . '</p></div>';
        });
        return;
    }
    
    // Initialize plugin only if WooCommerce is active
    wm_montaza_init();
});

// Activation - create price option
register_activation_hook(__FILE__, function() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Ovaj plugin zahteva WooCommerce. Molimo aktivirajte WooCommerce prvo.');
    }
    if (get_option('wm_montaza_price') === false) {
        add_option('wm_montaza_price', 7800);
    }
    if (get_option('wm_montaza_delivery_min') === false) {
        add_option('wm_montaza_delivery_min', 1);
    }
    if (get_option('wm_montaza_delivery_max') === false) {
        add_option('wm_montaza_delivery_max', 5);
    }
});

// Uninstall - delete option
register_uninstall_hook(__FILE__, 'wm_montaza_uninstall');
function wm_montaza_uninstall() {
    delete_option('wm_montaza_price');
    delete_option('wm_montaza_delivery_min');
    delete_option('wm_montaza_delivery_max');
}

// Admin menu function
function wm_montaza_settings_page() {
    if (isset($_POST['wm_montaza_price']) && current_user_can('manage_options')) {
        if (!isset($_POST['wm_montaza_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wm_montaza_settings_nonce'])), 'wm_montaza_save_settings')) {
            echo '<div class="error"><p>' . esc_html__('Provera bezbednosti nije uspela. Pokušajte ponovo.', 'wm-montaza-bojlera') . '</p></div>';
        } else {
            update_option('wm_montaza_price', floatval($_POST['wm_montaza_price']));
            update_option('wm_montaza_delivery_min', max(1, intval($_POST['wm_montaza_delivery_min'])));
            update_option('wm_montaza_delivery_max', max(1, intval($_POST['wm_montaza_delivery_max'])));
            echo '<div class="updated"><p>' . esc_html__('Podešavanja su sačuvana.', 'wm-montaza-bojlera') . '</p></div>';
        }
    }
    $price = get_option('wm_montaza_price', 7800);
    $delivery_min = get_option('wm_montaza_delivery_min', 1);
    $delivery_max = get_option('wm_montaza_delivery_max', 5);
    $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Montaža Bojlera – Podešavanja', 'wm-montaza-bojlera'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('wm_montaza_save_settings', 'wm_montaza_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wm_montaza_price"><?php echo esc_html__('Cena montaže:', 'wm-montaza-bojlera'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="wm_montaza_price" id="wm_montaza_price" value="<?php echo esc_attr($price); ?>" step="1" /> <?php echo esc_html($currency); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wm_montaza_delivery_min"><?php echo esc_html__('Vreme dostave – minimum (dana):', 'wm-montaza-bojlera'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="wm_montaza_delivery_min" id="wm_montaza_delivery_min" value="<?php echo esc_attr($delivery_min); ?>" step="1" min="1" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wm_montaza_delivery_max"><?php echo esc_html__('Vreme dostave – maximum (dana):', 'wm-montaza-bojlera'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="wm_montaza_delivery_max" id="wm_montaza_delivery_max" value="<?php echo esc_attr($delivery_max); ?>" step="1" min="1" />
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php echo esc_attr__('Sačuvaj', 'wm-montaza-bojlera'); ?>" />
            </p>
        </form>
    </div>
    <?php
}

// Initialize all hooks
function wm_montaza_init() {
    // Admin menu
    add_action('admin_menu', function() {
        add_menu_page(
            __('Montaža Bojlera', 'wm-montaza-bojlera'),
            __('Montaža Bojlera', 'wm-montaza-bojlera'),
            'manage_options',
            'wm-montaza-settings',
            'wm_montaza_settings_page',
            'dashicons-admin-generic',
            56
        );
    });

    // Checkbox on single product page
    add_action('woocommerce_before_add_to_cart_button', function() {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return;
        global $product;
        if (!$product) return;
        $price = get_option('wm_montaza_price', 7800);
        $delivery_min = get_option('wm_montaza_delivery_min', 1);
        $delivery_max = get_option('wm_montaza_delivery_max', 5);
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
        ?>
        <div class="wm-montaza-checkbox" style="margin-bottom:10px;">
            <label>
                <input type="checkbox" name="wm_montaza" id="wm_montaza_checkbox" value="1" /> 
                <?php echo esc_html__('Montaža i dostava bojlera', 'wm-montaza-bojlera'); ?> +<?php echo esc_html(number_format($price, 0, ',', '.')); ?> <?php echo esc_html($currency); ?>
                <br>
                <small><strong><?php echo esc_html__('Vreme dostave:', 'wm-montaza-bojlera'); ?> <?php echo esc_html($delivery_min); ?>–<?php echo esc_html($delivery_max); ?> <?php echo esc_html__('radnih dana.', 'wm-montaza-bojlera'); ?></strong></small>
                <br>
                <small><?php echo esc_html__('Montaža i dostava dostupne samo u Beogradu. U cenu uključena demontaža starog bojlera.', 'wm-montaza-bojlera'); ?> <strong style="color:#d32f2f;"><?php echo esc_html__('Nosači i inox creva nisu uključeni.', 'wm-montaza-bojlera'); ?></strong></small>
            </label>
        </div>
        <?php
    });

    // Save montaza option when adding to cart
    add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id) {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return $cart_item_data;
        if (isset($_POST['wm_montaza']) && sanitize_text_field(wp_unslash($_POST['wm_montaza'])) === '1') {
            $cart_item_data['wm_montaza'] = true;
            $cart_item_data['wm_montaza_price'] = get_option('wm_montaza_price', 7800);
        }
        return $cart_item_data;
    }, 10, 2);

    // Add montaza price to cart item price
    add_action('woocommerce_before_calculate_totals', function($cart) {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return;
        if (is_admin() && !defined('DOING_AJAX')) return;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wm_montaza']) && $cart_item['wm_montaza']) {
                $montaza_price = isset($cart_item['wm_montaza_price']) ? $cart_item['wm_montaza_price'] : get_option('wm_montaza_price', 7800);
                $product = $cart_item['data'];
                if (!$product) continue;
                $current_price = $product->get_price();
                // Reset price to original first
                if (isset($cart_item['wm_original_price'])) {
                    $product->set_price($cart_item['wm_original_price']);
                    $current_price = $cart_item['wm_original_price'];
                } else {
                    $cart->cart_contents[$cart_item_key]['wm_original_price'] = $current_price;
                }
                // Add montaza price
                $product->set_price($current_price + $montaza_price);
            } else {
                // If montaza was removed, restore original price
                if (isset($cart_item['wm_original_price'])) {
                    $product = $cart_item['data'];
                    if ($product) {
                        $product->set_price($cart_item['wm_original_price']);
                    }
                }
            }
        }
    }, 10, 1);

    // Display montaza info in cart item name
    add_filter('woocommerce_cart_item_name', function($name, $cart_item, $cart_item_key) {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return $name;
        if (isset($cart_item['wm_montaza']) && $cart_item['wm_montaza']) {
            $montaza_price = isset($cart_item['wm_montaza_price']) ? $cart_item['wm_montaza_price'] : get_option('wm_montaza_price', 7800);
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
            $name .= '<br><small style="color:#666;">+ ' . esc_html__('Montaža bojlera', 'wm-montaza-bojlera') . ' (' . esc_html(number_format($montaza_price, 0, ',', '.') . ' ' . $currency) . ')</small>';
        }
        return $name;
    }, 10, 3);

    // Display montaza info in cart item meta
    add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return $item_data;
        if (isset($cart_item['wm_montaza']) && $cart_item['wm_montaza']) {
            $montaza_price = isset($cart_item['wm_montaza_price']) ? $cart_item['wm_montaza_price'] : get_option('wm_montaza_price', 7800);
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
            $item_data[] = array(
                'name'  => __('Montaža bojlera', 'wm-montaza-bojlera'),
                'value' => number_format($montaza_price, 0, ',', '.') . ' ' . $currency,
            );
        }
        return $item_data;
    }, 10, 2);

    // Checkbox on checkout page for products without montaza
    add_action('woocommerce_review_order_before_cart_contents', function() {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return;
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) return;
        
        $price = get_option('wm_montaza_price', 7800);
        $delivery_min = get_option('wm_montaza_delivery_min', 1);
        $delivery_max = get_option('wm_montaza_delivery_max', 5);
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Show checkbox only for items without montaza
            if (!isset($cart_item['wm_montaza']) || !$cart_item['wm_montaza']) {
                $product = $cart_item['data'];
                $product_name = $product->get_name();
                ?>
                <tr class="wm-montaza-checkout-row">
                    <td colspan="2" style="padding-left:20px;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" 
                                   class="wm-montaza-checkout-checkbox" 
                                   data-cart-key="<?php echo esc_attr($cart_item_key); ?>"
                                   value="1" />
                            <span>
                                <strong><?php echo esc_html__('Dodaj montažu i dostavu bojlera', 'wm-montaza-bojlera'); ?></strong>
                                (+<?php echo esc_html(number_format($price, 0, ',', '.')); ?> <?php echo esc_html($currency); ?>)
                                <br>
                                <small><strong><?php echo esc_html__('Vreme dostave:', 'wm-montaza-bojlera'); ?> <?php echo esc_html($delivery_min); ?>–<?php echo esc_html($delivery_max); ?> <?php echo esc_html__('radnih dana.', 'wm-montaza-bojlera'); ?></strong></small>
                                <br>
                                <small style="color:#666;"><?php echo esc_html__('Montaža i dostava dostupne samo u Beogradu. U cenu uključena demontaža starog bojlera.', 'wm-montaza-bojlera'); ?> <strong style="color:#d32f2f;"><?php echo esc_html__('Nosači i inox creva nisu uključeni.', 'wm-montaza-bojlera'); ?></strong></small>
                            </span>
                        </label>
                    </td>
                </tr>
                <?php
            }
        }
    });

    // AJAX handler for adding montaza from checkout
    add_action('wp_ajax_wm_add_montaza_checkout', 'wm_add_montaza_checkout');
    add_action('wp_ajax_nopriv_wm_add_montaza_checkout', 'wm_add_montaza_checkout');
    function wm_add_montaza_checkout() {
        if (!function_exists('WC') || !class_exists('WooCommerce')) {
            wp_send_json_error(__('WooCommerce nije aktivan.', 'wm-montaza-bojlera'));
        }
        if (!isset($_POST['wm_montaza_checkout_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wm_montaza_checkout_nonce'])), 'wm_montaza_checkout')) {
            wp_send_json_error(__('Provera bezbednosti nije uspela.', 'wm-montaza-bojlera'));
        }
        if (!isset($_POST['cart_key']) || !isset($_POST['checked'])) {
            wp_send_json_error(__('Nedostaju parametri.', 'wm-montaza-bojlera'));
        }
        $cart      = WC()->cart;
        $cart_key  = sanitize_text_field(wp_unslash($_POST['cart_key']));
        $checked   = sanitize_text_field(wp_unslash($_POST['checked'])) === '1';
        
        $cart_item = $cart->get_cart_item($cart_key);
        if (!$cart_item) {
            wp_send_json_error(__('Proizvod nije pronađen u korpi.', 'wm-montaza-bojlera'));
        }
        
        if ($checked) {
            // Add montaza
            $montaza_price = get_option('wm_montaza_price', 7800);
            $cart->cart_contents[$cart_key]['wm_montaza'] = true;
            $cart->cart_contents[$cart_key]['wm_montaza_price'] = $montaza_price;
            if (!isset($cart->cart_contents[$cart_key]['wm_original_price'])) {
                $product = $cart_item['data'];
                $cart->cart_contents[$cart_key]['wm_original_price'] = $product->get_price();
            }
        } else {
            // Remove montaza
            unset($cart->cart_contents[$cart_key]['wm_montaza']);
            unset($cart->cart_contents[$cart_key]['wm_montaza_price']);
        }
        
        $cart->calculate_totals();
        
        wp_send_json_success(array(
            'message' => __('Uspešno ažurirano.', 'wm-montaza-bojlera'),
            'total'   => $cart->get_total(),
        ));
    }

    // JavaScript for checkout checkboxes (enqueued with dependency + version per .cursor rules)
    add_action('wp_enqueue_scripts', function() {
        if (!is_checkout()) return;
        wp_register_script(
            'wm-montaza-checkout',
            false,
            array('jquery'),
            (string) filemtime(__DIR__ . '/montaza-bojlera-addon.php'),
            true
        );
        wp_enqueue_script('wm-montaza-checkout');
        wp_add_inline_script('wm-montaza-checkout', 'var wmMontazaCheckout = ' . wp_json_encode(array(
            'ajaxUrl'   => esc_url(admin_url('admin-ajax.php')),
            'nonce'     => wp_create_nonce('wm_montaza_checkout'),
            'i18nError' => __('Greška:', 'wm-montaza-bojlera'),
            'i18nRefresh' => __('Došlo je do greške. Molimo osvežite stranicu.', 'wm-montaza-bojlera'),
        )) . ';', 'before');
        $inline_js = <<<'JS'
jQuery(function($) {
    $('.wm-montaza-checkout-checkbox').on('change', function() {
        var checkbox = $(this);
        var cartKey = checkbox.data('cart-key');
        var checked = checkbox.is(':checked') ? '1' : '0';
        checkbox.prop('disabled', true);
        $.post(wmMontazaCheckout.ajaxUrl, {
            action: 'wm_add_montaza_checkout',
            wm_montaza_checkout_nonce: wmMontazaCheckout.nonce,
            cart_key: cartKey,
            checked: checked
        }, function(response) {
            if (response.success) {
                $('body').trigger('update_checkout');
            } else {
                alert((wmMontazaCheckout.i18nError || 'Greška: ') + (response.data || ''));
                checkbox.prop('checked', checked !== '1');
            }
            checkbox.prop('disabled', false);
        }).fail(function() {
            alert(wmMontazaCheckout.i18nRefresh || 'Došlo je do greške. Molimo osvežite stranicu.');
            checkbox.prop('checked', checked !== '1');
            checkbox.prop('disabled', false);
        });
    });
});
JS;
        wp_add_inline_script('wm-montaza-checkout', $inline_js, 'after');
    }, 10);

    // Display montaza in order item meta
    add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
        if (!function_exists('WC') || !class_exists('WooCommerce')) return;
        if (isset($values['wm_montaza']) && $values['wm_montaza']) {
            $montaza_price = isset($values['wm_montaza_price']) ? $values['wm_montaza_price'] : get_option('wm_montaza_price', 7800);
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
            $item->add_meta_data(__('Montaža bojlera', 'wm-montaza-bojlera'), number_format($montaza_price, 0, ',', '.') . ' ' . $currency);
        }
    }, 10, 4);

    // Shortcode for displaying montaza info (without checkbox)
    add_shortcode('montaza_bojlera', 'wm_montaza_shortcode');
    function wm_montaza_shortcode($atts) {
        $price = get_option('wm_montaza_price', 7800);
        $delivery_min = get_option('wm_montaza_delivery_min', 1);
        $delivery_max = get_option('wm_montaza_delivery_max', 5);
        $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'RSD';
        
        $output  = '<div class="wm-montaza-info" style="margin:15px 0;padding:15px;background:#f9f9f9;border-left:3px solid #d32f2f;">';
        $output .= '<p style="margin:0 0 10px 0;"><strong>' . esc_html__('Montaža i dostava bojlera:', 'wm-montaza-bojlera') . ' ' . esc_html(number_format($price, 0, ',', '.') . ' ' . $currency) . '</strong></p>';
        $output .= '<p style="margin:0 0 10px 0;"><strong>' . esc_html__('Vreme dostave:', 'wm-montaza-bojlera') . ' ' . esc_html($delivery_min) . '–' . esc_html($delivery_max) . ' ' . esc_html__('radnih dana.', 'wm-montaza-bojlera') . '</strong></p>';
        $output .= '<p style="margin:0;font-size:14px;color:#666;">' . esc_html__('Montaža i dostava dostupne samo u Beogradu. U cenu uključena demontaža starog bojlera.', 'wm-montaza-bojlera') . ' <strong style="color:#d32f2f;">' . esc_html__('Nosači i inox creva nisu uključeni.', 'wm-montaza-bojlera') . '</strong></p>';
        $output .= '</div>';
        
        return $output;
    }
}
