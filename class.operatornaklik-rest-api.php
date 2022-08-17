<?php

class Operatornaklik_REST_API {

    public static function init()
    {
        register_rest_route('operatornaklik/v1', '/orders', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => ['Operatornaklik_REST_API', 'p596979_operatornaklik_get_orders'],
        ]);

        register_rest_route('operatornaklik/v1', '/products', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => ['Operatornaklik_REST_API', 'p596979_operatornaklik_get_products'],
        ]);

        register_rest_route('operatornaklik/v1', '/transport', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => ['Operatornaklik_REST_API', 'p596979_operatornaklik_get_transport'],
        ]);

        register_rest_route('operatornaklik/v1', '/payment', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => ['Operatornaklik_REST_API', 'p596979_operatornaklik_get_payment'],
        ]);
    }


    public static function p596979_operatornaklik_get_orders(WP_REST_Request $request)
    {
        self::checkToken($request['token']);
        $search = trim(strval($request['search']));
        $phone = trim(strval($request['phone']));
        $email = trim(strval($request['email']));
        $id = trim(strval($request['id']));
        if (!$phone && !$email && !$id && !$search) {
            header('Content-type: application/json');
            echo wp_json_encode(['error' => 'Put search, email, phone or id to query parameters']);
            die();
        }


        add_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_query_var_customer_email', 10, 2);
        function handle_custom_query_var_customer_email($query, $query_vars)
        {
            if (isset($query_vars['customer_email'])) {
                $query['meta_query'][] = [
                    'key' => '_billing_email',
                    'value' => $query_vars['customer_email'],
                    'compare' => 'like',
                ];
            }
            return $query;
        }
        add_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_query_var_customer_phone', 10, 2);
        function handle_custom_query_var_customer_phone($query, $query_vars)
        {
            if (isset($query_vars['customer_phone'])) {
                $query['meta_query'][] = [
                    'key' => '_billing_phone',
                    'value' => $query_vars['customer_phone'],
                    'compare' => 'like',
                ];
            }
            return $query;
        }


        $results = [];
        if ($search) {
            wc_get_order($search) && $results['orders'][] = self::formatOrder(wc_get_order($search));

            foreach (wc_get_orders([
                'limit' => 1000,
                'customer_email' => esc_attr($search),
                'orderby' => 'date',
                'order' => 'DESC',
            ]) as $result) {
                $results['orders'][] = self::formatOrder($result);
            }

            foreach (wc_get_orders([
                'limit' => 1000,
                'customer_phone' => esc_attr($search),
                'orderby' => 'date',
                'order' => 'DESC',
            ]) as $result) {
                $results['orders'][] = self::formatOrder($result);
            }

            $results['orders'] = array_unique($results['orders'], SORT_REGULAR);

        } elseif ($id) {
            wc_get_order($id) && $results = ['order' => self::formatOrder(wc_get_order($id))];

        } elseif ($email) {

            foreach (wc_get_orders([
                'limit' => 1000,
                'customer_email' => esc_attr($email),
                'orderby' => 'date',
                'order' => 'DESC',
            ]) as $result) {
                $results['orders'][] = self::formatOrder($result);
            }

        } elseif ($phone) {
            foreach (wc_get_orders([
                'limit' => 1000,
                'customer_phone' => esc_attr($phone),
                'orderby' => 'date',
                'order' => 'DESC',
            ]) as $result) {
                $results['orders'][] = self::formatOrder($result);
            }
        }


        remove_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_query_var_customer_email', 10, 2);
        remove_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_query_var_customer_phone', 10, 2);
        header('Content-type: application/json');
        echo wp_json_encode(['data' => $results ]);
    }


    public static function p596979_operatornaklik_get_products(WP_REST_Request $request)
    {
        self::checkToken($request['token']);
        $name = trim(strval($request['name']));
        $id = trim(strval($request['id']));
        if (!$name && !$id) {
            header('Content-type: application/json');
            echo wp_json_encode(['error' => 'Put name or id of product to query parameters']);
            die();
        }

        $results = [];

        if ($id) {
            wc_get_product($id) && $results['product'] = self::formatProduct(wc_get_product($id));

        } elseif ($name) {
            add_filter('woocommerce_product_data_store_cpt_get_products_query', 'handle_custom_query_var_product_name', 10, 2);
            function handle_custom_query_var_product_name($query, $query_vars)
            {
                if (isset($query_vars['like_name']) && !empty($query_vars['like_name'])) {
                    $query['s'] = esc_attr($query_vars['like_name']);
                }
                return $query;
            }

            foreach (wc_get_products([
                'limit' => 10,
                'like_name' => $name,
                'status' => 'publish',
                    ]) as $product) {
                $results[] = self::formatProduct($product);
            }
        }


        remove_filter('woocommerce_product_data_store_cpt_get_products_query', 'handle_custom_query_var_product_name', 10, 2);
        header('Content-type: application/json');
        echo wp_json_encode(['products' => $results ]);
    }


    public static function p596979_operatornaklik_get_transport(WP_REST_Request $request)
    {
        self::checkToken($request['token']);

        $methods = array_map(function ($zone) {
            return $zone['shipping_methods'];
        }, WC_Shipping_Zones::get_zones());

        $results = [];
        foreach (reset($methods) as $method) {
            if (isset($method->enabled) && $method->enabled === 'yes') {
                $results['shippingMethods'][$method->title]['name'] = $method->title;
                $results['shippingMethods'][$method->title]['trackingUrl'] = self::trackingApi($method->title);
                $results['all'][$method->title] = json_decode(wp_json_encode($method), true);
            }
        }

        header('Content-type: application/json');
        echo wp_json_encode(['data' => $results]);
    }


    public static function p596979_operatornaklik_get_payment(WP_REST_Request $request)
    {
        self::checkToken($request['token']);

        $results = [];
        foreach (WC()->payment_gateways->get_available_payment_gateways() as $gateway) {
            $results['paymentMethods'][] = $gateway->get_method_title();
            $results['all'][$gateway->get_method_title()] = json_decode(wp_json_encode($gateway), true);
        }

        header('Content-type: application/json');
        echo wp_json_encode(['data' => $results]);
    }


    private static function checkToken($token)
    {
        if (!isset($token) || $token !== get_option('OPERATORNAKLIK_ACCESS_TOKEN')) {
            header('Content-type: application/json');
            echo wp_json_encode(['error' => 'Bad or missing token']);
            die();
        }
    }


    private static function formatProduct($product)
    {
        $price = strval(number_format(floatval(wc_get_price_including_tax($product)), 2, '.', ''));
        $urlImage = strval(wp_get_attachment_url(get_post_thumbnail_id($product->get_id())));

        return [
            'id' => strval($product->get_id()),
            'type' => $product->get_type(),
            'name' => $product->get_name(),
            'amount' => strval($product->get_stock_quantity()),
            'status' => $product->get_stock_status(),
            'price' => $price,
            'url' => $product->get_permalink(),
            'url_img' => $urlImage,
//			'all' => $product->get_data(),
        ];
    }


    private static function formatOrder($result)
    {
        $items = [];
        foreach ($result->get_items() as $id => $itemObj) {
            $product = wc_get_product($itemObj->get_data()['product_id']);
            $price = strval(number_format(floatval(wc_get_price_including_tax($product)), 2, '.', ''));
            $urlImage = strval(wp_get_attachment_url(get_post_thumbnail_id($product->get_id())));

//			$items[$id] = $product->get_data();
            $items[$id]['itemId'] = strval($product->get_id());
            $items[$id]['itemType'] = $product->get_type();
            $items[$id]['name'] = $product->get_name();
            $items[$id]['amount'] = strval($itemObj->get_data()['quantity']);
            $items[$id]['status'] = ['name' => $product->get_stock_status()];
            $items[$id]['itemPrice'] = ['withVat' => $price];
            $items[$id]['url'] = $product->get_permalink();
            $items[$id]['url_img'] = $urlImage;
        }

        $orderData = $result->get_data();
        $totalPrice = strval(number_format(floatval($orderData['total']), 2, '.', ''));

        $resultData = [
            'code' => strval($orderData['id']),
            'creationTime' => $orderData['date_created']->date('Y-m-d H:i:s'),
            'fullName' => $orderData['billing']['first_name'] . ' ' . $orderData['billing']['last_name'],
            'email' => $orderData['billing']['email'],
            'phone' => $orderData['billing']['phone'],
            'paid' => is_null($orderData['date_paid'])? '' : $orderData['date_paid']->date('Y-m-d H:i:s'),
            'price' => ['withVat' => $totalPrice],
            'shipping' => ['name' => $result->get_shipping_method()],
            'paymentMethod' => ['name' => $orderData['payment_method_title']],
            'status' => ['name' => $result->get_status()],
            'items' => $items,
        ];

        $resultData['billingAddress'] = self::orderAddress('billing', $orderData);
        $resultData['deliveryAddress'] = self::orderAddress('shipping', $orderData);

        return $resultData;
    }


    private static function orderAddress($type, $result)
    {
        $adress = $result[$type]['address_1'];
        $result[$type]['address_2'] && $adress .= ' / '.$result[$type]['address_2'];
        $result[$type]['postcode'] && $adress .= ', '.$result[$type]['postcode'].' '.$result[$type]['city'];
        !$result[$type]['postcode'] && $adress .= ', '.$result[$type]['city'];
        return $adress;
    }


    private static function trackingApi($carrier)
    {

        $carrierConverted = trim(strtolower(strtr(strval($carrier), ['ä'=>'a','Ä'=>'A','á'=>'a','Á'=>'A','à'=>'a',
            'À'=>'A','ã'=>'a','Ã'=>'A','â'=>'a','Â'=>'A','č'=>'c','Č'=>'C','ć'=>'c','Ć'=>'C','ď'=>'d','Ď'=>'D',
            'ě'=>'e','Ě'=>'E','é'=>'e','É'=>'E','ë'=>'e','Ë'=>'E','è'=>'e','È'=>'E','ê'=>'e','Ê'=>'E','í'=>'i',
            'Í'=>'I','ï'=>'i','Ï'=>'I','ì'=>'i','Ì'=>'I','î'=>'i','Î'=>'I','ľ'=>'l','Ľ'=>'L','ĺ'=>'l','Ĺ'=>'L',
            'ń'=>'n','Ń'=>'N','ň'=>'n','Ň'=>'N','ñ'=>'n','Ñ'=>'N','ó'=>'o','Ó'=>'O','ö'=>'o','Ö'=>'O','ô'=>'o',
            'Ô'=>'O','ò'=>'o','Ò'=>'O','õ'=>'o','Õ'=>'O','ő'=>'o','Ő'=>'O','ř'=>'r','Ř'=>'R','ŕ'=>'r','Ŕ'=>'R',
            'š'=>'s','Š'=>'S','ś'=>'s','Ś'=>'S','ť'=>'t','Ť'=>'T','ú'=>'u','Ú'=>'U','ů'=>'u','Ů'=>'U','ü'=>'u',
            'Ü'=>'U','ù'=>'u','Ù'=>'U','ũ'=>'u','Ũ'=>'U','û'=>'u','Û'=>'U','ý'=>'y','Ý'=>'Y','ž'=>'z','Ž'=>'Z',
            'ź'=>'z','Ź'=>'Z'])));

        $apis = [
            'ceska posta' => 'http://www.postaonline.cz/trackandtrace/-/zasilka/#PACKAGE_NUMBER#',
            'ceskaposta' => 'http://www.postaonline.cz/trackandtrace/-/zasilka/#PACKAGE_NUMBER#',
            'ppl' => 'http://www.ppl.cz/main2.aspx?cls=Package&idSearch=#PACKAGE_NUMBER#',
            'dpd' => 'https://tracking.dpd.de/status/cs_CZ/parcel/#PACKAGE_NUMBER#',
            'gls' => 'https://gls-group.eu/CZ/cs/sledovani-zasilek.html?match=#PACKAGE_NUMBER#',
            'dhl' => 'http://www.dhl.cz/content/cz/cs/express/sledovani_zasilek.shtml?AWB=#PACKAGE_NUMBER#&DHL=brand',
            'zasilkovna' => 'https://tracking.packeta.com/cs/tracking/search?id=#PACKAGE_NUMBER#&_fid=4l7j',
            'packeta' => 'https://tracking.packeta.com/cs/tracking/search?id=#PACKAGE_NUMBER#&_fid=4l7j',
        ];

        foreach ($apis as $id => $api) {
            if (strpos($carrierConverted, $id) !== false) {
                return $api;
            }
        }
        return null;
    }
}
