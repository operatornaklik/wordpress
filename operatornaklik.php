<?php
/**
 * Plugin Name: Operátornaklik.cz
 * Plugin URI: https://operatornaklik.cz/widget
 * Description: Outsourcing zákaznické péče. Chat i volání s 24/7 dostupností našich operátorů
 * Requires at least: 5.9
 * Requires PHP: 5.6
 * Version: 1.0.0
 * Author: Ejlat partners s.r.o.
 * Author URI: https://operatornaklik.cz/
 * Text Domain: operatornaklik
 *
 * @package operatornaklik
 */


/** OPERATORNAKLIK ACTIVATION */
function p596979_operatornaklik_plugin_activated()
{
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        get_option("OPERATORNAKLIK_ACCESS_TOKEN")
            ? update_option('OPERATORNAKLIK_ACCESS_TOKEN', bin2hex(random_bytes(20)))
            : add_option('OPERATORNAKLIK_ACCESS_TOKEN', bin2hex(random_bytes(20)));

        $address = get_option('woocommerce_store_address')
            ? get_option('woocommerce_store_address')
            : get_option('woocommerce_store_address_2');
        get_option('woocommerce_store_city') && $address .= ', ' . get_option('woocommerce_store_city');
        get_option('woocommerce_store_postcode') && $address .= ', ' . get_option('woocommerce_store_postcode');

        $data = [
            'eshopUrl' => get_site_url(),
            'eshopApiUrl' => [
                'orders' => get_rest_url(null, 'operatornaklik/v1/orders'),
                'products' => get_rest_url(null, 'operatornaklik/v1/products'),
                'transport' => get_rest_url(null, 'operatornaklik/v1/transport'),
                'payment' => get_rest_url(null, 'operatornaklik/v1/payment'),
            ],
            'eshopName' => get_bloginfo('name'),
            'eshopTitle' => null,
            'eshopCategory' => get_bloginfo('description'),
            'eshopType' => 'woocommerce',
            'contactPerson' => null,
            'contactEmail' => get_bloginfo('admin_email'),
            'contactPhone' => null,
            'taxId' => null,
            'vatId' => null,
            'billingName' => null,
            "address" => $address,
            'productsCount' => wp_count_posts('product')->publish,
            'access_token' => get_option('OPERATORNAKLIK_ACCESS_TOKEN'),
        ];

    } else {
        add_action('admin_notices', 'Používate verzi bez pluginu WooCommerce, v případě jeho aktivace přeinstalujte prosím Operatornaklik.');
        $data = [
            'eshopUrl' => get_site_url(),
            'eshopApiUrl' => [
                'orders' => null,
                'products' => null,
                'transport' => null,
                'payment' => null,
            ],
            'eshopName' => get_bloginfo('name'),
            'eshopTitle' => null,
            'eshopCategory' => get_bloginfo('description'),
            'eshopType' => 'wordpress',
            'contactPerson' => null,
            'contactEmail' => get_bloginfo('admin_email'),
            'contactPhone' => null,
            'taxId' => null,
            'vatId' => null,
            'billingName' => null,
            "address" => null,
            'productsCount' => 0,
            'access_token' => null,
        ];
    }

    if (
        !isset($data['eshopUrl'])
        || !isset($data['eshopName'])
        || !isset($data['contactEmail'])
        || !isset($data['productsCount'])
    ) {
        wp_die('Informace nejsou kompletní, a proto nemohla být provedena aktivace pluginu.<br>
                Kontaktujte nás prosím na <a href="mailto:dev@operatornaklik.cz">dev@operatornaklik.cz</a><br>
                <a href="' . admin_url('plugins.php') . '">&laquo; vrátit</a>');
    }

    $response = wp_remote_post('https://api.operatornaklik.cz/eshop/install', [
        'body' => json_encode($data),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);

    if (is_wp_error($response)) {
        wp_die('Informace nelze odeslat, a proto nemohla být provedena aktivace pluginu.<br>
                Kontaktujte nás prosím na <a href="mailto:dev@operatornaklik.cz">dev@operatornaklik.cz</a><br>
                <a href="' . admin_url('plugins.php') . '">&laquo; vrátit</a>');
    }
}
register_activation_hook(__FILE__, 'p596979_operatornaklik_plugin_activated');


/** OPERATORNAKLIK DEACTIVATION */
function p596979_operatornaklik_plugin_deactivated()
{
    wp_remote_post('https://api.operatornaklik.cz/eshop/uninstall', [
        'body' => json_encode([
            'eshopUrl' => get_site_url(),
            'eshopType' => is_plugin_active('woocommerce/woocommerce.php') ? 'woocommerce' : 'wordpress',
        ]),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
    ]);
}
register_deactivation_hook(__FILE__, 'p596979_operatornaklik_plugin_deactivated');


/** APPEND INLINE SCRIPT: VERIFY USER */
function p596979_operatornaklik_add_inline_script()
{
    $current_user_id = get_current_user_id();
    if ($current_user_id) {
        $user_info = get_userdata($current_user_id);

        $name = $user_info->user_login ?: '';
        $telephone = get_user_meta($current_user_id, 'phone_number', true)
            ? get_user_meta($current_user_id, 'phone_number', true) : '';
        $email = $user_info->user_email ?:  '';
        $id = password_hash($name . $email . $telephone, PASSWORD_BCRYPT, [
            'salt' => get_option('OPERATORNAKLIK_ACCESS_TOKEN'),
        ]);

        if ($email) {
            wp_register_script('p596979_operatornaklik_verify', '');
            wp_enqueue_script('p596979_operatornaklik_verify');
            wp_add_inline_script('p596979_operatornaklik_verify', 'window.p596979_verify = {
            name: "' . $name . '",
            telephone: "' . $telephone . '",
            email: "' . $email . '",
            id: "' . $id . '"
        }');
            wp_script_add_data('p596979_operatornaklik_verify', 'defer', true);
        }
    }
}
add_action('wp_enqueue_scripts', 'p596979_operatornaklik_add_inline_script');


/** APPEND SCRIPT: WIDGET
 *  #external service call for creating user account on third party service, terms & privacy policies provided in readme file
 */
function p596979_operatornaklik_add_script()
{
    /** #external service call for creating user account on third party service, terms & privacy policies provided in readme file */
    wp_register_script('p596979_operatornaklik', 'https://api.operatornaklik.cz/widget/app.js', '', '1.0');
    wp_enqueue_script('p596979_operatornaklik');
    wp_script_add_data('p596979_operatornaklik', 'defer', true);
}
add_action('wp_enqueue_scripts', 'p596979_operatornaklik_add_script');


/** API ENDPOINTS */
if (is_plugin_active('woocommerce/woocommerce.php')) {
    require_once(plugin_dir_path(__FILE__) . 'class.operatornaklik-rest-api.php');
    add_action('rest_api_init', ['Operatornaklik_REST_API', 'init']);
}

