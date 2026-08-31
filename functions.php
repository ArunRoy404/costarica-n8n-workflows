<?php

// Load the stylesheets for the child theme
add_action('wp_enqueue_scripts', 'my_child_theme_styles');
function my_child_theme_styles()
{
    // Enqueue parent style
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );


    // Enqueue child style
    wp_enqueue_style(
        'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        ['parent-style'], // Make parent a dependency
        wp_get_theme()->get('Version')
    );
	
	wp_enqueue_script(
        'child-script-new', // Handle (your choice)
        get_stylesheet_directory_uri() . '/script-new.js', // Path to your file
        ['jquery', 'wc-add-to-cart'], // Dependencies (jQuery + WooCommerce add-to-cart)
        wp_get_theme()->get('Version'), // Version
        true // Load in footer
    );
}

// Add custom post type for Tours
add_action('pre_get_posts', 'ctb_show_available_tours_on_archive');
function ctb_show_available_tours_on_archive($query)
{
    if (!is_admin() && $query->is_main_query() && is_post_type_archive('tour')) {
        $query->set('posts_per_page', -1); // Show all
        // Or use: $query->set('posts_per_page', 20);
    }
}
// Add Bootstrap CSS and JS for tours
add_action('wp_enqueue_scripts', 'enqueue_bootstrap_for_tours');
function enqueue_bootstrap_for_tours()
{
    if (is_singular('tour')) {
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', [], null, true);
    }
}


add_filter('get_the_archive_title', function ($title) {
    if (is_category()) {
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
    } elseif (is_author()) {
        $title = get_the_author();
    } elseif (is_tax()) {
        $title = single_term_title('', false);
    }
    return $title;
});

function custom_archive_order($query)
{
    if ($query->is_archive() && $query->is_main_query() && !is_admin()) {
        $query->set('order', 'ASC');  // ASC = Oldest First, DESC = Newest First
        //$query->set('orderby', 'date'); // alphabetic karna ho to 'title' use karna
    }
}
add_action('pre_get_posts', 'custom_archive_order');




// Disable all automatic updates in WordPress
add_filter('automatic_updater_disabled', '__return_true');

// Disable core updates
add_filter('pre_site_transient_update_core', '__return_null');

// Disable plugin updates
add_filter('pre_site_transient_update_plugins', '__return_null');

// Disable theme updates
add_filter('pre_site_transient_update_themes', '__return_null');

// Optional: Hide update notifications from dashboard
remove_action('load-update-core.php', 'wp_update_plugins');
remove_action('load-update-core.php', 'wp_update_themes');
add_action('admin_menu', 'remove_update_core_menu');
function remove_update_core_menu()
{
    remove_submenu_page('index.php', 'update-core.php');
}



function my_custom_coupon_form_display()
{
    if (! is_admin() && is_cart() && wc_coupons_enabled()) {
        echo '<div class="custom-coupon-form">';
        wc_get_template('cart/form-coupon.php');
        echo '</div>';
    }
}

add_action('wp_ajax_update_cart_item_quantity', 'custom_update_cart_item_quantity');
add_action('wp_ajax_nopriv_update_cart_item_quantity', 'custom_update_cart_item_quantity');

function custom_update_cart_item_quantity()
{
    $cart_item_key = sanitize_text_field($_POST['cart_item_key']);
    $quantity = intval($_POST['quantity']);

    if (isset(WC()->cart->cart_contents[$cart_item_key])) {
        WC()->cart->set_quantity($cart_item_key, $quantity, true);
        WC()->cart->calculate_totals();
        wp_send_json_success();
    } else {
        wp_send_json_error('Item not found.');
    }
    wp_die();
}





add_action('wp_enqueue_scripts', 'enqueue_ajax_cart_script');
function enqueue_ajax_cart_script()
{
    wp_enqueue_script('jquery');
    wp_localize_script('jquery', 'wc_cart_params', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}

// Agar cart empty ho to cart page se home page par redirect karo
add_action('template_redirect', 'redirect_empty_cart_to_home');
function redirect_empty_cart_to_home()
{
    if (is_cart() && WC()->cart->is_empty()) {
        wp_safe_redirect(home_url());
        exit;
    }
}



function load_custom_google_fonts()
{
    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap', false);
}
add_action('wp_enqueue_scripts', 'load_custom_google_fonts');

add_filter('woocommerce_account_menu_items', 'custom_rename_addresses_tab', 99);
function custom_rename_addresses_tab($items)
{
    $items['edit-address'] = 'Address'; // Change to your desired label
    return $items;
}


add_filter('gettext', 'custom_change_tax_label_once', 20, 3);
function custom_change_tax_label_once($translated_text, $text, $domain)
{
    // Only replace exactly "Tax" from the original source
    if ('woocommerce' === $domain && $text === 'Tax') {
        return 'Sales Tax';
    }
    return $translated_text;
}

add_action('template_redirect', 'custom_redirect_empty_cart');
function custom_redirect_empty_cart()
{
    if (is_cart() && WC()->cart->is_empty()) {
        wp_redirect(home_url());
        exit;
    }
}


add_filter('woocommerce_get_terms_and_conditions_checkbox_text', 'custom_terms_checkbox_text');
function custom_terms_checkbox_text($text)
{
    $terms_page_id = wc_get_page_id('terms');
    if ($terms_page_id > 0) {
        $terms_url = get_permalink($terms_page_id);
        $text = 'I have read and agree to the <a href="' . esc_url($terms_url) . '" target="_blank">Terms and Conditions</a>';
    }
    return $text;
}


// Email me "Total (you pay 20%)" add karo
add_filter('woocommerce_get_order_item_totals', function ($total_rows, $order, $tax_display) {
    if (isset($total_rows['order_total'])) {
        $total_rows['order_total']['label'] = __('Total (you pay 20%)', 'woocommerce');
    }
    return $total_rows;
}, 20, 3);


// Force comments open for all posts
add_filter('comments_open', function ($open, $post_id) {
    if (is_single() && 'post' == get_post_type($post_id)) {
        return true;
    }
    return $open;
}, 10, 2);

// Force show comments template
add_filter('comments_template', function ($template) {
    if (!is_admin()) {
        return $template;
    }
    return $template;
});


// Ensure Balance to pay driver fee is visible in ALL emails (including admin)
// add_filter('woocommerce_get_order_item_totals', function($totals, $order, $tax_display) {
//     foreach ($order->get_items('fee') as $item_id => $item) {
//         if ($item->get_name() === 'Balance to pay driver') {
//             $amount = wc_price(abs($item->get_total()), ['currency' => $order->get_currency()]);
//             $totals['balance_to_pay_driver'] = [
//                 'label' => 'Balance to pay driver',
//                 'value' => $amount,
//             ];
//         }
//     }
//     return $totals;
// }, 30, 3);







add_action('wp_footer', function () {
?>
    <script>
        jQuery(document).ready(function($) {
            $(".summary-line span:first-child").each(function() {
                if ($(this).text().includes("Payment Fee")) {
                    $(this).text("Payment Fee");
                }
            });
        });

        jQuery(document).ready(function($) {
            $('address.address li[scope="row"]').each(function() {
                var text = $(this).text().trim();
                if (text.includes("Payment Fee")) {
                    $(this).text("Payment Fee:");
                }
            });
        });

        jQuery(document).ready(function($) {
            $('.wc-block-components-totals-item__label').each(function() {
                var text = $(this).text().trim();
                if (text.includes("Payment Fee")) {
                    $(this).text("Payment Fee");
                }
            });
            $('.wc-block-components-totals-item__label').each(function() {
                var text = $(this).text().trim();
                if (text === "Taxes") {
                    $(this).text("Sales Taxes");
                }
            });
        });

        $('.wc-block-components-text-input.wc-block-components-address-form__postcode label').each(function() {
        var text = $(this).text().trim();
        if (text === "Postal code") {
            $(this).text("Zip Code/Postal Code");
        }
        });
        });
    </script>
    <?php
});













// Add this in your theme’s functions.php
add_action('wp_footer', function () {
    if (is_checkout()) {
    ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const observer = new MutationObserver(function() {
                    const totalLabel = document.querySelector(".wc-block-components-totals-item__label");
                    if (totalLabel && totalLabel.textContent.trim() === "Total") {
                        totalLabel.textContent = "Total (you pay 20%)";
                        observer.disconnect(); // Stop observing once done
                    }
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true
                });
            });
        </script>
    <?php
    }
});

/**
 * Cart UI: replace quantity with "Passengers" (uses ACF min/max on tour)
 */
add_filter('woocommerce_cart_item_quantity', function ($product_quantity, $cart_item_key, $cart_item) {
    if (empty($cart_item['tour_id'])) {
        return $product_quantity;
    }

    $tour_id   = (int) $cart_item['tour_id'];
    $isGroup = get_field('pricing_by_group', $cart_item['tour_id']);

    $minPax    = (int) (get_field('tier_1_min',  $tour_id) ?: 1);
    $maxPax    = (int) (get_field('tier_1_max',  $tour_id) ?: 50); // adjust sensible cap if needed

    if ($isGroup) {
        $minPax = get_field('tire-1_min_passenger', $tour_id);
        $maxPax = get_field('tire-3_max_passenger', $tour_id);
    }

    $current   = isset($cart_item['passenger_count']) ? (int) $cart_item['passenger_count'] : $minPax;
    $current   = max($minPax, min($current, $maxPax));

    ob_start(); ?>
    <div class="quantity_incre tour-pass">
        <label for="pax-<?php echo esc_attr($cart_item_key); ?>"><span><?php esc_html_e('Passengers:', 'your-textdomain'); ?></span></label>
        <select id="pax-<?php echo esc_attr($cart_item_key); ?>"
            name="cart[<?php echo esc_attr($cart_item_key); ?>][passenger_count]"
            class="input-select">
            <?php for ($i = $minPax; $i <= $maxPax; $i++): ?>
                <option value="<?php echo esc_attr($i); ?>" <?php selected($current, $i); ?>>
                    <?php echo esc_html($i); ?>
                </option>
            <?php endfor; ?>
        </select>
        <!-- keep Woo quantity fixed at 1 -->
        <input type="hidden" name="cart[<?php echo esc_attr($cart_item_key); ?>][qty]" value="1" />
    </div>
<?php
    return ob_get_clean();
}, 10, 3);

/**
 * Persist passengers for tour items only; leave non-tour items unchanged.
 */
add_action('woocommerce_update_cart_action_cart_updated', function () {
    if (empty($_POST['cart']) || !is_array($_POST['cart'])) return;

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

        // Only apply custom logic when this cart line is a tour
        if (isset($cart_item['tour_id']) && !empty($cart_item['tour_id'])) {

            // Force qty=1 for tours so passengers control the price
            WC()->cart->cart_contents[$cart_item_key]['quantity'] = 1;

            // Update passengers if posted
            if (isset($_POST['cart'][$cart_item_key]['passenger_count'])) {
                $tour_id = (int) $cart_item['tour_id'];

                $minPax = (int) (get_field('tier_1_min', $tour_id) ?: 1);
                $maxPax = (int) (get_field('tier_1_max', $tour_id) ?: 50);

                $isGroup = get_field('pricing_by_group', $tour_id);
                if ($isGroup) {
                    $minPax = get_field('tire-1_min_passenger', $tour_id);
                    $maxPax = get_field('tire-3_max_passenger', $tour_id);
                }

                $raw = (int) $_POST['cart'][$cart_item_key]['passenger_count'];
                $pax = max($minPax, min($raw, $maxPax));

                WC()->cart->cart_contents[$cart_item_key]['passenger_count'] = $pax;
            }
        } else {
            // Non-tour item: do NOTHING here.
            // - Don't force qty=1
            // - Don't touch passenger_count
            // WooCommerce (or your existing qty AJAX) will handle quantity updates as usual.
        }
    }
});







/**
 * Pricing: set line price from ACF + passenger_count
 * - If pricing_by_group: price = basePrice (no multiply)
 * - Else (per-person):  price = basePrice * passenger_count
 */
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        $product = $cart_item['data'];
        if (!$product) continue;

        // 🚫 Non-tour items: do nothing (let Woo handle qty & price normally)
        if (!isset($cart_item['tour_id'])) {
            continue;
        }

        // ✅ Tour items: custom pricing
        $tour_id   = (int) $cart_item['tour_id'];
        $minPax    = (int) (get_field('tier_1_min',  $tour_id) ?: 1);
        $maxPax    = (int) (get_field('tier_1_max',  $tour_id) ?: 999999);

        $isGroup = get_field('pricing_by_group', $tour_id);
		$basePrice = (float) (get_field('tier_1_price', $tour_id) ?: 0.0);
        if ($isGroup) {
            $minPax = get_field('tire-1_min_passenger', $tour_id);
            $maxPax = get_field('tire-3_max_passenger', $tour_id);
			$maxPax1 = get_field('tire-1_max_passenger', $tour_id);
			$maxPax2 = get_field('tire-2_max_passenger', $tour_id);
			$maxPax3 = get_field('tire-3_max_passenger', $tour_id);
			$duration = $cart_item['duration'];
			
			$tier = null;
			
			if($maxPax1 >= $cart_item['passenger_count']){
				$tier = get_field('tire-1', $tour_id);
			}elseif($maxPax2 >= $cart_item['passenger_count']){
				$tier = get_field('tire-2', $tour_id);
			}elseif($maxPax3 >= $cart_item['passenger_count']){
				$tier = get_field('tire-3', $tour_id);
			}
			
			fwc_log('[FWC_DEBUG] tier', $tier);
			
			if($duration == 'half_day_morning_4hours'){
				$basePrice = $tier['half_day_morning_4hours'];
			}elseif($duration == 'half_day_afternoon_4hours'){
				$basePrice = $tier['half_day_afternoon_4hours'];
			}elseif($duration == 'threeQ_day_6hours'){
				$basePrice = $tier['34_day_6hours'];
			}elseif($duration == 'full_day_8hours'){
				$basePrice = $tier['full_day_8hours'];
			}
			
			
        }
        

        $pax = isset($cart_item['passenger_count']) ? (int) $cart_item['passenger_count'] : $minPax;
        $pax = max($minPax, min($pax, $maxPax));
        $cart->cart_contents[$cart_item_key]['passenger_count'] = $pax;

        $line_unit_price = $isGroup ? $basePrice : ($basePrice * $pax);
        $product->set_price($line_unit_price);

        // Force qty=1 **only** for tour lines
        $cart->cart_contents[$cart_item_key]['quantity'] = 1;
    }
}, 10, 1);


/**
 * Show passengers under product name (cart/checkout)
 */
add_filter('woocommerce_cart_item_name', function ($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['passenger_count'])) {
        $name .= '<br><small>' . esc_html__('Passengers:', 'your-textdomain') . ' ' . intval($cart_item['passenger_count']) . '</small>';
    }
    return $name;
}, 10, 3);

/**
 * Persist passengers to the order line item
 */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (isset($values['passenger_count'])) {
        $item->add_meta_data('Passengers', (int) $values['passenger_count'], true);
    }
}, 10, 3);

add_action('wp_ajax_update_cart_item_passengers', 'my_update_cart_item_passengers');
add_action('wp_ajax_nopriv_update_cart_item_passengers', 'my_update_cart_item_passengers');

function my_update_cart_item_passengers()
{
    // Uncomment if you add the nonce in JS
    // check_ajax_referer('update-passengers', 'nonce');

    if (empty($_POST['cart_item_key'])) {
        wp_send_json_error(['message' => 'Missing cart_item_key']);
    }

    $cart_item_key  = wc_clean(wp_unslash($_POST['cart_item_key']));
    $new_pax        = isset($_POST['passenger_count']) ? (int) $_POST['passenger_count'] : 0;

    $cart = WC()->cart;
    if (!$cart || !isset($cart->cart_contents[$cart_item_key])) {
        wp_send_json_error(['message' => 'Invalid cart item.']);
    }

    $item    = $cart->cart_contents[$cart_item_key];
    $tour_id = !empty($item['tour_id']) ? (int) $item['tour_id'] : 0;

    // Fallbacks if no tour_id
    $minPax = 1;
    $maxPax = 999999;
    $isGroup = false;
    $basePrice = 0;

    $isGroup = get_field('pricing_by_group', $tour_id);


    if ($tour_id) {
        $minPax    = (int) (get_field('tier_1_min',  $tour_id) ?: 1);
        $maxPax    = (int) (get_field('tier_1_max',  $tour_id) ?: 999999);
        $basePrice = (float) (get_field('tier_1_price', $tour_id) ?: 0);
        $isGroup   = (bool)  get_field('pricing_by_group', $tour_id);
		
        if ($isGroup) {
            $minPax = get_field('tire-1_min_passenger', $tour_id);
            $maxPax = get_field('tire-3_max_passenger', $tour_id);
			$maxPax1 = get_field('tire-1_max_passenger', $tour_id);
			$maxPax2 = get_field('tire-2_max_passenger', $tour_id);
			$maxPax3 = get_field('tire-3_max_passenger', $tour_id);
			$duration = $item['duration'];
			
			$tier = null;
			
			if($maxPax1 >= $new_pax){
				$tier = get_field('tire-1', $tour_id);
			}elseif($maxPax2 >= $new_pax){
				$tier = get_field('tire-2', $tour_id);
			}elseif($maxPax3 >= $new_pax){
				$tier = get_field('tire-3', $tour_id);
			}
			
			fwc_log('[FWC_DEBUG] tier', $tier);
			
			if($duration == 'half_day_morning_4hours'){
				$basePrice = $tier['half_day_morning_4hours'];
			}elseif($duration == 'half_day_afternoon_4hours'){
				$basePrice = $tier['half_day_afternoon_4hours'];
			}elseif($duration == 'threeQ_day_6hours'){
				$basePrice = $tier['34_day_6hours'];
			}elseif($duration == 'full_day_8hours'){
				$basePrice = $tier['full_day_8hours'];
			}
			
			
        }
    }



    // Clamp passengers to allowed range
    $pax = max($minPax, min($new_pax, $maxPax));

    // Persist passengers & force qty=1
    $cart->cart_contents[$cart_item_key]['passenger_count'] = $pax;
    $cart->cart_contents[$cart_item_key]['quantity']        = 1;

    // Re-price line (group: flat price; per-person: base * pax)
    if (!empty($cart->cart_contents[$cart_item_key]['data'])) {
        $product = $cart->cart_contents[$cart_item_key]['data'];
        $line_unit_price = $isGroup ? $basePrice : ($basePrice * $pax);
        $product->set_price($line_unit_price);
    }

    // Recalculate
    $cart->calculate_totals();

    // Either return success and let JS reload, or return fragments to update without full reload.
    wp_send_json_success([
        'passengers' => $pax,
        // If you want fragments instead of a full reload, uncomment these lines:
        // 'fragments'  => apply_filters('woocommerce_add_to_cart_fragments', [
        //     'div.widget_shopping_cart_content' => WC_AJAX::get_refreshed_fragments()
        // ]),
    ]);
}


function get_all_tire_data()
{
    $data = [];

    // Loop through fields dynamically until there are no more
    for ($i = 1; $i <= 10; $i++) { // supports up to 10 tiers; increase if needed
        $tire_key = 'tire-' . $i;

        if (have_rows($tire_key)) {


            while (have_rows($tire_key)) {
                the_row();
                $row = [
                    'type' => $tire_key,
                    'min_passenger' => get_sub_field('min_passenger'),
                    'max_passenger' => get_sub_field('max_passenger'),
                    'half_day_morning_4hours' => get_sub_field('half_day_morning_4hours'),
                    'pick_up_time_4hours' => get_sub_field('pick_up_time_4hours'),
                    'half_day_afternoon_4hours' => get_sub_field('half_day_afternoon_4hours'),
                    'pick_up_time_afternoon' => get_sub_field('pick_up_time_afternoon'),
                    'threeQ_day_6hours' => get_sub_field('34_day_6hours'),
                    'pick_up_time_6hours' => get_sub_field('pick_up_time_6hours'),
                    'full_day_8hours' => get_sub_field('full_day_8hours'),
                    'pick_up_time_8hours' => get_sub_field('pick_up_time_8hours'),
                    'title_boat' => get_sub_field('title_boat'),
                    'boat_description' => get_sub_field('boat_description'),
                ];

                $data[] = $row;
            }
        } else {
            // Stop when no more tire-x rows exist
            break;
        }
    }

    return $data;
};

/**
 * Step 1: Log every time WooCommerce asks for an order item name.
 * We only LOG and return the original name unchanged.
 *
 * Enable logging (wp-config.php):
 *   define('WP_DEBUG', true);
 *   define('WP_DEBUG_LOG', true);
 *   define('WP_DEBUG_DISPLAY', false);
 * Log file: wp-content/debug.log
 */

if (!function_exists('fwc_log')) {
    function fwc_log($msg, $context = [])
    {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $payload = $context ? ' ' . wp_json_encode($context) : '';
            error_log('[FWC_DEBUG] ' . $msg . $payload);
        }
    }
}

/**
 * Build a concise snapshot of the $item so we can see what's available
 * without dumping huge objects into the log.
 */
function fwc_snapshot_order_item($item): array
{
    $snap = [
        'type'   => is_object($item) ? get_class($item) : gettype($item),
        'is_obj' => is_object($item),
    ];

    // Common WC getters (guarded)
    if (is_object($item)) {
        if (method_exists($item, 'get_id')) {
            $snap['item_id'] = (int) $item->get_id();
        }
        if (method_exists($item, 'get_name')) {
            $snap['item_name'] = (string) $item->get_name();
        }
        if (method_exists($item, 'get_product_id')) {
            $snap['product_id'] = (int) $item->get_product_id();
        }
        if (method_exists($item, 'get_variation_id')) {
            $snap['variation_id'] = (int) $item->get_variation_id();
        }

        // Product data (very light)
        if (method_exists($item, 'get_product')) {
            $p = $item->get_product();
            if ($p) {
                $snap['product_obj'] = [
                    'class'       => get_class($p),
                    'id'          => (int) $p->get_id(),
                    'is_variation' => method_exists($p, 'is_type') ? (bool) $p->is_type('variation') : null,
                    'parent_id'   => method_exists($p, 'get_parent_id') ? (int) $p->get_parent_id() : null,
                ];
            } else {
                $snap['product_obj'] = null;
            }
        }

        // Raw data payload (keys only, so logs stay small)
        if (method_exists($item, 'get_data')) {
            $data = (array) $item->get_data();
            $snap['data_keys'] = array_keys($data);
            // include a few common fields if present
            foreach (['name', 'product_id', 'variation_id', 'subtotal', 'total', 'quantity'] as $k) {
                if (isset($data[$k])) $snap['data_' . $k] = $data[$k];
            }
        }

        // A couple of meta lookups often used by customizers
        if (method_exists($item, 'get_meta')) {
            $snap['meta_product_id']   = (int) $item->get_meta('_product_id', true);
            $snap['meta_variation_id'] = (int) $item->get_meta('_variation_id', true);
        }
    } elseif (is_array($item)) {
        // Some previewers pass arrays
        $snap['array_keys']  = array_keys($item);
        $snap['product_id']  = isset($item['product_id']) ? (int) $item['product_id'] : null;
        $snap['variation_id'] = isset($item['variation_id']) ? (int) $item['variation_id'] : null;
        $snap['name']        = isset($item['name']) ? (string) $item['name'] : null;
    }

    return $snap;
}

/**
 * Hook 1: this is what Woo templates use to render the product name.
 * Priority 9 so we log before most other filters run.
 */
add_filter('woocommerce_order_item_name', function ($item_name, $item, $is_visible) {

    // Core identifiers
    $snapshot = [
        'item_id'        => $item->get_id(),               // Order item ID (in wp_woocommerce_order_items)
        'product_id'     => $item->get_product_id(),       // Underlying product/post ID
        'variation_id'   => $item->get_variation_id(),     // Variation ID if any
        'order_id'       => $item->get_order_id(),         // Parent order ID
        'name'           => $item->get_name(),             // Item name
        'quantity'       => $item->get_quantity(),
        'subtotal'       => $item->get_subtotal(),
        'total'          => $item->get_total(),
        'meta_data'      => [],
    ];

    // Include item meta key/value pairs
    foreach ($item->get_meta_data() as $meta) {
        $snapshot['meta_data'][$meta->key] = $meta->value;
		
		if($meta->key == "product_name_n" && !empty($meta->value)){
			return $meta->value;
		}
		
		if($meta->key == "Service Type" && !empty($meta->value)){
			return "Private Chef";
		}
    }

    // Log full snapshot
    fwc_log('[FWC_DEBUG] Order Item full dump', $snapshot);

    // Confirm product object
    $product = $item->get_product();
    if ($product) {
        fwc_log('[FWC_DEBUG] Product data', [
            'id'   => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
        ]);
    } else {
        fwc_log('[FWC_DEBUG] Product is null for item ID ' . $item->get_id());
    }

    return $item_name;
}, 9, 3);


/**
 * Hook 2 (optional): some builders skip the filter above and only call the loop action.
 * This logs each line item when the email/order items table is being built.
 */
add_action('woocommerce_email_order_items', function ($order) {
    if (!$order) return;
    foreach ($order->get_items('line_item') as $order_item_id => $order_item) {
        fwc_log('woocommerce_email_order_items → loop snapshot', [
            'order_item_id' => $order_item_id,
            'snapshot'      => fwc_snapshot_order_item($order_item),
        ]);
    }
}, 10, 1);






// Custom field display in checkout order summary for "food-menu" products

// add_action('woocommerce_checkout_create_order_line_item', 'custom_private_chef_field_to_checkout', 10, 4);
// function custom_private_chef_field_to_checkout($item, $cart_item_key, $values, $order) {
//     $product_id = $item->get_product_id();

//     // Check agar product "food-menu" category me hai
//     if (has_term('food-menu', 'product_cat', $product_id)) {

//         // Custom ACF ya meta field lo (agar chahiye)
//         $calories = get_field('calories', $product_id);

//         // Product title lo
//         $product = wc_get_product($product_id);
//         $product_title = $product ? $product->get_name() : '';

//         // Custom info save karo line item meta me
//         $item->add_meta_data('Private Chef', $product_title);

//         if (!empty($calories)) {
//             $item->add_meta_data('Calories', $calories . ' kcal');
//         }
//     }
// }

// Order summary me display karne ke liye (frontend)
add_filter('woocommerce_get_item_data', 'custom_private_chef_field_display', 10, 2);
function custom_private_chef_field_display($item_data, $cart_item) {
    $product_id = $cart_item['product_id'];

    // Check agar product "food-menu" category me hai
    if (has_term('food-menu', 'product_cat', $product_id)) {

        $product = wc_get_product($product_id);
        $product_title = $product ? $product->get_name() : '';

        $calories = get_field('calories', $product_id);

        // Custom line show karo order summary me
        $item_data[] = array(
            'name'  => 'Private Dining',
            'value' => esc_html($product_title)
        );
		
		$item_data[] = array(
            'name'  => 'Service Type',
            'value' => esc_html($cart_item['Service Type'])
        );
		
		$item_data[] = array(
            'name'  => 'Date',
            'value' => esc_html(date("F j, Y", strtotime($cart_item['Date'])))
        );
		$item_data[] = array(
            'name'  => 'Time',
            'value' => esc_html(date("h:i a", strtotime($cart_item['Date'] . ' '. $cart_item['Time'])))
        );
		
		

        if (!empty($calories)) {
 //           $item_data[] = array(
//                 'name'  => 'Calories',
//                 'value' => esc_html($calories) . ' kcal'
 //            );
        }
    }

    return $item_data;
}


add_action('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    fwc_log('woocommerce_add_cart_item_data → ', [
        'cart_item_data' => $cart_item_data,
		'gets' => $_GET,
		'posts' => $_POST,
		'requests' => $_REQUEST
    ]);
	
	if ( isset( $_REQUEST['service_type'] ) ) {
        $cart_item_data['Service Type'] = $_REQUEST['service_type'] == 'onsite_chef' ? "Onsite Chef" : ($_REQUEST['service_type'] == 'home_delivery' ? 'Home Delivery' : "");
		
		$cart_item_data["Private Dining"] = "Private Dining";
		$cart_item_data['product_name_n'] = "Private Dining";
    }
	
	if ( isset( $_REQUEST['location'] ) ) {
        $cart_item_data['Location'] = $_REQUEST['location'];
    }
	
	if ( isset( $_REQUEST['date'] ) ) {
        $cart_item_data['Date'] = $_REQUEST['date'];
    }
	
	if ( isset( $_REQUEST['time'] ) ) {
        $cart_item_data['Time'] = $_REQUEST['time'];
    }
	
	if(isset($cart_item_data['tour_id'])){
		$tour_id = $cart_item_data['tour_id'];

		$isGroup   = (bool)  get_field('pricing_by_group', $tour_id);

		if ($isGroup) {
			$maxPax1 = get_field('tire-1_max_passenger', $tour_id);
			$maxPax2 = get_field('tire-2_max_passenger', $tour_id);
			$maxPax3 = get_field('tire-3_max_passenger', $tour_id);
			$duration = $cart_item_data['duration'];

			$tier = null;

			if ($maxPax1 >= $cart_item_data['passenger_count']) {
				$tier = get_field('tire-1', $tour_id);
			} elseif ($maxPax2 >= $cart_item_data['passenger_count']) {
				$tier = get_field('tire-2', $tour_id);
			} elseif ($maxPax3 >= $cart_item_data['passenger_count']) {
				$tier = get_field('tire-3', $tour_id);
			}

			if ($duration == 'half_day_morning_4hours') {
				$durationName = "Half Day Morning 7am (4hours)";
			} elseif ($duration == 'half_day_afternoon_4hours') {
				$durationName = "Half Day Afternoon 1:30pm (4hours)";
			} elseif ($duration == 'threeQ_day_6hours') {
				$durationName = "3/4 Day (6hours)";
			} elseif ($duration == 'full_day_8hours') {
				$durationName = "Full Day (8hours)";
			}

			$productName = "Boat Charters";

		}else{
			$productName = "Experiences";
			$durationName = "";
		}

		$cart_item_data['product_name_n'] = $productName;
		$cart_item_data['duration_name_n'] = $durationName;
	}

    fwc_log('before returning  → woocommerce_add_cart_item_data', [
        'cart_item_data' => $cart_item_data,
    ]);
    return $cart_item_data;

}, 10, 3);

add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (isset($values['product_name_n'])) {
        $item->add_meta_data('product_name_n', $values['product_name_n']);
    }

    if (isset($values['duration_name_n'])) {
        $item->add_meta_data('duration_name_n', $values['duration_name_n']);
    }
	
	if (isset($values['Service Type'])) {
        $item->add_meta_data('Service Type', $values['Service Type']);
    }
	
	if (isset($values['Location'])) {
        $item->add_meta_data('Location', $values['Location']);
    }
	
	if (isset($values['Date'])) {
        $item->add_meta_data('Date', $values['Date']);
    }
	
	if (isset($values['Time'])) {
        $item->add_meta_data('Time', $values['Time']);
    }
}, 10, 4);



add_filter('woocommerce_order_item_get_formatted_meta_data', function ($formatted_meta, $item) {

    // 🔍 Check if the item has a "Service Type" meta key
    $has_service_type = false;

    foreach ($formatted_meta as $meta) {
        if (isset($meta->key) && $meta->key === 'Service Type') {
            $has_service_type = true;
            break;
        }
    }

    // 🔹 If Service Type exists, prepend Product Name to the meta list
    if ($has_service_type) {
        $product_name = $item->get_name();

        $product_name_meta = (object)[
            'key'           => 'product_name_manual',
            'value'         => $product_name,
            'display_key'   => __('Menu Item', 'your-textdomain'),
            'display_value' => $product_name,
        ];

        // Prepend Product Name at the top
        $formatted_meta = array_merge(['product_name_manual' => $product_name_meta], $formatted_meta);
    }

    // 🔹 Loop through and format or hide specific fields
    foreach ($formatted_meta as $id => $meta) {
        switch ($meta->key) {

            // Friendly labels
            case 'duration_name_n':
                $meta->display_key = __('Duration', 'your-textdomain');
                break;

            case 'Private Chef Product':
            case 'Private Chef':
			case 'Private Dining':
                $meta->display_key = __('Product', 'your-textdomain');
                break;

            // Format Date fields
            case 'Pickup Date':
            case 'Date':
                if (!empty($meta->value)) {
                    $timestamp = strtotime($meta->value);
                    if ($timestamp) {
                        $meta->display_value = date_i18n('F j, Y', $timestamp);
                    }
                }
                break;

            // Format Time fields
            case 'Time':
                if (!empty($meta->value)) {
                    $timestamp = strtotime($meta->value);
                    if ($timestamp) {
                        $meta->display_value = date_i18n('g:i A', $timestamp); // e.g. 5:02 PM
                    }
                }
                break;

            // Hide unwanted internal fields
            case 'Calories':
            case 'internal_note':
            case 'product_name_n':
            case 'debug_blob':
                unset($formatted_meta[$id]);
                break;
        }
    }

    return $formatted_meta;

}, 10, 2);


add_filter('woocommerce_get_order_item_totals', function ($totals, $order, $tax_display) {

    if (empty($totals) || !is_array($totals)) return $totals;

    $tax_key = null;
    $subtotal_key = null;

    // Find tax + subtotal by label (works even if keys are different)
    foreach ($totals as $key => $row) {
        $label = isset($row['label']) ? strtolower(trim(wp_strip_all_tags($row['label']))) : '';

        if (!$tax_key && strpos($label, 'tax') !== false) {
            $tax_key = $key;
        }

        if (!$subtotal_key && strpos($label, 'subtotal') !== false) {
            $subtotal_key = $key;
        }
    }

    // If no tax found, return
    if (!$tax_key || !isset($totals[$tax_key])) return $totals;

    $tax_row = $totals[$tax_key];
    unset($totals[$tax_key]);

    $new_totals = [];
    $inserted = false;

    foreach ($totals as $key => $row) {
        $new_totals[$key] = $row;

        // Insert tax right after subtotal (by detected key)
        if ($subtotal_key && $key === $subtotal_key) {
            $new_totals[$tax_key] = $tax_row;
            $inserted = true;
        }
    }

    // Fallback: if subtotal not found, put tax back at the end (so it never disappears)
    if (!$inserted) {
        $new_totals[$tax_key] = $tax_row;
    }

    return $new_totals;

}, 99, 3);










add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    /*
    =====================================
    PRIVATE CHEF CARD (POST ID 8213)
    =====================================
    */

    var wishlist = document.querySelector('.wishlist-button-wrap[item_id="8213"]');

    if(wishlist){

        var card = wishlist.closest('.card_loop');

        if(card){

            var smallTitle = card.querySelector(".card_content p");

            if(smallTitle){
                smallTitle.innerHTML = smallTitle.innerHTML.replace(
                    "Private Guided Tour",
                    "Private Dining Experience"
                );
            }

            var btn = card.querySelector(".custom-button");

            if(btn){
                btn.innerText = "Book Now";
                btn.href = "https://costaricatransfersandtours.com/private-dining/";
            }

            var ul = card.querySelector('.card_content ul');

            if(ul){

                ul.innerHTML = "";

                var lines = [
                    { text: "Private Chef Hire", icon: "icon icon-user" },
                    { text: "Home Delivery or Onsite Private", icon: "icon icon-home" },
                    { text: "Free Cancellation", icon: "icon icon-cancel" },
                    { text: "Great Menu Choice", icon: "fas fa-utensils" }
                ];

                lines.forEach(function(item){
                    var li = document.createElement("li");

                    li.innerHTML = `
                        <div class="icone">
                            <i class="${item.icon}"></i>
                        </div>
                        ${item.text}
                    `;

                    ul.appendChild(li);
                });
            }

            var price = card.querySelector(".price_se");

            if(price){
                price.innerHTML = "from <strong>$185";
            }

        }
    }

});
</script>
<?php
});









add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    var transportIDs = ["8199", "8216", "8217"];

    transportIDs.forEach(function(id){

        var wishlist = document.querySelector('.wishlist-button-wrap[item_id="'+id+'"]');

        if(!wishlist) return;

        var card = wishlist.closest('.card_loop');
        if(!card) return;

        var smallTitle = card.querySelector(".card_content p");

        if(smallTitle){
            smallTitle.innerHTML = smallTitle.innerHTML.replace(
                "Private Guided Tour",
                "Private Transportation"
            );
        }

        var btn = card.querySelector(".custom-button");

        if(btn){
            btn.innerText = "Book Now";
            btn.href = "https://costaricatransfersandtours.com/private-transfers-costa-rica";
        }

    });


    /*
    4 SEATER
    */

    var wishlist8199 = document.querySelector('.wishlist-button-wrap[item_id="8199"]');

    if(wishlist8199){

        var card8199 = wishlist8199.closest('.card_loop');
        var ul8199 = card8199.querySelector('.card_content ul');

        if(ul8199){

            ul8199.querySelectorAll("li").forEach(function(li){
                if(li.innerText.includes("Includes")){
                    li.remove();
                }
            });

            ul8199.insertAdjacentHTML("beforeend", `
                <li><div class="icone"><i class="icon icon-check"></i></div>Includes Bottled Water</li>
                <li><div class="icone"><i class="icon icon-star"></i></div>Premium Transfers</li>
                <li><div class="icone"><i class="fas fa-users"></i></div>Up to 4 Passengers</li>
                <li><div class="icone"><i class="icon icon-suitcase"></i></div>Max 4 suitcases</li>
            `);

            var price8199 = card8199.querySelector(".price_se");

            if(price8199){
                price8199.innerHTML = "from <strong>£150</strong>";
            }

        }
    }


    /*
    6 SEATER
    */

    var wishlist8216 = document.querySelector('.wishlist-button-wrap[item_id="8216"]');

    if(wishlist8216){

        var card8216 = wishlist8216.closest('.card_loop');
        var ul8216 = card8216.querySelector('.card_content ul');

        if(ul8216){

            ul8216.querySelectorAll("li").forEach(function(li){
                if(li.innerText.includes("Includes")){
                    li.remove();
                }
            });

            ul8216.insertAdjacentHTML("beforeend", `
                <li><div class="icone"><i class="icon icon-check"></i></div>Includes Bottled Water</li>
                <li><div class="icone"><i class="icon icon-star"></i></div>Premium Transfers</li>
                <li><div class="icone"><i class="fas fa-users"></i></div>Up to 6 Passengers</li>
                <li><div class="icone"><i class="icon icon-suitcase"></i></div>Max 6 suitcases</li>
            `);

            var price8216 = card8216.querySelector(".price_se");

            if(price8216){
                price8216.innerHTML = "from <strong>$200</strong>";
            }

        }
    }


    /*
    13 SEATER
    */

    var wishlist8217 = document.querySelector('.wishlist-button-wrap[item_id="8217"]');

    if(wishlist8217){

        var card8217 = wishlist8217.closest('.card_loop');
        var ul8217 = card8217.querySelector('.card_content ul');

        if(ul8217){

            ul8217.querySelectorAll("li").forEach(function(li){
                if(li.innerText.includes("Includes")){
                    li.remove();
                }
            });

            ul8217.insertAdjacentHTML("beforeend", `
                <li><div class="icone"><i class="icon icon-check"></i></div>Includes Bottled Water</li>
                <li><div class="icone"><i class="icon icon-star"></i></div>Premium Transfers</li>
                <li><div class="icone"><i class="fas fa-users"></i></div>Up to 13 Passengers</li>
                <li><div class="icone"><i class="icon icon-suitcase"></i></div>Max 13 suitcases</li>
            `);

            var price8217 = card8217.querySelector(".price_se");

            if(price8217){
                price8217.innerHTML = "from <strong>$290</strong>";
            }

        }
    }

});
</script>
<?php
});









add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    var headings = document.querySelectorAll(
        '.elementor-heading-title a[href="/tours/rincon-de-la-vieja-combo-tour/"]'
    );

    headings.forEach(function(el){
        el.textContent = "Rincon de la Vieja Costa Rica 4 in 1 Combo Adventure";
    });

});
</script>
<?php
});

add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    var exactHeading = document.querySelector(
        '.elementor-element[data-id="65d38da"] .elementor-heading-title a'
    );

    if(exactHeading){
        exactHeading.textContent =
        "Rio Celeste Cost Rica Blue River, Hiking & Waterfall Tour";
    }

});
</script>
<?php
});

add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    var paloHeading = document.querySelector(
        '.elementor-element[data-id="0598cb1"] .elementor-heading-title a'
    );

    if(paloHeading){
        paloHeading.textContent =
        "Palo Verde Costa Rica Jungle River Cruise";
    }

});
</script>
<?php
});

add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    var miravallesHeading = document.querySelector(
        '.elementor-element[data-id="9576200"] .elementor-heading-title a'
    );

    if(miravallesHeading){
        miravallesHeading.textContent =
        "Miravalles Crater Costa Rica Hike & Waterfall Guided Tour";
    }

});
</script>
<?php
});














add_action('wp_ajax_get_tour_price', 'get_tour_price');
add_action('wp_ajax_nopriv_get_tour_price', 'get_tour_price');

function get_tour_price() {

    $post_id = intval($_POST['post_id']);

    if(!$post_id){
        wp_send_json_error();
    }

    // ACF FIELD NAME (IMPORTANT)
    $price = get_field('tier_1_price', $post_id);

    if(!$price){
        wp_send_json_error();
    }

    wp_send_json_success([
        'price' => '$' . $price
    ]);
}
add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    function loadACFPrices(){

        document.querySelectorAll('.card_loop').forEach(function(card){

            var priceWrap = card.querySelector('.price_se');
            if(!priceWrap) return;

            var strong = priceWrap.querySelector('strong');
            if(!strong) return;

            // skip if already has price
            if(strong.innerText.replace(/\s+/g,'').trim() !== "") return;

            var wishlist = card.querySelector('.wishlist-button-wrap');
            if(!wishlist) return;

            var postID = wishlist.getAttribute("item_id");
            if(!postID) return;

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_tour_price&post_id=' + postID
            })
            .then(res => res.json())
            .then(res => {

                if(res.success && res.data.price){
                    strong.innerText = res.data.price;

// ensure "per person" text
priceWrap.innerHTML = 'from <strong>' + res.data.price + '</strong> per person';
                }

            })
            .catch(()=>{});

        });

    }

    loadACFPrices();
    setTimeout(loadACFPrices, 1000);
    setTimeout(loadACFPrices, 2000);

});
</script>
<?php
});










add_action('wp_footer', function () {
?>
<script>
document.addEventListener("DOMContentLoaded", function(){

    document.querySelectorAll('.card_loop').forEach(function(card){

        var info = card.querySelector('.card_content p');
        var img = card.querySelector('.card_img');
        var heart = card.querySelector('.wishlist-button-wrap');

        if(!info || !img || !heart) return;

        // move text into image
        img.appendChild(info);

        // get heart position
        var heartTop = heart.offsetTop;

        // style correctly (LEFT + aligned with heart)
        info.style.position = "absolute";
        info.style.top = heartTop + "px"; // same height as heart
        info.style.left = "15px"; // left aligned
        info.style.right = "auto";
        info.style.margin = "0";
        info.style.color = "#fff";
        info.style.fontSize = "13px";
        info.style.lineHeight = "1.3";
        info.style.textAlign = "left";
        info.style.zIndex = "9";

    });

});
</script>
<?php
});





add_filter('the_title', function($title) {
    if (!is_admin()) {
        $title = str_replace('SUV', '', $title);
    }
    return $title;
});










//=======================================================================================================================
//=======================================================================================================================
//================================================= n8n rest apis =======================================================
//==================================================== Start ============================================================
//=======================================================================================================================



// TEMP DEBUG — remove after fix
add_action('rest_api_init', function() {
    register_rest_route('crtt/v1', '/tour-debug/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'crtt_debug_tour_plain',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_debug_tour_plain($request) {
    $id   = $request['id'];
    $post = get_post($id);
    if (!$post) return new WP_Error('not_found', 'Not found', ['status' => 404]);

    $raw = apply_filters('the_content', $post->post_content);

    // Replicate exact pipeline
    $html = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "\n• $1\n", $html);
    $html = preg_replace('/<\/?(p|br|div|h[1-6]|ul|ol|section|article)[^>]*>/i', "\n", $html);
    $html = wp_strip_all_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/[ \t]+/', ' ', $html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    $plain = trim($html);

    // Collapse bullets
    $lines = explode("\n", $plain);
    $out = []; $i = 0; $count = count($lines);
    while ($i < $count) {
        $line = trim($lines[$i]);
        if (preg_match('/^[•\x{2022}?]$/u', $line)) {
            $j = $i + 1;
            while ($j < $count && trim($lines[$j]) === '') $j++;
            $value = isset($lines[$j]) ? trim($lines[$j]) : '';
            if ($value !== '') { $out[] = '• ' . $value; $i = $j + 1; continue; }
        }
        $out[] = $lines[$i]; $i++;
    }
    $plain = implode("\n", $out);

    // Find the boat section specifically
    $boat_start = stripos($plain, 'Boat');
    $boat_snippet = $boat_start !== false
        ? substr($plain, max(0, $boat_start - 50), 400)
        : 'BOAT NOT FOUND IN PLAIN TEXT';

    return rest_ensure_response([
        'plain_text'   => $plain,
        'boat_snippet' => $boat_snippet,
    ]);
}










//=======================================================================================================================
//===================================== Get All Tours, Transportation and Dining ========================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_all_tours',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_all_tours($request) {

    $tours = get_posts([
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    $data = array_map(function ($tour) {

        $id         = $tour->ID;
        $all_fields = get_fields($id);

        // ── ACF shorthand ─────────────────────────────────────────────────────
        $acf = function($key) use ($all_fields) {
            return isset($all_fields[$key]) ? $all_fields[$key] : null;
        };

        // ── Pricing fields ────────────────────────────────────────────────────
        $pricing_by_group = (bool) $acf('pricing_by_group');
        $tier_1_min       = $acf('tier_1_min');
        $tier_1_max       = $acf('tier_1_max');
        $tier_1_price     = $acf('tier_1_price');
        $tier_2_min       = $acf('tier_2_min');
        $tier_2_max       = $acf('tier_2_max');
        $tier_2_price     = $acf('tier_2_price');
        $tier_3_min       = $acf('tier_3_min');
        $tier_3_max       = $acf('tier_3_max');
        $tier_3_price     = $acf('tier_3_price');

        // ── Boat tier detection ───────────────────────────────────────────────
        $boat_tier_count = 0;
        foreach (crtt_get_boat_tier_keys($id) as $tk) {
            $td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
            if (!empty($td) && (!empty($td['min_passenger']) || !empty($td['half_day_morning_4hours']))) {
                $boat_tier_count++;
            }
        }

        // ── Categories ────────────────────────────────────────────────────────
        $categories = array_map(
            function($c) { return $c->name; },
            wp_get_post_terms($id, 'tour_category')
        );

        // ── Service type detection ────────────────────────────────────────────
        $title_lower     = strtolower($tour->post_title);
        $offered_in      = trim($acf('offered_in') !== null ? $acf('offered_in') : '');
        $group_or_person = trim($acf('group_or_person') !== null ? $acf('group_or_person') : '');

        if ($boat_tier_count > 0 || $pricing_by_group) {
            $service_type = 'boat_charter';
        } elseif (
            strpos($title_lower, 'transportation') !== false ||
            strpos($title_lower, 'transfer') !== false ||
            strpos($title_lower, 'shuttle') !== false
        ) {
            $service_type = 'transportation';
        } elseif (
            strpos($title_lower, 'chef') !== false ||
            strpos($title_lower, 'dining') !== false ||
            strpos($title_lower, 'dinner') !== false ||
            in_array('Dining', $categories, true)
        ) {
            $service_type = 'dining';
        } else {
            $service_type = 'tour';
        }

        // ── Capacity (transportation only) ────────────────────────────────────
        $capacity = '';
        if ($service_type === 'transportation') {
            $gop = trim($acf('group_or_person') !== null ? $acf('group_or_person') : '');
            if (preg_match('/(\d+)/u', $gop, $m)) {
                $capacity = (int) $m[1];
            }
        }

        // ── Normalise pickup_time ─────────────────────────────────────────────
        $pickup_time_raw = trim($acf('pickup_time') !== null ? $acf('pickup_time') : '');
        if ($service_type === 'transportation' || $service_type === 'dining') {
            $pickup_time = '';
        } else {
            $pickup_time = $pickup_time_raw;
        }

        // ── Normalise duration ────────────────────────────────────────────────
        $duration_raw = trim($acf('duration') !== null ? $acf('duration') : '');
        if ($service_type === 'transportation') {
            $duration = $duration_raw ? $duration_raw . ' hrs drive time' : '';
        } elseif ($service_type === 'dining') {
            $duration = '';
        } else {
            $duration = $duration_raw;
        }
		
		
		// ── Durations list (boat charter only) ──────────────────────────────────
		$durations = [];
		if ($boat_tier_count > 0) {
    		$duration_fields = [
        		'half_day_morning_4hours'   => ['label' => 'Half Day Morning Start - 7am (4 Hours)',   'time_key' => 'pick_up_time_4hours',   'key' => 'half_day_morning_4hours'],
        		'half_day_afternoon_4hours' => ['label' => 'Half Day Afternoon Start - 1:30pm (4 Hours)', 'time_key' => 'pick_up_time_afternoon', 'key' => 'half_day_afternoon_4hours'],
        		'34_day_6hours'             => ['label' => '3/4 Day 7am (6 Hours)',                     'time_key' => 'pick_up_time_6hours',   'key' => 'threeQ_day_6hours'],
        		'full_day_8hours'           => ['label' => 'Full Day 7am (8 Hours)',                    'time_key' => 'pick_up_time_8hours',   'key' => 'full_day_8hours'],
    		];
    		$duration_map = []; // label => ['pickup_time' => ..., 'duration_key' => ..., 'prices' => [...]]
    		foreach (crtt_get_boat_tier_keys($id) as $tk) {
        		$td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
        		if (empty($td)) continue;
		        foreach ($duration_fields as $field => $info) {
		            if (!empty($td[$field])) {
		                $label = $info['label'];
		                if (!isset($duration_map[$label])) {
		                    $duration_map[$label] = [
		                        'pickup_time'  => trim(isset($td[$info['time_key']]) ? $td[$info['time_key']] : ''),
		                        'duration_key' => $info['key'],
		                        'prices'       => [],
		                    ];
		                }
		                $duration_map[$label]['prices'][] = $td[$field];
		            }
		        }
		    }
		    foreach ($duration_map as $label => $info) {
		        $durations[] = [
		            'label'             => $label,
		            'duration_key'      => $info['duration_key'],
		            'pickup_time'       => $info['pickup_time'],
		            'price_starts_from' => min($info['prices']),
		        ];
		    }
		}
		
		

        // ── Pricing summary ───────────────────────────────────────────────────
        $pricing_summary = [];

        if ($boat_tier_count > 0) {
            // Boat charter — one row per boat tier
            foreach (crtt_get_boat_tier_keys($id) as $tk) {
                $td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
                if (empty($td)) continue;

                $prices = array_filter([
                    isset($td['half_day_morning_4hours'])   ? $td['half_day_morning_4hours']   : null,
                    isset($td['half_day_afternoon_4hours']) ? $td['half_day_afternoon_4hours'] : null,
                    isset($td['34_day_6hours'])             ? $td['34_day_6hours']             : null,
                    isset($td['full_day_8hours'])           ? $td['full_day_8hours']           : null,
                ]);

                if (empty($prices)) continue;

                $pricing_summary[] = [
                    'min_pax'    => isset($td['min_passenger']) ? $td['min_passenger'] : '',
                    'max_pax'    => isset($td['max_passenger']) ? $td['max_passenger'] : '',
                    'boat'       => trim(wp_strip_all_tags(isset($td['title_boat']) ? $td['title_boat'] : '')),
                    'price_from' => min($prices),
                ];
            }

        } elseif ($service_type === 'transportation') {
            // Transportation — capacity + flat price
            foreach ([
                ['price' => $tier_1_price],
                ['price' => $tier_2_price],
                ['price' => $tier_3_price],
            ] as $t) {
                if (!empty($t['price'])) {
                    $pricing_summary[] = [
                        'capacity' => $capacity,
                        'price'    => $t['price'],
                    ];
                }
            }

        } elseif ($service_type === 'dining') {
            // Dining — guest range + price
            foreach ([
                ['min' => $tier_1_min, 'max' => $tier_1_max, 'price' => $tier_1_price],
                ['min' => $tier_2_min, 'max' => $tier_2_max, 'price' => $tier_2_price],
                ['min' => $tier_3_min, 'max' => $tier_3_max, 'price' => $tier_3_price],
            ] as $t) {
                if (!empty($t['price'])) {
                    $pricing_summary[] = [
                        'min_guests' => !empty($t['min']) ? $t['min'] : '1',
                        'max_guests' => !empty($t['max']) ? $t['max'] : '',
                        'price'      => $t['price'],
                    ];
                }
            }

        } else {
            // Standard tour — passenger range + price per person
            foreach ([
                ['min' => $tier_1_min, 'max' => $tier_1_max, 'price' => $tier_1_price],
                ['min' => $tier_2_min, 'max' => $tier_2_max, 'price' => $tier_2_price],
                ['min' => $tier_3_min, 'max' => $tier_3_max, 'price' => $tier_3_price],
            ] as $t) {
                if (!empty($t['price'])) {
                    $pricing_summary[] = [
                        'min_pax' => $t['min'],
                        'max_pax' => $t['max'],
                        'price'   => $t['price'],
                    ];
                }
            }
        }

		// ── Response ──────────────────────────────────────────────────────────
        $item = [
            // Identity
            'id'    => $id,
            'title' => html_entity_decode($tour->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug'  => $tour->post_name,
            'link'  => get_permalink($id),

            // Routing
            'service_type' => $service_type,
            'categories'   => $categories,
            'region'       => $acf('region'),
            'offered_in'   => $offered_in,

            // Availability & badges
            'available'    => (bool) $acf('enable_availability'),
            'badge'        => $acf('badge') !== null ? $acf('badge') : '',
            'cancellation' => $acf('cancellation') !== null ? $acf('cancellation') : '',

            // Scheduling
            'duration'    => $duration,
            'pickup_time' => $pickup_time,
            'durations'   => $durations,

            // Pricing
            'pricing_by_group' => $pricing_by_group,
            'group_or_person'  => $group_or_person,
            'boat_tier_count'  => $boat_tier_count,
            'vehicle_capacity' => $service_type === 'transportation' ? $capacity : '',
            'pricing_summary'  => $pricing_summary,
        ];

        if ($service_type === 'boat_charter') {
            $item['duration_hour'] = $item['duration'];
            unset($item['duration']);

            // Hidden for boat charters — durations[] shown instead (do not delete pricing_summary computation above)
            unset($item['pricing_summary']);
        }

        return $item;

    }, $tours);

    return rest_ensure_response($data);
}







//================================================================================================================================================================================
//================================================================================================================================================================================







//=======================================================================================================================
//=============================================== Helper: Get ACF Fields ================================================
//=======================================================================================================================
// Shared helper to extract ACF field value safely
function crtt_acf($all_fields, $key) {
    return isset($all_fields[$key]) ? $all_fields[$key] : null;
}





//=======================================================================================================================
//======================================= Helper: Allowed Boat Tier Keys per Tour ========================================
//=======================================================================================================================
// Only these tour IDs are allowed to show all 3 boat tiers (multi-boat fishing charters).
// Every other boat_charter only shows tire-1 (Queen Mary 2 - 23ft).
function crtt_get_boat_tier_keys($tour_id) {
    $multi_boat_tour_ids = [3207]; // Sport Fishing Private Boat Charter

    if (in_array((int) $tour_id, $multi_boat_tour_ids, true)) {
        return ['tire-1', 'tire-2', 'tire-3'];
    }

    return ['tire-1'];
}




//=======================================================================================================================
//============================================= Helper: Build Service Item ==============================================
//=======================================================================================================================
// Shared builder — same response shape as /services
function crtt_build_service_item($tour) {
    $id         = $tour->ID;
    $all_fields = get_fields($id);

    $acf = function($key) use ($all_fields) {
        return crtt_acf($all_fields, $key);
    };

    // ── Pricing fields ────────────────────────────────────────────────────
    $pricing_by_group = (bool) $acf('pricing_by_group');
    $tier_1_min       = $acf('tier_1_min');
    $tier_1_max       = $acf('tier_1_max');
    $tier_1_price     = $acf('tier_1_price');
    $tier_2_min       = $acf('tier_2_min');
    $tier_2_max       = $acf('tier_2_max');
    $tier_2_price     = $acf('tier_2_price');
    $tier_3_min       = $acf('tier_3_min');
    $tier_3_max       = $acf('tier_3_max');
    $tier_3_price     = $acf('tier_3_price');

    // ── Boat tier detection ───────────────────────────────────────────────
    $boat_tier_count = 0;
    foreach (crtt_get_boat_tier_keys($id) as $tk) {
        $td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
        if (!empty($td) && (!empty($td['min_passenger']) || !empty($td['half_day_morning_4hours']))) {
            $boat_tier_count++;
        }
    }
	
	// ── Durations list (boat charter only) ──────────────────────────────────
    $durations = [];
    if ($boat_tier_count > 0) {
        $duration_fields = [
        		'half_day_morning_4hours'   => ['label' => 'Half Day Morning Start - 7am (4 Hours)',   'time_key' => 'pick_up_time_4hours',   'key' => 'half_day_morning_4hours'],
        		'half_day_afternoon_4hours' => ['label' => 'Half Day Afternoon Start - 1:30pm (4 Hours)', 'time_key' => 'pick_up_time_afternoon', 'key' => 'half_day_afternoon_4hours'],
        		'34_day_6hours'             => ['label' => '3/4 Day 7am (6 Hours)',                     'time_key' => 'pick_up_time_6hours',   'key' => 'threeQ_day_6hours'],
        		'full_day_8hours'           => ['label' => 'Full Day 7am (8 Hours)',                    'time_key' => 'pick_up_time_8hours',   'key' => 'full_day_8hours'],
    	];
    	$duration_map = []; // label => ['pickup_time' => ..., 'duration_key' => ..., 'prices' => [...]]
    	foreach (crtt_get_boat_tier_keys($id) as $tk) {
        	$td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
        	if (empty($td)) continue;
		    foreach ($duration_fields as $field => $info) {
		        if (!empty($td[$field])) {
		            $label = $info['label'];
		            if (!isset($duration_map[$label])) {
		                $duration_map[$label] = [
		                    'pickup_time'  => trim(isset($td[$info['time_key']]) ? $td[$info['time_key']] : ''),
		                    'duration_key' => $info['key'],
		                    'prices'       => [],
		                ];
		            }
		            $duration_map[$label]['prices'][] = $td[$field];
		        }
		    }
		}
	    foreach ($duration_map as $label => $info) {
	        $durations[] = [
		        'label'             => $label,
		        'duration_key'      => $info['duration_key'],
		        'pickup_time'       => $info['pickup_time'],
		        'price_starts_from' => min($info['prices']),
		    ];
		}
    }

    // ── Categories ────────────────────────────────────────────────────────
    $categories = array_map(
        function($c) { return $c->name; },
        wp_get_post_terms($id, 'tour_category')
    );

    // ── Service type detection ────────────────────────────────────────────
    $title_lower     = strtolower($tour->post_title);
    $offered_in      = trim($acf('offered_in') !== null ? $acf('offered_in') : '');
    $group_or_person = trim($acf('group_or_person') !== null ? $acf('group_or_person') : '');

    if ($boat_tier_count > 0 || $pricing_by_group) {
        $service_type = 'boat_charter';
    } elseif (
        strpos($title_lower, 'transportation') !== false ||
        strpos($title_lower, 'transfer') !== false ||
        strpos($title_lower, 'shuttle') !== false
    ) {
        $service_type = 'transportation';
    } elseif (
        strpos($title_lower, 'chef') !== false ||
        strpos($title_lower, 'dining') !== false ||
        strpos($title_lower, 'dinner') !== false ||
        in_array('Dining', $categories, true)
    ) {
        $service_type = 'dining';
    } else {
        $service_type = 'tour';
    }

    // ── Capacity (transportation only) ────────────────────────────────────
    $capacity = '';
    if ($service_type === 'transportation') {
        $gop = trim($acf('group_or_person') !== null ? $acf('group_or_person') : '');
        if (preg_match('/(\d+)/u', $gop, $m)) {
            $capacity = (int) $m[1];
        }
    }

    // ── Normalise pickup_time ─────────────────────────────────────────────
    $pickup_time_raw = trim($acf('pickup_time') !== null ? $acf('pickup_time') : '');
    if ($service_type === 'transportation' || $service_type === 'dining') {
        $pickup_time = '';
    } else {
        $pickup_time = $pickup_time_raw;
    }

    // ── Normalise duration ────────────────────────────────────────────────
    $duration_raw = trim($acf('duration') !== null ? $acf('duration') : '');
    if ($service_type === 'transportation') {
        $duration = $duration_raw ? $duration_raw . ' hrs drive time' : '';
    } elseif ($service_type === 'dining') {
        $duration = '';
    } else {
        $duration = $duration_raw;
    }

    // ── Pricing summary ───────────────────────────────────────────────────
    $pricing_summary = [];

    if ($boat_tier_count > 0) {
        // Superseded by durations[] for boat charters — pricing_summary no longer populated (do not delete)
        // foreach (crtt_get_boat_tier_keys($id) as $tk) {
        //     $td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
        //     if (empty($td)) continue;
        //
        //     $prices = array_filter([
        //         isset($td['half_day_morning_4hours'])   ? $td['half_day_morning_4hours']   : null,
        //         isset($td['half_day_afternoon_4hours']) ? $td['half_day_afternoon_4hours'] : null,
        //         isset($td['34_day_6hours'])             ? $td['34_day_6hours']             : null,
        //         isset($td['full_day_8hours'])           ? $td['full_day_8hours']           : null,
        //     ]);
        //
        //     if (empty($prices)) continue;
        //
        //     $pricing_summary[] = [
        //         'min_pax'    => isset($td['min_passenger']) ? $td['min_passenger'] : '',
        //         'max_pax'    => isset($td['max_passenger']) ? $td['max_passenger'] : '',
        //         'boat'       => trim(wp_strip_all_tags(isset($td['title_boat']) ? $td['title_boat'] : '')),
        //         'price_from' => min($prices),
        //     ];
        // }

    } elseif ($service_type === 'transportation') {
        foreach ([
            ['price' => $tier_1_price],
            ['price' => $tier_2_price],
            ['price' => $tier_3_price],
        ] as $t) {
            if (!empty($t['price'])) {
                $pricing_summary[] = [
                    'capacity' => $capacity,
                    'price'    => $t['price'],
                ];
            }
        }

    } elseif ($service_type === 'dining') {
        foreach ([
            ['min' => $tier_1_min, 'max' => $tier_1_max, 'price' => $tier_1_price],
            ['min' => $tier_2_min, 'max' => $tier_2_max, 'price' => $tier_2_price],
            ['min' => $tier_3_min, 'max' => $tier_3_max, 'price' => $tier_3_price],
        ] as $t) {
            if (!empty($t['price'])) {
                $pricing_summary[] = [
                    'min_guests' => !empty($t['min']) ? $t['min'] : '1',
                    'max_guests' => !empty($t['max']) ? $t['max'] : '',
                    'price'      => $t['price'],
                ];
            }
        }

    } else {
        foreach ([
            ['min' => $tier_1_min, 'max' => $tier_1_max, 'price' => $tier_1_price],
            ['min' => $tier_2_min, 'max' => $tier_2_max, 'price' => $tier_2_price],
            ['min' => $tier_3_min, 'max' => $tier_3_max, 'price' => $tier_3_price],
        ] as $t) {
            if (!empty($t['price'])) {
                $pricing_summary[] = [
                    'min_pax' => $t['min'],
                    'max_pax' => $t['max'],
                    'price'   => $t['price'],
                ];
            }
        }
    }

    // ── Response ──────────────────────────────────────────────────────────
    $item = [
        'id'    => $id,
        'title' => html_entity_decode($tour->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'slug'  => $tour->post_name,
        'link'  => get_permalink($id),

        'service_type' => $service_type,
        'categories'   => $categories,
        'region'       => $acf('region'),
        'offered_in'   => $offered_in,

        'available'    => (bool) $acf('enable_availability'),
        'badge'        => $acf('badge') !== null ? $acf('badge') : '',
        'cancellation' => $acf('cancellation') !== null ? $acf('cancellation') : '',

        'duration'    => $duration,
        'pickup_time' => $pickup_time,
        'durations'   => $durations,

        'pricing_by_group' => $pricing_by_group,
        'group_or_person'  => $group_or_person,
        'boat_tier_count'  => $boat_tier_count,
        'vehicle_capacity' => $service_type === 'transportation' ? $capacity : '',
        'pricing_summary'  => $pricing_summary,
    ];

    if ($service_type === 'boat_charter') {
        // Hidden for boat charters — durations[] shown instead (do not delete pricing_summary computation above)
        unset($item['pricing_summary']);
    }

    return $item;
}






//=======================================================================================================================
//=========================================== 1. GET /crtt/v1/services/tour ============================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/tour', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_tours',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_tours($request) {
    $posts = get_posts([
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    $data = [];
    foreach ($posts as $tour) {
        $item = crtt_build_service_item($tour);
        if ($item['service_type'] === 'tour') {
            $data[] = $item;
        }
    }

    return rest_ensure_response($data);
}






//=======================================================================================================================
//=========================================== 2. GET /crtt/v1/services/boat_charter =====================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/boat_charter', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_boat_charters',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_boat_charters($request) {
    $posts = get_posts([
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    $data = [];
    foreach ($posts as $tour) {
        $item = crtt_build_service_item($tour);
        if ($item['service_type'] === 'boat_charter') {
            $data[] = $item;
        }
    }

    return rest_ensure_response($data);
}






//=======================================================================================================================
//========================================== 3. GET /crtt/v1/services/transportation ====================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/transportation', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_transportation',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_transportation($request) {
    $posts = get_posts([
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    $data = [];
    foreach ($posts as $tour) {
        $item = crtt_build_service_item($tour);
        if ($item['service_type'] === 'transportation') {
            $data[] = $item;
        }
    }

    return rest_ensure_response($data);
}






//=======================================================================================================================
//============================================= 4. GET /crtt/v1/services/dining =========================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/dining', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_dining',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_dining($request) {
    $posts = get_posts([
        'post_type'      => 'tour',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ]);

    $data = [];
    foreach ($posts as $tour) {
        $item = crtt_build_service_item($tour);
        if ($item['service_type'] === 'dining') {
            $data[] = $item;
        }
    }

    return rest_ensure_response($data);
}



//================================================================================================================================================================================
//================================================================================================================================================================================




//=======================================================================================================================
//=============================================== Helper: crtt_detail_clean =============================================
//=======================================================================================================================
function crtt_detail_clean($text) {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $text));
}

//=======================================================================================================================
//============================================ Helper: crtt_detail_html_to_plain ========================================
//=======================================================================================================================
function crtt_detail_html_to_plain($html) {
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "\n• $1\n", $html);
    $html = preg_replace('/<\/?(p|br|div|h[1-6]|ul|ol|section|article)[^>]*>/i', "\n", $html);
    $html = wp_strip_all_tags($html);
    $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $html = preg_replace('/[ \t]+/', ' ', $html);
    $html = preg_replace('/\n{3,}/', "\n\n", $html);
    return trim($html);
}

//=======================================================================================================================
//========================================== Helper: crtt_detail_collapse_bullets =======================================
//=======================================================================================================================
function crtt_detail_collapse_bullets($text) {
    $lines = explode("\n", $text);
    $out   = [];
    $i     = 0;
    $count = count($lines);

    while ($i < $count) {
        $line = trim($lines[$i]);
        if (preg_match('/^[•\x{2022}?]$/u', $line)) {
            $j = $i + 1;
            while ($j < $count && trim($lines[$j]) === '') {
                $j++;
            }
            $value = isset($lines[$j]) ? trim($lines[$j]) : '';
            if ($value !== '') {
                $out[] = '• ' . $value;
                $i = $j + 1;
                continue;
            }
        }
        $out[] = $lines[$i];
        $i++;
    }

    return implode("\n", $out);
}

//=======================================================================================================================
//============================================ Helper: crtt_detail_parse_sections =======================================
//=======================================================================================================================
function crtt_detail_parse_sections($plain) {
    $header_map = [
        'overview'                                      => 'overview',
        'highlights'                                    => 'highlights',
        'fishing\s+license\s+details?'                  => 'fishing_license',
        'what\s+to\s+bring'                             => 'what_to_bring',
        'trip\s+info(?:rmation)?'                       => 'trip_info_raw',
        "what\u{2019}?s\s+included|what'?s\s+included" => 'included_raw',
        "what\u{2019}?s\s+excluded|what'?s\s+excluded" => 'excluded_raw',
    ];

    $header_pattern = '/^[ \t]*(' . implode('|', array_keys($header_map)) . ')[ \t]*:?[ \t]*$/im';
    $chunks   = preg_split($header_pattern, $plain, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    $sections = [];
    $current  = 'preamble';

    foreach ($chunks as $chunk) {
        $chunk_trimmed = trim($chunk);
        if ($chunk_trimmed === '') continue;

        $matched_key = null;
        foreach ($header_map as $regex => $key) {
            if (preg_match('/^(?:' . $regex . ')$/iu', $chunk_trimmed)) {
                $matched_key = $key;
                break;
            }
        }

        if ($matched_key !== null) {
            $current = $matched_key;
        } else {
            $sections[$current] = isset($sections[$current])
                ? $sections[$current] . "\n" . $chunk_trimmed
                : $chunk_trimmed;
        }
    }

    return $sections;
}

//=======================================================================================================================
//============================================ Helper: crtt_detail_extract_bullets =======================================
//=======================================================================================================================
function crtt_detail_extract_bullets($text) {
    $lines = explode("\n", $text);
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^[•\x{2022}?\*\-]\s+(.+)$/u', $line, $m)) {
            $item = trim($m[1]);
            if ($item !== '') $items[] = $item;
        }
    }
    return $items;
}

//=======================================================================================================================
//=========================================== Helper: crtt_detail_section_to_prose ======================================
//=======================================================================================================================
function crtt_detail_section_to_prose($text) {
    $text = preg_replace('/^[•\x{2022}?\*\-]\s*/mu', '', $text);
    return crtt_detail_clean($text);
}

//=======================================================================================================================
//============================================== Helper: crtt_detail_parse_tier =========================================
//=======================================================================================================================
function crtt_detail_parse_tier($tier) {
    if (empty($tier)) return [];

    $durations = [];

    if (!empty($tier['half_day_morning_4hours']))
        $durations[] = [
            'label'        => 'Half Day Morning Start - 7am (4 Hours)',
            'duration_key' => 'half_day_morning_4hours',
            'price'        => $tier['half_day_morning_4hours'],
            'pickup_time'  => trim(isset($tier['pick_up_time_4hours']) ? $tier['pick_up_time_4hours'] : ''),
        ];

    if (!empty($tier['half_day_afternoon_4hours']))
        $durations[] = [
            'label'        => 'Half Day Afternoon Start - 1:30pm (4 Hours)',
            'duration_key' => 'half_day_afternoon_4hours',
            'price'        => $tier['half_day_afternoon_4hours'],
            'pickup_time'  => trim(isset($tier['pick_up_time_afternoon']) ? $tier['pick_up_time_afternoon'] : ''),
        ];

    if (!empty($tier['34_day_6hours']))
        $durations[] = [
            'label'        => '3/4 Day 7am (6 Hours)',
            'duration_key' => 'threeQ_day_6hours',
            'price'        => $tier['34_day_6hours'],
            'pickup_time'  => trim(isset($tier['pick_up_time_6hours']) ? $tier['pick_up_time_6hours'] : ''),
        ];

    if (!empty($tier['full_day_8hours']))
        $durations[] = [
            'label'        => 'Full Day 7am (8 Hours)',
            'duration_key' => 'full_day_8hours',
            'price'        => $tier['full_day_8hours'],
            'pickup_time'  => trim(isset($tier['pick_up_time_8hours']) ? $tier['pick_up_time_8hours'] : ''),
        ];

    return [
        'min_passenger'    => isset($tier['min_passenger'])   ? $tier['min_passenger']   : '',
        'max_passenger'    => isset($tier['max_passenger'])   ? $tier['max_passenger']   : '',
        'boat_name'        => crtt_detail_clean(isset($tier['title_boat'])       ? $tier['title_boat']       : ''),
        'boat_description' => crtt_detail_clean(wp_strip_all_tags(isset($tier['boat_description']) ? $tier['boat_description'] : '')),
        'durations'        => $durations,
    ];
}

//=======================================================================================================================
//============================================ Helper: crtt_build_detail_response =======================================
//=======================================================================================================================
function crtt_build_detail_response($id, $post) {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        error_reporting(0);
    }

    // ── Parse post body ───────────────────────────────────────────────────────
    $raw_content = apply_filters('the_content', $post->post_content);
    $plain       = crtt_detail_html_to_plain($raw_content);
    $plain       = crtt_detail_collapse_bullets($plain);
    $sections    = crtt_detail_parse_sections($plain);

    // ── ACF ───────────────────────────────────────────────────────────────────
    $all_fields = get_fields($id);
    $acf = function($key) use ($all_fields) {
        return isset($all_fields[$key]) ? $all_fields[$key] : null;
    };

    // ── Categories ────────────────────────────────────────────────────────────
    $categories = array_map(
        function($c) { return $c->name; },
        wp_get_post_terms($id, 'tour_category')
    );

    // ── Service type detection ────────────────────────────────────────────────
    $title_lower      = strtolower(get_the_title($id));
    $pricing_by_group = (bool) $acf('pricing_by_group');

    $boat_tier_count = 0;
    foreach (crtt_get_boat_tier_keys($id) as $tk) {
        $td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
        if (!empty($td) && (!empty($td['min_passenger']) || !empty($td['half_day_morning_4hours']))) {
            $boat_tier_count++;
        }
    }

    if ($boat_tier_count > 0 || $pricing_by_group) {
        $service_type = 'boat_charter';
    } elseif (
        strpos($title_lower, 'transportation') !== false ||
        strpos($title_lower, 'transfer') !== false ||
        strpos($title_lower, 'shuttle') !== false
    ) {
        $service_type = 'transportation';
    } elseif (
        strpos($title_lower, 'chef') !== false ||
        strpos($title_lower, 'dining') !== false ||
        strpos($title_lower, 'dinner') !== false ||
        in_array('Dining', $categories, true)
    ) {
        $service_type = 'dining';
    } else {
        $service_type = 'tour';
    }

    // ── Boat tiers ────────────────────────────────────────────────────────────
    $tiers = [];
    foreach (crtt_get_boat_tier_keys($id) as $tier_key) {
        $tier_data = isset($all_fields[$tier_key]) ? $all_fields[$tier_key] : [];
        if (!empty($tier_data)) {
            $parsed = crtt_detail_parse_tier($tier_data);
            if (!empty($parsed)) $tiers[] = $parsed;
        }
    }
	
	
	// ── Durations list (boat charter only) ──────────────────────────────────
	$durations = [];
	if ($service_type === 'boat_charter') {
		$duration_map = []; // label => ['pickup_time' => ..., 'duration_key' => ..., 'prices' => [...]]
		foreach ($tiers as $tier) {
			foreach ($tier['durations'] as $d) {
				$label = $d['label'];
				if (!isset($duration_map[$label])) {
					$duration_map[$label] = [
						'pickup_time'  => $d['pickup_time'],
						'duration_key' => $d['duration_key'],
						'prices'       => [],
					];
				}
				$duration_map[$label]['prices'][] = $d['price'];
			}
		}
		foreach ($duration_map as $label => $info) {
			$durations[] = [
				'label'             => $label,
				'duration_key'      => $info['duration_key'],
				'pickup_time'       => $info['pickup_time'],
				'price_starts_from' => min($info['prices']),
			];
		}
	}
	
	// ── Rate table (flattened tier × duration combinations) ─────────────────
	$rate_table = [];
	foreach ($tiers as $tier) {
		foreach ($tier['durations'] as $d) {
			$rate_table[] = [
				'min_pax'        => (int) $tier['min_passenger'],
				'max_pax'        => (int) $tier['max_passenger'],
				'duration_label' => $d['label'],
				'duration_key'   => $d['duration_key'],
				'pickup_time'    => $d['pickup_time'],
				'price'          => (float) $d['price'],
				'boat_name'      => $tier['boat_name'],
				'summary'        => sprintf(
					'%d–%d passengers on %s: %s — $%s',
					(int) $tier['min_passenger'],
					(int) $tier['max_passenger'],
					$tier['boat_name'],
					$d['label'],
					$d['price']
				),
			];
		}
	}

    // ── Pricing fields ────────────────────────────────────────────────────────
    $tier_1_min   = $acf('tier_1_min');
    $tier_1_max   = $acf('tier_1_max');
    $tier_1_price = $acf('tier_1_price');
    $tier_2_min   = $acf('tier_2_min');
    $tier_2_max   = $acf('tier_2_max');
    $tier_2_price = $acf('tier_2_price');
    $tier_3_min   = $acf('tier_3_min');
    $tier_3_max   = $acf('tier_3_max');
    $tier_3_price = $acf('tier_3_price');

    $pricing_summary = [];

    if ($boat_tier_count > 0) {
        // Superseded by rate_table — boat pricing_summary no longer populated (do not delete)
        // foreach ($tiers as $tier) {
        //     $prices = array_column($tier['durations'], 'price');
        //     $prices = array_filter($prices);
        //     if (empty($prices)) continue;
        //     $pricing_summary[] = [
        //         'min_pax'    => $tier['min_passenger'],
        //         'max_pax'    => $tier['max_passenger'],
        //         'boat'       => $tier['boat_name'],
        //         'price_from' => min($prices),
        //     ];
        // }
    } else {
        foreach ([
            ['min' => $tier_1_min, 'max' => $tier_1_max, 'price' => $tier_1_price],
            ['min' => $tier_2_min, 'max' => $tier_2_max, 'price' => $tier_2_price],
            ['min' => $tier_3_min, 'max' => $tier_3_max, 'price' => $tier_3_price],
        ] as $t) {
            if (!empty($t['price'])) {
                $pricing_summary[] = [
                    'min_pax' => $t['min'],
                    'max_pax' => $t['max'],
                    'price'   => $t['price'],
                ];
            }
        }
    }

	// ── Response ──────────────────────────────────────────────────────────────
    $response = [
        'id'             => $id,
        'title'          => html_entity_decode(get_the_title($id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'slug'           => $post->post_name,
        'link'           => get_permalink($id),
        'service_type'   => $service_type,

        'categories'     => $categories,
        'region'         => $acf('region'),
        'offered_in'     => $acf('offered_in'),
        'badge'          => $acf('badge') !== null ? $acf('badge') : '',
        'cancellation'   => $acf('cancellation') !== null ? $acf('cancellation') : '',
		'cancellation_condition'   => 'Free cancellation is valid only for first 24 hours.',

        'featured_image' => get_the_post_thumbnail_url($id, 'full'),

        'available'      => (bool) $acf('enable_availability'),
        'book_ahead'     => trim($acf('book_ahead')    !== null ? $acf('book_ahead')    : ''),
        'mobile_ticket'  => trim($acf('mobile_ticket') !== null ? $acf('mobile_ticket') : ''),

        'duration'       => $acf('duration') !== null ? $acf('duration') : '',
        'durations'      => $durations,
        'pickup_time'    => $acf('pickup_time') !== null ? $acf('pickup_time') : '',
        'pickup_note'    => trim($acf('pickup_note') !== null ? $acf('pickup_note') : ''),

        'overview'        => isset($sections['overview'])
                                ? crtt_detail_section_to_prose($sections['overview'])
                                : crtt_detail_section_to_prose(isset($sections['preamble']) ? $sections['preamble'] : ''),
        'highlights'      => crtt_detail_extract_bullets(isset($sections['highlights'])       ? $sections['highlights']       : ''),
        'fishing_license' => crtt_detail_section_to_prose(isset($sections['fishing_license']) ? $sections['fishing_license'] : ''),
        'what_to_bring'   => crtt_detail_section_to_prose(isset($sections['what_to_bring'])   ? $sections['what_to_bring']   : ''),

        'included'        => crtt_detail_extract_bullets(isset($sections['included_raw']) ? $sections['included_raw'] : ''),
        'excluded'        => crtt_detail_extract_bullets(isset($sections['excluded_raw']) ? $sections['excluded_raw'] : ''),

        'lunch'           => $acf('lunch') !== null ? $acf('lunch') : '',

        'pricing_by_group' => $pricing_by_group,
        'group_or_person'  => $acf('group_or_person') !== null ? $acf('group_or_person') : '',
        'pricing_summary'  => $pricing_summary,

        // 'tiers'         => $tiers, // commented out in favor of rate_table — do not delete
        'rate_table'       => $rate_table,
    ];

    if ($service_type === 'boat_charter') {
        $response['duration_hour'] = $response['duration'];
        unset($response['duration']);

        // Fields superseded by rate_table for boat charters — still computed above for tours, just hidden here (do not delete)
        unset($response['pricing_summary']);
        unset($response['pickup_time']);
        unset($response['pickup_note']);
    }

    return $response;
}








//=======================================================================================================================
//=========================================== 1. GET /crtt/v1/services/tour/{id} =======================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/tour/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_tour_by_id',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_tour_by_id($request) {
    $id   = (int) $request['id'];
    $post = get_post($id);

    if (!$post || $post->post_status !== 'publish') {
        return new WP_Error('not_found', 'Tour not found', ['status' => 404]);
    }

    $response = crtt_build_detail_response($id, $post);

    if ($response['service_type'] !== 'tour') {
        return new WP_Error('wrong_type', 'This service is not a tour', ['status' => 404]);
    }

    return rest_ensure_response($response);
}



//=======================================================================================================================
//=======================================  2. GET /crtt/v1/services/boat_charter/{id} ===================================
//=======================================================================================================================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/boat_charter/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_boat_charter_by_id',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_boat_charter_by_id($request) {
    $id   = (int) $request['id'];
    $post = get_post($id);

    if (!$post || $post->post_status !== 'publish') {
        return new WP_Error('not_found', 'Boat charter not found', ['status' => 404]);
    }

    $response = crtt_build_detail_response($id, $post);

    if ($response['service_type'] !== 'boat_charter') {
        return new WP_Error('wrong_type', 'This service is not a boat charter', ['status' => 404]);
    }

    return rest_ensure_response($response);
}

























//=======================GET single Tour==================
add_action('rest_api_init', function () {
    register_rest_route('crtt/v1', '/services/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'crtt_get_tour_data',
        'permission_callback' => '__return_true',
    ]);
});

function crtt_get_tour_data($request) {
    if (defined('REST_REQUEST') && REST_REQUEST) {
        error_reporting(0);
    }

    $id   = $request['id'];
    $post = get_post($id);

    if (!$post) return new WP_Error('not_found', 'Tour not found', ['status' => 404]);

    // ── Helpers ───────────────────────────────────────────────────────────────

    $clean = function($text) {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    };

    $html_to_plain = function($html) {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/<li[^>]*>(.*?)<\/li>/is', "\n• $1\n", $html);
        $html = preg_replace('/<\/?(p|br|div|h[1-6]|ul|ol|section|article)[^>]*>/i', "\n", $html);
        $html = wp_strip_all_tags($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = preg_replace('/[ \t]+/', ' ', $html);
        $html = preg_replace('/\n{3,}/', "\n\n", $html);
        return trim($html);
    };

    $collapse_bullets = function($text) {
        $lines = explode("\n", $text);
        $out   = [];
        $i     = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = trim($lines[$i]);
            if (preg_match('/^[•\x{2022}?]$/u', $line)) {
                $j = $i + 1;
                while ($j < $count && trim($lines[$j]) === '') {
                    $j++;
                }
                $value = isset($lines[$j]) ? trim($lines[$j]) : '';
                if ($value !== '') {
                    $out[] = '• ' . $value;
                    $i = $j + 1;
                    continue;
                }
            }
            $out[] = $lines[$i];
            $i++;
        }

        return implode("\n", $out);
    };

    $parse_sections = function($plain) {
        $header_map = [
            'overview'                                      => 'overview',
            'highlights'                                    => 'highlights',
            'fishing\s+license\s+details?'                  => 'fishing_license',
            'what\s+to\s+bring'                             => 'what_to_bring',
            'trip\s+info(?:rmation)?'                       => 'trip_info_raw',
            "what\u{2019}?s\s+included|what'?s\s+included" => 'included_raw',
            "what\u{2019}?s\s+excluded|what'?s\s+excluded" => 'excluded_raw',
        ];

        $header_pattern = '/^[ \t]*(' . implode('|', array_keys($header_map)) . ')[ \t]*:?[ \t]*$/im';
        $chunks   = preg_split($header_pattern, $plain, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $sections = [];
        $current  = 'preamble';

        foreach ($chunks as $chunk) {
            $chunk_trimmed = trim($chunk);
            if ($chunk_trimmed === '') continue;

            $matched_key = null;
            foreach ($header_map as $regex => $key) {
                if (preg_match('/^(?:' . $regex . ')$/iu', $chunk_trimmed)) {
                    $matched_key = $key;
                    break;
                }
            }

            if ($matched_key !== null) {
                $current = $matched_key;
            } else {
                $sections[$current] = isset($sections[$current])
                    ? $sections[$current] . "\n" . $chunk_trimmed
                    : $chunk_trimmed;
            }
        }

        return $sections;
    };

    $extract_bullets = function($text) {
        $lines = explode("\n", $text);
        $items = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^[•\x{2022}?\*\-]\s+(.+)$/u', $line, $m)) {
                $item = trim($m[1]);
                if ($item !== '') $items[] = $item;
            }
        }
        return $items;
    };

    $section_to_prose = function($text) use ($clean) {
        $text = preg_replace('/^[•\x{2022}?\*\-]\s*/mu', '', $text);
        return $clean($text);
    };

    $parse_tier = function($tier) use ($clean) {
        if (empty($tier)) return [];

        $durations = [];

        if (!empty($tier['half_day_morning_4hours']))
            $durations[] = [
                'label'       => 'Half Day Morning Start - 7am (4 Hours)',
                'price'       => $tier['half_day_morning_4hours'],
                'pickup_time' => trim(isset($tier['pick_up_time_4hours']) ? $tier['pick_up_time_4hours'] : ''),
            ];

        if (!empty($tier['half_day_afternoon_4hours']))
            $durations[] = [
                'label'       => 'Half Day Afternoon Start - 1:30pm (4 Hours)',
                'price'       => $tier['half_day_afternoon_4hours'],
                'pickup_time' => trim(isset($tier['pick_up_time_afternoon']) ? $tier['pick_up_time_afternoon'] : ''),
            ];

        if (!empty($tier['34_day_6hours']))
            $durations[] = [
                'label'       => '3/4 Day 7am (6 Hours)',
                'price'       => $tier['34_day_6hours'],
                'pickup_time' => trim(isset($tier['pick_up_time_6hours']) ? $tier['pick_up_time_6hours'] : ''),
            ];

        if (!empty($tier['full_day_8hours']))
            $durations[] = [
                'label'       => 'Full Day 7am (8 Hours)',
                'price'       => $tier['full_day_8hours'],
                'pickup_time' => trim(isset($tier['pick_up_time_8hours']) ? $tier['pick_up_time_8hours'] : ''),
            ];

        return [
            'min_passenger'    => isset($tier['min_passenger'])   ? $tier['min_passenger']   : '',
            'max_passenger'    => isset($tier['max_passenger'])   ? $tier['max_passenger']   : '',
            'boat_name'        => $clean(isset($tier['title_boat'])       ? $tier['title_boat']       : ''),
            'boat_description' => $clean(wp_strip_all_tags(isset($tier['boat_description']) ? $tier['boat_description'] : '')),
            'durations'        => $durations,
        ];
    };

    // ── Parse post body ───────────────────────────────────────────────────────

    $raw_content = apply_filters('the_content', $post->post_content);
    $plain       = $html_to_plain($raw_content);
    $plain       = $collapse_bullets($plain);
    $sections    = $parse_sections($plain);

    // ── ACF ───────────────────────────────────────────────────────────────────

    $all_fields = get_fields($id);
    $acf = function($key) use ($all_fields) {
        return isset($all_fields[$key]) ? $all_fields[$key] : null;
    };

    // ── Service type detection ────────────────────────────────────────────────

    $title_lower  = strtolower(get_the_title($id));
    $categories   = array_map(
        function($c) { return $c->name; },
        wp_get_post_terms($id, 'tour_category')
    );

    $pricing_by_group = (bool) $acf('pricing_by_group');

    // Boat tier count
    $boat_tier_count = 0;
	foreach (crtt_get_boat_tier_keys($id) as $tk) {	
        $td = isset($all_fields[$tk]) ? $all_fields[$tk] : [];
        if (!empty($td) && (!empty($td['min_passenger']) || !empty($td['half_day_morning_4hours']))) {
            $boat_tier_count++;
        }
    }

    if ($boat_tier_count > 0 || $pricing_by_group) {
        $service_type = 'boat_charter';
    } elseif (
        strpos($title_lower, 'transportation') !== false ||
        strpos($title_lower, 'transfer') !== false ||
        strpos($title_lower, 'shuttle') !== false
    ) {
        $service_type = 'transportation';
    } elseif (
        strpos($title_lower, 'chef') !== false ||
        strpos($title_lower, 'dining') !== false ||
        strpos($title_lower, 'dinner') !== false ||
        in_array('Dining', $categories, true)
    ) {
        $service_type = 'dining';
    } else {
        $service_type = 'tour';
    }

    // ── Boat tiers ────────────────────────────────────────────────────────────

    $tiers = [];
    foreach (crtt_get_boat_tier_keys($id) as $tier_key) {
        $tier_data = isset($all_fields[$tier_key]) ? $all_fields[$tier_key] : [];
        if (!empty($tier_data)) {
            $parsed = $parse_tier($tier_data);
            if (!empty($parsed)) $tiers[] = $parsed;
        }
    }

    // ── Pricing summary (replaces flat tier_1/2/3 fields) ────────────────────

    $tier_1_min   = $acf('tier_1_min');
    $tier_1_max   = $acf('tier_1_max');
    $tier_1_price = $acf('tier_1_price');
    $tier_2_min   = $acf('tier_2_min');
    $tier_2_max   = $acf('tier_2_max');
    $tier_2_price = $acf('tier_2_price');
    $tier_3_min   = $acf('tier_3_min');
    $tier_3_max   = $acf('tier_3_max');
    $tier_3_price = $acf('tier_3_price');

    $pricing_summary = [];

    if ($boat_tier_count > 0) {
        // Boat — summarise from tiers (full detail already in tiers[])
        foreach ($tiers as $tier) {
            $prices = array_column($tier['durations'], 'price');
            $prices = array_filter($prices);
            if (empty($prices)) continue;
            $pricing_summary[] = [
                'min_pax'    => $tier['min_passenger'],
                'max_pax'    => $tier['max_passenger'],
                'boat'       => $tier['boat_name'],
                'price_from' => min($prices),
            ];
        }
    } else {
        // Flat tour pricing
        foreach ([
            ['min' => $tier_1_min, 'max' => $tier_1_max, 'price' => $tier_1_price],
            ['min' => $tier_2_min, 'max' => $tier_2_max, 'price' => $tier_2_price],
            ['min' => $tier_3_min, 'max' => $tier_3_max, 'price' => $tier_3_price],
        ] as $t) {
            if (!empty($t['price'])) {
                $pricing_summary[] = [
                    'min_pax' => $t['min'],
                    'max_pax' => $t['max'],
                    'price'   => $t['price'],
                ];
            }
        }
    }

    // ── Response ──────────────────────────────────────────────────────────────

    return rest_ensure_response([

        // Identity
        'id'             => $id,
        'title'          => html_entity_decode(get_the_title($id), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'slug'           => $post->post_name,
        'link'           => get_permalink($id),
        'service_type'   => $service_type,

        // Meta
        'categories'     => $categories,
        'region'         => $acf('region'),
        'offered_in'     => $acf('offered_in'),
        'badge'          => $acf('badge') !== null ? $acf('badge') : '',
        'cancellation'   => $acf('cancellation') !== null ? $acf('cancellation') : '',

        // Media
        'featured_image' => get_the_post_thumbnail_url($id, 'full'),

        // Availability
        'available'      => (bool) $acf('enable_availability'),
        'book_ahead'     => trim($acf('book_ahead')    !== null ? $acf('book_ahead')    : ''),
        'mobile_ticket'  => trim($acf('mobile_ticket') !== null ? $acf('mobile_ticket') : ''),

        // Scheduling
        'duration'       => $acf('duration') !== null ? $acf('duration') : '',
        'pickup_time'    => $acf('pickup_time') !== null ? $acf('pickup_time') : '',
        'pickup_note'    => trim($acf('pickup_note') !== null ? $acf('pickup_note') : ''),

        // Content
        'overview'        => isset($sections['overview'])
                                ? $section_to_prose($sections['overview'])
                                : $section_to_prose(isset($sections['preamble']) ? $sections['preamble'] : ''),
        'highlights'      => $extract_bullets(isset($sections['highlights'])     ? $sections['highlights']     : ''),
        'fishing_license' => $section_to_prose(isset($sections['fishing_license']) ? $sections['fishing_license'] : ''),
        'what_to_bring'   => $section_to_prose(isset($sections['what_to_bring'])   ? $sections['what_to_bring']   : ''),

        // Inclusions / Exclusions — parsed content wins over ACF
        'included'        => $extract_bullets(isset($sections['included_raw']) ? $sections['included_raw'] : ''),
        'excluded'        => $extract_bullets(isset($sections['excluded_raw']) ? $sections['excluded_raw'] : ''),

        // Extras
        'lunch'           => $acf('lunch') !== null ? $acf('lunch') : '',

        // Pricing
        'pricing_by_group' => $pricing_by_group,
        'group_or_person'  => $acf('group_or_person') !== null ? $acf('group_or_person') : '',
        'pricing_summary'  => $pricing_summary,

        // Boat tiers — full detail, empty array for non-boat
        'tiers'            => $tiers,
    ]);
}



//=======================================================================================================================
//=======================================================================================================================
//================================================= n8n rest apis =======================================================
//===================================================== END =============================================================
//=======================================================================================================================












add_action('wp_footer', function() {
?>
<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll('.tour-grid .card_loop').forEach(function(card) {

        const link = card.querySelector('.custom-button');

        if(link){

            card.addEventListener('click', function(e) {

                /* prevent wishlist clicks */
                if(e.target.closest('.wishlist-button-wrap')){
                    return;
                }

                window.location.href = link.href;
            });

            card.style.cursor = 'pointer';
        }
    });

});
</script>
<?php
});









