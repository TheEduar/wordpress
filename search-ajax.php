<?php
/**
 * Plugin Name: Buscador AJAX WooCommerce
 * Description: Agrega un buscador en vivo para productos de WooCommerce con coincidencia difusa y sinónimos.
 * Version: 1.2
 * Author: Eduar Padilla
 */

if (!defined('ABSPATH')) {
    exit;
}

// Registrar los scripts necesarios
function buscador_ajax_woocommerce_scripts() {
    if (!is_admin()) {
        wp_enqueue_script('fuse-js', 'https://cdnjs.cloudflare.com/ajax/libs/fuse.js/6.6.2/fuse.min.js', [], null, true);
        wp_enqueue_script('buscador-ajax', plugin_dir_url(__FILE__) . 'buscador.js', ['jquery', 'fuse-js'], null, true);
        wp_localize_script('buscador-ajax', 'buscadorAjax', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'buscador_ajax_woocommerce_scripts');

// Función para buscar productos con sinónimos
function buscador_ajax_woocommerce() {
    $termino = sanitize_text_field($_POST['termino']);
    
    // Definir sinónimos, en este array se colocan todos los sinonimos
    $sinonimos = [
        'laptop' => ['portátil', 'notebook'],
        'celular' => ['móvil', 'smartphone']
    ];
    
    // Expandir búsqueda con sinónimos
    foreach ($sinonimos as $clave => $variantes) {
        if (in_array(strtolower($termino), $variantes)) {
            $termino = $clave;
            break;
        }
    }

    $args = [
        'post_type' => 'product',
        'posts_per_page' => 10,
        's' => $termino
    ];
    $query = new WP_Query($args);
    
    $resultados = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $resultados[] = [
                'id' => get_the_ID(),
                'titulo' => get_the_title(),
                'url' => get_permalink(),
                'imagen' => get_the_post_thumbnail_url(get_the_ID(), 'thumbnail')
            ];
        }
    }
    wp_reset_postdata();
    
    wp_send_json($resultados);
}
add_action('wp_ajax_buscador_ajax', 'buscador_ajax_woocommerce');
add_action('wp_ajax_nopriv_buscador_ajax', 'buscador_ajax_woocommerce');

// Shortcode para el buscador
function buscador_ajax_shortcode() {
    ob_start();
    ?>
    <div class="buscador-wrapper">
        <input type="text" id="buscador-productos" placeholder="Buscar productos...">
        <div id="resultados-buscador"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('buscador_ajax', 'buscador_ajax_shortcode');
