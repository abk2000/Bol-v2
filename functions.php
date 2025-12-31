<?php

/**
 * Enqueue parent theme styles, child theme styles, and custom JavaScript.
 */
function merto_child_register_scripts()
{
    $parent_style = 'merto-style';

    // Enqueue the parent theme stylesheet
    wp_enqueue_style(
        $parent_style,
        get_template_directory_uri() . '/style.css',
        array('font-awesome-5', 'merto-reset'),
        merto_get_theme_version()
    );

    // Enqueue the child theme stylesheet
    wp_enqueue_style(
        'merto-child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array($parent_style)
    );

    // Enqueue a custom JavaScript file with automatic cache busting
    wp_enqueue_script(
        'toggle-description-script',
        get_stylesheet_directory_uri() . '/js/custom.js',
        array('jquery'), // Add 'jquery' if needed
        filemtime(get_stylesheet_directory() . '/js/custom.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'merto_child_register_scripts', 999);

/**
 * Display a language switcher depending on WPML availability.
 * Falls back to a custom menu if WPML is not active.
 */
function merto_wpml_language_selector()
{
    echo '<div class="test"></div>';
    if (class_exists('SitePress')) {
        do_action('wpml_add_language_selector');
    } else {
        do_action('merto_header_language_switcher'); /* Allow use another language switcher */
        // Display the 'Language Menu'
        wp_nav_menu(array(
            'menu'           => 'Language Menu',
            'container'      => false,
            'container_class' => 'language-menu-container',
            'menu_class'     => 'language-menu',
            'fallback_cb'    => false
        ));
    }
}

/**
 * Customizes how product variation attributes are displayed.
 * Adds visual formatting for colors and special behavior for purchase options.
 */
function my_custom_variation_attribute_options_html($html, $args)
{
    $theme_options = merto_get_theme_options();

    // Use default dropdown if dropdown setting is enabled
    if ($theme_options['ts_prod_attr_dropdown']) {
        return $html;
    }

    global $product;

    $attr_color_text = $theme_options['ts_prod_attr_color_text'];
    $use_variation_thumbnail = $theme_options['ts_prod_attr_color_variation_thumbnail'];

    $options = $args['options'];
    $attribute_name = $args['attribute'];

    ob_start();
    // Display size chart button for size attributes on single product pages
    if ($theme_options['ts_prod_size_chart'] && is_singular('product')) {
        if (strpos(sanitize_title($attribute_name), 'size') !== false && merto_get_product_size_chart_id()) {
            echo '<a class="ts-product-size-chart-button" href="#"><span>' . esc_html__('Size Chart', 'merto') . '</span></a>';
            add_action('wp_footer', 'merto_add_product_size_chart_popup_modal', 999);
            wp_cache_set('ts_size_chart_is_showed', true);
        }
    }

    if (is_array($options)) {
?>
        <div class="ts-product-attribute">
            <?php
            $selected_key = 'attribute_' . sanitize_title($attribute_name);

            $selected_value = isset($_REQUEST[$selected_key]) ? wc_clean(wp_unslash($_REQUEST[$selected_key])) : $product->get_variation_default_attribute($attribute_name);

            // Get terms if this is a taxonomy - ordered
            if (taxonomy_exists($attribute_name)) {

                $class = 'option';
                $is_attr_color = false;
                $attribute_color = wc_sanitize_taxonomy_name('color');
                if ($attribute_name == wc_attribute_taxonomy_name($attribute_color)) {
                    if (!$attr_color_text) {
                        $is_attr_color = true;
                        $class .= ' color';

                        if ($use_variation_thumbnail) {
                            $color_variation_thumbnails = merto_get_color_variation_thumbnails();
                        }
                    } else {
                        $class .= ' text';
                    }
                }

                // Get ordered terms
                $terms = wc_get_product_terms($product->get_id(), $attribute_name, array('fields' => 'all'));

                // Custom sort order for purchase options
                if ($attribute_name === 'pa_purchase-options') {
                    usort($terms, function ($a, $b) {
                        $order = array('rent', 'monthly-term'); // Desired order
                        $pos_a = array_search($a->slug, $order);
                        $pos_b = array_search($b->slug, $order);

                        // Put unknown items at the end in original order
                        if ($pos_a === false) return 1;
                        if ($pos_b === false) return -1;

                        return $pos_a - $pos_b;
                    });
                }

                // Render each term
                foreach ($terms as $term) {
                    if (! in_array($term->slug, $options)) {
                        continue;
                    }
                    $term_name = apply_filters('woocommerce_variation_option_name', $term->name);

                    // Prepare color swatch data
                    if ($is_attr_color && !$use_variation_thumbnail) {
                        $datas = get_term_meta($term->term_id, 'ts_product_color_config', true);
                        if ($datas) {
                            $datas = unserialize($datas);
                        } else {
                            $datas = array(
                                'ts_color_color'                 => "#ffffff",
                                'ts_color_image'                 => 0
                            );
                        }
                    }

                    $selected_class = sanitize_title($selected_value) == sanitize_title($term->slug) ? 'selected' : '';

                    // Add extra classes only for pa_purchase-options
                    $extra_classes = '';
                    if ($attribute_name === 'pa_purchase-options') {
                        $extra_classes = 'purchase-option'; // Add any classes you want here
                    }

                    echo '<div data-value="' . esc_attr($term->slug) . '" class="' . $class . ' ' . $selected_class . ' ' . $extra_classes . '">';

                    if ($is_attr_color) {
                        if ($use_variation_thumbnail) {
                            if (isset($color_variation_thumbnails[$term->slug])) {
                                echo '<a href="#">' . $color_variation_thumbnails[$term->slug] . '<span class="ts-tooltip button-tooltip">' . esc_html($term_name) . '</span></a>';
                            }
                        } else {
                            if (absint($datas['ts_color_image']) > 0) {
                                echo '<a href="#">' . wp_get_attachment_image(absint($datas['ts_color_image']), 'ts_prod_color_thumb', true, array('title' => $term_name, 'alt' => $term_name)) . '<span class="ts-tooltip button-tooltip">' . esc_html($term_name) . '</span></a>';
                            } else {
                                echo '<a href="#" style="background-color:' . $datas['ts_color_color'] . '"><span class="ts-tooltip button-tooltip">' . esc_html($term_name) . '</span></a>';
                            }
                        }
                    } elseif ($attribute_name === 'pa_purchase-options' && in_array($term->slug, array('monthly-term', 'rent', 'refurbished-lease'))) {
                        echo '<a href="#" class="variation-link">' . esc_html($term_name) . '</a>';
                        echo '<div class="variation-description-wrapper">';

                        // Info icon that toggles the description
                        echo '<div class="info-toggle-wrapper">';
                        echo '<svg class="info-icon" data-toggle="collapse-info" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#0073aa" viewBox="0 0 16 16" style="margin-right:6px; vertical-align:middle; cursor:pointer;">';
                        echo '<path d="M8 0a8 8 0 1 0 8 8A8.01 8.01 0 0 0 8 0zM7 6h2v6H7zm0-3h2v2H7z"/>';
                        echo '</svg>';
                        echo '</div>';

                        // Collapsible description block
                        echo '<div class="variation-description-block">';

                        if ($term->slug === 'monthly-term') {
                            echo '<p class="variation-label-description">Lease this product for a fixed term at a lower monthly rate. It’s perfect for users who want reliable access over a longer period without paying the full price upfront.</p>';
                        } elseif ($term->slug === 'rent') {
                            echo '<p class="variation-label-description">Rent this item on a short-term basis with no long-term commitment. Ideal for temporary needs, one-time projects or trying the product before making a bigger decision. You can return it anytime.</p>';
                        } elseif ($term->slug === 'refurbished-lease') {
                            echo '<p class="variation-label-description">Lease a professionally refurbished product at a reduced monthly rate. Enjoy dependable performance with added savings—ideal for cost-conscious users who want long-term value without compromising on quality.</p>';
                        }
                        echo '</div>';
                        echo '</div>';
                    } else {
                        echo '<a href="#">' . esc_html($term_name) . '</a>';
                    }
                    echo '</div>';
                }
            } else {
                foreach ($options as $option) {
                    $class = 'option';
                    $class .= sanitize_title($selected_value) == sanitize_title($option) ? ' selected' : '';
                    echo '<div data-value="' . esc_attr($option) . '" class="' . $class . '"><a href="#">' . esc_html(apply_filters('woocommerce_variation_option_name', $option)) . '</a></div>';
                }
            }
            ?>
        </div>
    <?php
    }

    return ob_get_clean() . $html;
}

/**
 * Replaces the parent theme's variation HTML with the custom version.
 */
add_action('after_setup_theme', function () {
    remove_filter('woocommerce_dropdown_variation_attribute_options_html', 'merto_variation_attribute_options_html', 10);
    add_filter('woocommerce_dropdown_variation_attribute_options_html', 'my_custom_variation_attribute_options_html', 10, 2);
});

/**
 * Customizes the price display for variable products.
 * Adds a "From:" prefix and "/mo" suffix to show monthly pricing.
 */
add_filter('woocommerce_get_price_html', 'change_variable_products_price_display', 10, 2);
function change_variable_products_price_display($price, $product)
{

    // Only for variable products type
    if (! $product->is_type('variable')) return $price;

    $prices = $product->get_variation_prices(true);

    if (empty($prices['price']))
        return apply_filters('woocommerce_variable_empty_price_html', '', $product);

    $min_price = current($prices['price']);
    $max_price = end($prices['price']);

    $prefix_html = '<span class="price-prefix">' . __('From: ') . '</span>';
    $suffix_text = ' <span class="price-suffix">' . __('/mo') . '</span>';

    $prefix = $min_price !== $max_price ? $prefix_html : '';

    return apply_filters(
        'woocommerce_variable_price_html',
        $prefix . wc_price($min_price) . $product->get_price_suffix() . $suffix_text,
        $product
    );
}

// Add a "Package Content" tab with conditional content based on category
add_filter('woocommerce_product_tabs', function ($tabs) {
    global $product;

    $tabs['package_content'] = [
        'title'    => __('Package Content', 'your-textdomain'),
        'priority' => 25, // adjust position
        'callback' => function () use ($product) {

            // Check if product belongs to category "rent"
            if (has_term('rent', 'product_cat', $product->get_id())) {
                echo '<h2>Package Content (Rental)</h2>';
                echo '<p>We make sure you can get started right away. Your rental product is delivered completely ready-to-use with the following essential items:</p>';
                echo '<ul><strong>';
                echo '<li>Your rented device</li>';
                echo '<li>Original charger and cable (if applicable)</li>';
                echo '<li>A protective sleeve or case</li>';
                echo '<li>Secure and sturdy shipping box</li>';
                echo '</strong></ul>';
                echo 'All you have to do is unpack and begin!';
            } else {
                echo '<h2>Package Content</h2>';
                echo '<p>We make sure you can get started right away. Your product is delivered completely ready-to-use with the following essential items:</p>';
                echo '<ul><strong>';
                echo '<li>Your device</li>';
                echo '<li>Original charger and cable (if applicable)</li>';
                echo '<li>Secure and sturdy shipping box</li>';
                echo '</strong></ul>';
                echo 'All you have to do is open the box and begin!';
            }
        },
    ];

    return $tabs;
});

// Add a "Warranties" tab to all WooCommerce products (global/static content)
add_filter('woocommerce_product_tabs', function ($tabs) {
    $tabs['warranties'] = [
        'title'    => __('Warranty', 'your-textdomain'),
        'priority' => 55, // adjust position; lower = further left
        'callback' => function () {
            // The title and content for the warranty tab
            echo '<h2>Product Warranty</h2>';

            echo '<p>Every new MacBook leased from JUUZ. comes with a 2-year legal warranty, starting from the delivery date. We ensure that your investment is protected against defects.</p>';

            echo '<ul>';
            echo '<li><strong>Coverage:</strong> This warranty covers all manufacturing defects and hardware malfunctions that occur during normal use.</li>';
            echo '<li><strong>Exclusions:</strong> Accidental damage (e.g., drops, spills), software problems, and cosmetic wear and tear are not covered.</li>';
            echo '<li><strong>Support:</strong> If you encounter a problem, contact us directly. We use our support tools to provide fast assistance and will manage the warranty claim process on your behalf to get your device repaired or replaced.</li>';
            echo '</ul>';
        },
    ];
    return $tabs;
});

function juuz_generate_seo_for_all_posts($post_id)
{
    // Draai alleen voor 'post' en niet voor revisies.
    if (get_post_type($post_id) !== 'post' || wp_is_post_revision($post_id)) {
        return;
    }

    // --- 1. Vul Focus Keyphrase met de post titel ---
    if (!get_post_meta($post_id, '_yoast_wpseo_focuskw', true)) {
        $post_title = get_the_title($post_id);
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $post_title);
    }

    // --- 2. Vul SEO-titel met "Post Titel | Site Naam" ---
    if (!get_post_meta($post_id, '_yoast_wpseo_title', true)) {
        $post_title = get_the_title($post_id);
        $site_name = get_bloginfo('name');
        $new_seo_title = $post_title . ' | ' . $site_name;
        update_post_meta($post_id, '_yoast_wpseo_title', $new_seo_title);
    }

    // --- 3. Vul Meta-omschrijving met de eerste ~155 karakters van de content ---
    if (!get_post_meta($post_id, '_yoast_wpseo_metadesc', true)) {
        $post_content = get_post_field('post_content', $post_id);
        // Verwijder HTML, shortcodes en extra witruimte voor een schone tekst.
        $clean_content = trim(wp_strip_all_tags(strip_shortcodes($post_content)));
        if (!empty($clean_content)) {
            $excerpt = substr($clean_content, 0, 155);
            // Voorkom dat het laatste woord wordt afgebroken.
            $last_space = strrpos($excerpt, ' ');
            if ($last_space !== false) {
                $new_meta_desc = substr($excerpt, 0, $last_space) . '...';
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_meta_desc);
            }
        }
    }
}
// Voer de functie uit wanneer een post wordt opgeslagen, maar met een LAGE prioriteit (99).
add_action('save_post', 'juuz_generate_seo_for_all_posts', 99, 1);

// "Back to shop" ONLY on the 5 category archives (not on the main Shop page)
add_action('woocommerce_before_shop_loop', function () {

    // Only on product CATEGORY archives
    if (! is_tax('product_cat')) {
        return; // prevents it on the Shop page and other archives
    }

    // Category slugs that should show the button
    $allowed_slugs = ['buy', 'cloud', 'lease', 'refurbished-lease', 'rent'];

    $qo = get_queried_object();
    if (empty($qo) || ! in_array($qo->slug, $allowed_slugs, true)) {
        return; // not one of your 5 categories
    }

    // Link back to the main shop
    $shop_url = function_exists('wc_get_page_permalink')
        ? wc_get_page_permalink('shop')
        : get_permalink(wc_get_page_id('shop'));

    echo '<a href="' . esc_url($shop_url) . '" class="juuz-back-to-shop wc-forward">&larr; '
        . esc_html__('Back to shop', 'your-textdomain') . '</a>';
}, 5);

// Adding fees to products and categories
add_action('woocommerce_cart_calculate_fees', 'custom_pcat_fee', 20, 1);
function custom_pcat_fee($cart)
{
    if (is_admin() && ! defined('DOING_AJAX'))
        return;

    // Set HERE your categories (can be term IDs, slugs or names) in a coma separated array
    $categories = array('322');
    $fee_amount = 0;

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item) {
        if (has_term($categories, 'product_cat', $cart_item['product_id']))
            $fee_amount = 99;
    }

    // Adding the fee - Lower end rental macbooks 
    if ($fee_amount > 0) {
        // Last argument is related to enable tax (true or false)
        WC()->cart->add_fee(__("Rental | Deposit", "woocommerce"), $fee_amount, false);
    }
    // Set HERE your categories (can be term IDs, slugs or names) in a coma separated array
    $categories = array('325');
    $fee_amount = 0;

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item) {
        if (has_term($categories, 'product_cat', $cart_item['product_id']))
            $fee_amount = 199;
    }

    // Adding the fee - Higher end rental macbooks 
    if ($fee_amount > 0) {
        // Last argument is related to enable tax (true or false)
        WC()->cart->add_fee(__("Rental | Deposit", "woocommerce"), $fee_amount, false);
    }
    // Set HERE your categories (can be term IDs, slugs or names) in a coma separated array
    $categories = array('305');
    $fee_amount = 0;

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item) {
        if (has_term($categories, 'product_cat', $cart_item['product_id']))
            $fee_amount = 199;
    }

    // Adding the fee - New lease macbooks 
    if ($fee_amount > 0) {
        // Last argument is related to enable tax (true or false)
        WC()->cart->add_fee(__("Initial Payment", "woocommerce"), $fee_amount, false);
    }

    // Set HERE your categories (can be term IDs, slugs or names) in a coma separated array
    $categories = array('374');
    $fee_amount = 0;

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item) {
        if (has_term($categories, 'product_cat', $cart_item['product_id']))
            $fee_amount = 99;
    }

    // Adding the fee - Refurbished lease macbooks 
    if ($fee_amount > 0) {
        // Last argument is related to enable tax (true or false)
        WC()->cart->add_fee(__("Initial Payment", "woocommerce"), $fee_amount, false);
    }

    // Adding the fee - New lease Apple Watches 
    if ($fee_amount > 0) {
        // Last argument is related to enable tax (true or false)
        WC()->cart->add_fee(__("Initial Payment", "woocommerce"), $fee_amount, false);
    }

    // Set HERE your categories (can be term IDs, slugs or names) in a coma separated array
    $categories = array('302');
    $fee_amount = 0;

    // Loop through cart items
    foreach ($cart->get_cart() as $cart_item) {
        if (has_term($categories, 'product_cat', $cart_item['product_id']))
            $fee_amount = 49;
    }

    // Adding the fee - Refurbished lease macbooks 
    if ($fee_amount > 0) {
        // Last argument is related to enable tax (true or false)
        WC()->cart->add_fee(__("Initial Payment", "woocommerce"), $fee_amount, false);
    }
}

// Disable WordPress oEmbed
function disable_wp_oembed()
{
    remove_action('rest_api_init', 'wp_oembed_register_route');
    remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
    remove_filter('pre_oembed_result', 'wp_filter_pre_oembed_result', 10);
}
add_action('init', 'disable_wp_oembed');

/**
 * Plugin Name: Custom WooCommerce Subscription Restrictions
 * Description: A simple plugin to prevent customers from canceling their subscriptions or removing individual items from a subscription.
 * Version: 1.1
 * Author: Gemini
 */

// Exit if accessed directly to prevent security vulnerabilities.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Plugin Name: Custom WooCommerce Subscription Restrictions
 * Description: A simple plugin to prevent customers from canceling their subscriptions or removing individual items from a subscription.
 * Version: 1.2
 * Author: Gemini
 */

// Exit if accessed directly to prevent security vulnerabilities.
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Removes the "Cancel" button from the My Account > View Subscription page.
 *
 * This function hooks into 'wcs_view_subscription_actions'. We use a very high
 * priority (999) to make sure our function is the last one to run, overriding
 * any other themes or plugins that might also be modifying the actions.
 *
 * @param array           $actions      The array of action buttons.
 * @param WC_Subscription $subscription The customer's subscription object.
 * @return array The modified array of actions, without the 'cancel' button.
 */
function gemini_wcs_remove_cancel_action($actions, $subscription)
{
    // Check if the 'cancel' key exists in the array and remove it.
    if (isset($actions['cancel'])) {
        unset($actions['cancel']);
    }
    return $actions;
}
add_filter('wcs_view_subscription_actions', 'gemini_wcs_remove_cancel_action', 999, 2);


/**
 * Hides the remove icon ('x') for items in a subscription.
 *
 * This function hooks into 'woocommerce_can_subscription_item_be_removed'.
 * By always returning 'false', we ensure the remove icon never appears. This
 * is the first layer of defense, handling the user interface.
 *
 * @param bool            $is_removable Whether the item is removable by default.
 * @param object          $item         The subscription line item.
 * @param WC_Subscription $subscription The customer's subscription object.
 * @return bool Always returns false to prevent removal.
 */
function gemini_wcs_prevent_item_removal_ui($is_removable, $item, $subscription)
{
    // Force the result to be false, meaning no item is ever removable.
    return false;
}
add_filter('woocommerce_can_subscription_item_be_removed', 'gemini_wcs_prevent_item_removal_ui', 999, 3);


/**
 * Actively blocks the item removal action if a user tries to access the URL directly.
 *
 * This is the second layer of defense. It checks if a user is trying to remove an
 * item by looking for the 'remove_item' parameter in the URL. If found, it adds
 * an error message and redirects them, preventing the item from being removed.
 */
function gemini_wcs_block_item_removal_action()
{
    // Check if we are on the view-subscription page and the 'remove_item' URL parameter exists.
    if (! is_admin() && function_exists('wcs_is_view_subscription_page') && wcs_is_view_subscription_page() && isset($_GET['remove_item'])) {

        // Get the current subscription's ID from the URL.
        $subscription_id = get_query_var('view-subscription');
        // Build the URL to redirect the user back to.
        $redirect_url = wc_get_endpoint_url('view-subscription', $subscription_id, wc_get_page_permalink('myaccount'));

        // Add a standard WooCommerce error message to inform the user.
        wc_add_notice(__('Removing items from this subscription is not permitted.', 'woocommerce'), 'error');

        // Redirect the user back to the subscription page and stop the script.
        wp_safe_redirect($redirect_url);
        exit;
    }
}
add_action('template_redirect', 'gemini_wcs_block_item_removal_action', 1);

// verplicht tel nummer
add_filter('woocommerce_billing_fields', 'maak_telefoon_verplicht');
function maak_telefoon_verplicht($fields)
{
    $fields['billing_phone']['required'] = true;
    return $fields;
}

/**
 * Prevent stock reduction on subscription renewal orders.
 */
add_filter('woocommerce_can_reduce_order_stock', function ($can_reduce_stock, $order) {

    // Controleer of het een verlengingsorder is van een abonnement
    if (function_exists('wcs_order_contains_renewal') && wcs_order_contains_renewal($order)) {
        return false; // Niet de voorraad verlagen
    }

    return $can_reduce_stock;
}, 10, 2);

// Add KVK and VAT fields to checkout
add_filter('woocommerce_checkout_fields', 'custom_business_fields_checkout');
function custom_business_fields_checkout($fields)
{
    $fields['billing']['billing_kvk'] = array(
        'type'        => 'text',
        'label'       => 'Company Registration Nr',
        'placeholder' => 'e.g. 12345678',
        'required'    => false,
        'class'       => array('form-row-wide'),
        'priority'    => 30,
    );

    $fields['billing']['billing_vat'] = array(
        'type'        => 'text',
        'label'       => 'VAT Number',
        'placeholder' => 'e.g. NL123456789B01',
        'required'    => false,
        'class'       => array('form-row-wide'),
        'priority'    => 31,
    );

    return $fields;
}

/** Creating Offers in BOL through product edit page */
/** Creating Widget */
add_action('add_meta_boxes', 'offers_widgets');
function offers_widgets()
{
    add_meta_box(
        'extra_product_details',
        'Create Offers in BOL',
        'render_extra_product_details_box',
        'product',
        'normal',
        'high'
    );
}

/** Showing Widget */
function render_extra_product_details_box($post)
{
    $product = wc_get_product($post->ID);

    $ean = get_post_meta($post->ID, '_global_unique_id', true);
    $price = $product->get_regular_price();
    $stock = $product->get_stock_quantity();

    $show_success = get_transient('bol_offer_success_flag_' . $post->ID);

    if ($show_success) {
        echo '<div style="padding:10px; margin-bottom:15px; background:#d1ffd1; border-left:5px solid #32a852;">
                <strong>Offer created on BOL.</strong>
              </div>';

        delete_transient('bol_offer_success_flag_' . $post->ID);
    }
    if (get_transient('bol_offer_error_flag_' . $post->ID)) {
        echo '<div style="padding:10px; margin-bottom:15px; background:#fbeaea; border-left:5px solid #d63638; color: #941c1e;">
                <strong>Offer not created in BOL. Please try again and fill the form properly.</strong>
              </div>';
        delete_transient('bol_offer_error_flag_' . $post->ID);
    }
    ?>

    <table class="form-table">
        <?php wp_nonce_field('bol_offer_nonce_action', 'bol_offer_nonce'); ?>

        <!-- EAN -->
        <tr>
            <th><label>EAN</label></th>
            <td>
                <input type="text" name="ean" class="regular-text" value="<?php echo esc_attr($ean); ?>" placeholder="Only Numeric values" />
                <span class="error-msg"></span>
            </td>
        </tr>

        <!-- Condition category -->
        <tr>
            <th><label>Condition Category</label></th>
            <td>
                <select name="condition_category" class="regular-text" required>
                    <option value="">Select category</option>
                    <option value="NEW">NEW</option>
                    <option value="SECONDHAND">SECONDHAND</option>
                    <option value="REFURBISHED">REFURBISHED</option>
                </select>
                <span class="error-msg"></span>
            </td>
        </tr>
        <!-- Condition state -->
        <tr id="state">
            <th><label>Condition State</label></th>
            <td>
                <select id="condition_state" name="condition_state" class="regular-text">
                    <option value="">Select an option</option>
                    <option value="AS_NEW">AS_NEW</option>
                    <option value="GOOD">GOOD</option>
                    <option value="REASONABLE">REASONABLE</option>
                    <option value="MODERATE">MODERATE</option>
                </select>
                <span class="error-msg"></span>
            </td>
        </tr>
        <!-- Condition grade -->
        <tr id="grade">
            <th><label>Condition Grade</label></th>
            <td>
                <select name="condition_grade" class="regular-text">
                    <option value="">Select grade</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                </select>
                <span class="error-msg"></span>
            </td>
        </tr>
        <input type="hidden" name="margin" value="false">

        <!-- Stock -->
        <tr>
            <th><label>Stock Amount</label></th>
            <td>
                <input type="text" name="stock_amount" placeholder="Only Numeric values" value="<?php echo esc_attr($stock); ?>" />
                <span class="error-msg"></span>
            </td>
        </tr>

        <!-- Price -->
        <tr>
            <th><label>Unit Price (€)</label></th>
            <td>
                <input type="text" name="unit_price" placeholder="Only Numeric values" value="<?php echo esc_attr($price); ?>" />
                <span class="error-msg"></span>
            </td>
        </tr>

        <input type="hidden" name="fulfilment_method" value="FBR">
        <tr>
            <th><label>Schedule</label></th>
            <td>
                <select id="schedule" name="schedule" class="regular-text">
                    <option value="">Select an option</option>
                    <option value="BOL_DELIVERY_PROMISE">BOL Delivery Promise</option>
                    <option value="MY_DELIVERY_PROMISE">My Delivery Promise</option>
                    <option value="SHIPPING_VIA_BOL">Shipping VIA BOL</option>
                </select>
                <span class="error-msg"></span>
            </td>
        </tr>

        <!-- Delivery: minimum days -->
        <tr>
            <th><label>Minimum Days to Customer</label></th>
            <td>
                <input type="text" name="min_days" class="regular_text" min="0" placeholder="Minimum days must be greater than or equal to 0." value="" />
                <span class="error-msg"></span>
            </td>
        </tr>

        <!-- Delivery: maximum days -->
        <tr>
            <th><label>Maximum Days to Customer</label></th>
            <td>
                <input type="text" name="max_days" class="regular_text" placeholder="Maximum days must be greater than or equal to 1." value="" />
                <span class="error-msg"></span>
            </td>
        </tr>

        <!-- Ultimate order time -->
        <tr id="order">
            <th><label>Ultimate Order Time</label></th>
            <td>
                <select name="ultimate_order_time" id="ultimate_order_time">
                    <option value="">Select an option</option>
                    <option value="12:00">12:00</option>
                    <option value="13:00">13:00</option>
                    <option value="14:00">14:00</option>
                    <option value="15:00">15:00</option>
                    <option value="16:00">16:00</option>
                    <option value="17:00">17:00</option>
                    <option value="18:00">18:00</option>
                    <option value="19:00">19:00</option>
                    <option value="20:00">20:00</option>
                    <option value="21:00">21:00</option>
                    <option value="22:00">22:00</option>
                    <option value="23:00">23:00</option>
                </select>
                <p class="description">Required if minimum days = 0 and maximum days = 1</p>
                <span class="error-msg"></span>
            </td>
        </tr>
    </table>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('#post'); // Standard WordPress post form
            const category = document.querySelector('select[name="condition_category"]');
            const stateRow = document.getElementById('state');
            const gradeRow = document.getElementById('grade');
            const minDaysField = document.querySelector('input[name="min_days"]');
            const maxDaysField = document.querySelector('input[name="max_days"]');
            const orderRow = document.getElementById('order');
            const orderTimeField = document.querySelector('select[name="ultimate_order_time"]');

            // --- Error Helpers ---
            function showError(input, message) {
                const td = input.closest('td');
                const errorSpan = td.querySelector('.error-msg');
                if (errorSpan) errorSpan.textContent = message;
                input.style.border = '1px solid #d63638';
            }

            function clearError(input) {
                const td = input.closest('td');
                const errorSpan = td.querySelector('.error-msg');
                if (errorSpan) errorSpan.textContent = '';
                input.style.border = '';
            }

            // --- Validation Logic ---
            function validateInput(input) {
                // Skip validation if the row is hidden
                const row = input.closest('tr');
                if (row && row.style.display === 'none') {
                    clearError(input);
                    return true;
                }

                if (!input.value || input.value.trim() === '') {
                    showError(input, 'This field is required.');
                    return false;
                } else {
                    clearError(input);
                    return true;
                }
            }

            // --- Real-Time Listeners ---
            // Select all inputs inside your specific table
            const inputs = document.querySelectorAll('.form-table input[type="text"], .form-table select');

            inputs.forEach(input => {
                // When user leaves a field (Real-time trigger)
                input.addEventListener('blur', function() {
                    validateInput(this);
                });

                // Clear error immediately when they start typing again
                input.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        clearError(this);
                    }
                });
            });

            // --- Keep your existing UI Logic ---
            function toggleConditionRows() {
                const value = category.value;
                stateRow.style.display = 'none';
                gradeRow.style.display = 'none';
                if (value == 'SECONDHAND') stateRow.style.display = '';
                if (value == 'REFURBISHED') gradeRow.style.display = '';
            }

            function toggleUltimateOrderTime() {
                const minDays = Number(minDaysField.value);
                const maxDays = Number(maxDaysField.value);
                orderRow.style.display = (minDays === 0 && maxDays === 1) ? '' : 'none';
            }

            category.addEventListener('change', () => {
                toggleConditionRows();
                validateInput(category);
            });

            minDaysField.addEventListener('input', toggleUltimateOrderTime);
            maxDaysField.addEventListener('input', toggleUltimateOrderTime);

            toggleConditionRows();
            toggleUltimateOrderTime();

            // --- Final Prevent Submit if Errors Exist ---
            form.addEventListener('submit', function(e) {
                let isValid = true;
                inputs.forEach(input => {
                    if (!validateInput(input)) {
                        isValid = false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    // Optional: Scroll to the first error
                    const firstError = document.querySelector('.error-msg:not(:empty)');
                    if (firstError) firstError.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }
            });
        });
    </script>

<?php
}

/** Get Token */
function bol_get_token($base64Credentials)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://login.bol.com/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            "Authorization: Basic {$base64Credentials}",
            'Content-Type: application/x-www-form-urlencoded'
        ],
    ]);
    $response = curl_exec($curl);
    if (!$response) return ["error" => curl_error($curl)];
    return json_decode($response, true);
}
/** Post Offer */
function bol_create_offer(array $offerData, string $jwt)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.bol.com/retailer/offers',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($offerData, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer {$jwt}",
            "Accept-Language: nl",
            "Content-Type: application/vnd.retailer.v11-beta+json",
            "Accept: application/vnd.retailer.v11-beta+json",
        ),
    ));
    $response = curl_exec($curl);
    if (!$response) return ["error" => curl_error($curl)];
    return json_decode($response, true);
}

function bol_update_offer_price($offer_id, $update_data, $jwt)
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.bol.com/retailer/offers/$offer_id",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($update_data, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer {$jwt}",
            "Accept-Language: nl",
            "Content-Type: application/vnd.retailer.v11-beta+json",
            "Accept: application/vnd.retailer.v11-beta+json",
        ),
    ));
    $response = curl_exec($curl);
    if (!$response) return ["error" => curl_error($curl)];
    return json_decode($response, true);
}

/** Creating offer */
add_action('save_post', 'creating_offer', 10, 1);
function creating_offer($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    if (!current_user_can('edit_post', $post_id)) return;

    if (!isset($_POST['bol_offer_nonce']) || !wp_verify_nonce($_POST['bol_offer_nonce'], 'bol_offer_nonce_action')) {
        return;
    }

    if (empty($_POST['ean']) || empty($_POST['condition_category']) || empty($_POST['unit_price']) || empty($_POST['stock_amount'])) {
        set_transient('bol_offer_notice', 'Missing required fields.', 30);
        return;
    }

    $condition = [
        'category' => $_POST['condition_category'],
    ];

    // SECONDHAND → state
    if ($condition['category'] == 'SECONDHAND' && !empty($_POST['condition_state'])) {
        $condition['state'] = $_POST['condition_state'];
    }

    // REFURBISHED → grade + margin
    if ($condition['category'] === 'REFURBISHED' && !empty($_POST['condition_grade'])) {
        $condition['grade']  = $_POST['condition_grade'];
        $condition['margin'] = false;
    }

    $min_days = intval($_POST['min_days'] ?? 0);
    $max_days = intval($_POST['max_days'] ?? 0);

    $deliveryPromise = [
        'minimumDaysToCustomer' => $min_days,
        'maximumDaysToCustomer' => $max_days,
    ];

    if ($min_days === 0 && !empty($_POST['ultimate_order_time'])) {
        $deliveryPromise['ultimateOrderTime'] = $_POST['ultimate_order_time'];
    }

    $offer = [
        'ean' => $_POST['ean'],
        'condition' => $condition,
        'pricing' => [
            'bundlePrices' => [
                [
                    'quantity'  => 1,
                    'unitPrice' => floatval($_POST['unit_price']),
                ]
            ]
        ],
        'fulfilment' => [
            'method'   => $_POST['fulfilment_method'],
            'schedule' => $_POST['schedule'],
            'deliveryPromise' => $deliveryPromise,
        ],
        'stock' => [
            'amount' => intval($_POST['stock_amount']),
            'managedByRetailer' => true,
        ],
    ];

    $credentials = '486f3de5-f6cf-4edc-8339-03e52462aea4:lajoSX9Y6KUR?Jf5IT!FwbexNsx?(pSZzAG2mJFxKzfYie@KhK!9qQ?HDgRb?kGh';
    $base64_encoded = base64_encode($credentials);

    $tokenResponse = bol_get_token($base64_encoded);
    $jwt = $tokenResponse['access_token'] ?? null;

    if (!$jwt) {
        set_transient('bol_offer_notice', 'Failed to get token from BOL.', 30);
        return;
    }

    $result = bol_create_offer($offer, $jwt);
    if (!empty($result['title']) && $result['title'] == 'Expired JWT' && intval($result['status']) == 401) {
        $tokenResponse = bol_get_token($base64_encoded);
        $jwt = $tokenResponse['access_token'] ?? null;

        if ($jwt) {
            $result = bol_create_offer($offer, $jwt);
        }
    }

    if (!empty($result['error'])) {
        set_transient('bol_offer_notice', 'Error: ' . $result['error'], 30);
        return;
    }

    if (!empty($result['offerId'])) {
        update_post_meta($post_id, '_bol_offer_id', $result['offerId']);

        set_transient('bol_offer_success_flag_' . $post_id, true, 60);
    } else {
        set_transient('bol_offer_error_flag_' . $post_id, true, 60);

        delete_transient('bol_offer_success_flag_' . $post_id);
    }

    update_post_meta($post_id, '_bol_offer_timestamp', current_time('mysql'));
    update_post_meta($post_id, '_bol_offer_response', wp_json_encode($result));

    // set_transient('bol_offer_notice', 'Offer created successfully on BOL!', 30);
}
