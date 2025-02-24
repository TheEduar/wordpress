<?php

if (!defined('ABSPATH')) {
    exit;
}

// Registrar la tarea cron para el Get de los pedidos desde Imotriz
if (!wp_next_scheduled('consultar_pedidos_imotriz')) {
    wp_schedule_event(time(), 'every_minute', 'consultar_pedidos_imotriz');
}

// Hook
add_action('consultar_pedidos_imotriz', 'consultar_y_procesar_pedidos_imotriz');

// Lapso de tiempo
add_filter('cron_schedules', 'agregar_intervalo_minuto');

function agregar_intervalo_minuto($schedules) {
    $schedules['every_minute'] = array(
        'interval' => 60, // cada minuto
        'display'  => __('Cada minuto')
    );
    return $schedules;
}






// Asigna rproceso
add_action('admin_post_ver_pedidos_recientes', 'consultar_y_procesar_pedidos_imotriz');

function consultar_y_procesar_pedidos_imotriz() {
    $url = 'https://www.imotriz.com/api/cart/transaction/newOrders';
    
    // Token de autenticación
    $api_key = ""; //aqui va el token
    
    // Configuración de los parametros para el Get
    $args = array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => $api_key
        )
    );
    
    // Crear Get
    $response = wp_remote_get($url, $args);
    
    // Check errores
    if (is_wp_error($response)) {
        error_log('Error en la solicitud a iMotriz: ' . $response->get_error_message());
        return;
    }

    //validar si todo esta bien
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (is_null($data)) {
        error_log('Error al decodificar la respuesta JSON de iMotriz.');
        return;
    }
    
    // Procesar los pedidos recibidos
    if (!empty($data['data'])) {
        foreach ($data['data'] as $transaction) {
            // Extraer la información del pedido
            $order_info = $transaction['order'];
            $products = $transaction['products'];
            $buyer = $transaction['buyer'];
            
            // Crear pedido
            $order = wc_create_order();
            
            // Crear el listado de los productos del listado de la respuesta
            foreach ($products as $product) {
                $product_id = wc_get_product_id_by_sku($product['code']);
                
                if ($product_id) {
                    $product_obj = wc_get_product($product_id);
                    $order->add_product($product_obj, $product['quantity'], array(
                        'subtotal' => $product['unit_price_before'],
                        'total'    => $product['unit_price']
                    ));
                } else {
                    $order->add_item(array(
                        'name'     => $product['name'],
                        'quantity' => $product['quantity'],
                        'total'    => $product['unit_price'],
                        'subtotal' => $product['unit_price_before']
                    ));
                }
            }
            
            // Crear cliente
            $order->set_address(array(
                'first_name' => $buyer['name'],
                'last_name'  => '', //
                'email'      => $buyer['email'],
                'phone'      => $buyer['mobile'],
                'address_1'  => $buyer['address'],
                'city'       => $buyer['city'],
                'country'    => 'CO' 
            ), 'billing');

            $order->set_total($order_info['total']);
            $order->update_status('completed');
        }
    }
}



