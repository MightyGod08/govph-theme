<?php
/**
 * GovPH Theme 001 Child Theme - Functions
 */

function govph_child_enqueue_styles() {
    wp_enqueue_style(
        'govph-parent-style',
        get_template_directory_uri() . '/style.css'
    );
    
    // Enqueue child theme style
    wp_enqueue_style(
        'govph-child-style',
        get_stylesheet_uri(),
        array( 'govph-parent-style' ),
        wp_get_theme()->get( 'Version' )
    );
}

add_action( 'wp_enqueue_scripts', 'govph_child_enqueue_styles' );
