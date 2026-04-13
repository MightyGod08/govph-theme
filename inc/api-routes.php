<?php
/**
 * Theme REST API routes.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', 'govph_register_theme_rest_routes');

function govph_register_theme_rest_routes() {
    register_rest_route('govph/v1', '/budget-overview', array(
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'govph_get_budget_overview_data',
            'permission_callback' => '__return_true',
        ),
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'govph_create_budget_overview',
            'permission_callback' => 'govph_can_manage_budget_overview',
            'args'                => array(
                'title' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'content' => array(
                    'required'          => true,
                    'sanitize_callback' => 'wp_kses_post',
                ),
                'year' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'ordinance_no' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'total_budget' => array(
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'pdf_id' => array(
                    'required'          => false,
                    'sanitize_callback' => 'absint',
                ),
                'status' => array(
                    'required'          => false,
                    'default'           => 'publish',
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        ),
    ));
}

function govph_can_manage_budget_overview() {
    return current_user_can('edit_posts');
}

function govph_get_budget_overview_data(WP_REST_Request $request) {
    $per_page = absint($request->get_param('per_page'));

    if ($per_page < 1) {
        $per_page = 5;
    }

    if ($per_page > 50) {
        $per_page = 50;
    }

    $query = new WP_Query(array(
        'post_type'           => 'budget_overview',
        'post_status'         => 'publish',
        'posts_per_page'      => $per_page,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
    ));

    $items = array();

    if ($query->have_posts()) {
        foreach ($query->posts as $post) {
            $items[] = govph_format_budget_overview_item($post->ID);
        }
    }

    wp_reset_postdata();

    return rest_ensure_response($items);
}

function govph_create_budget_overview(WP_REST_Request $request) {
    $status = $request->get_param('status');
    $allowed_statuses = array('publish', 'draft', 'pending', 'private');

    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'draft';
    }

    $post_id = wp_insert_post(array(
        'post_type'    => 'budget_overview',
        'post_status'  => $status,
        'post_title'   => $request->get_param('title'),
        'post_content' => $request->get_param('content'),
    ), true);

    if (is_wp_error($post_id)) {
        return new WP_Error(
            'budget_overview_create_failed',
            'Unable to create Budget Overview.',
            array('status' => 500)
        );
    }

    update_post_meta($post_id, 'budget_overview_year', $request->get_param('year'));
    update_post_meta($post_id, 'budget_overview_ordinance_no', $request->get_param('ordinance_no'));
    update_post_meta($post_id, 'budget_overview_total_budget', $request->get_param('total_budget'));

    $pdf_id = absint($request->get_param('pdf_id'));
    if ($pdf_id > 0) {
        update_post_meta($post_id, 'budget_overview_pdf_id', $pdf_id);
    }

    $response = rest_ensure_response(govph_format_budget_overview_item($post_id));
    $response->set_status(201);

    return $response;
}

function govph_format_budget_overview_item($post_id) {
    $pdf_id        = absint(get_post_meta($post_id, 'budget_overview_pdf_id', true));
    $pdf_url       = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
    $fallback_link = get_permalink($post_id);
    $download_link = $pdf_url ? $pdf_url : $fallback_link;

    return array(
        'id'            => $post_id,
        'date'          => get_the_date('c', $post_id),
        'title'         => get_the_title($post_id),
        'content'       => apply_filters('the_content', get_post_field('post_content', $post_id)),
        'status'        => get_post_status($post_id),
        'link'          => $fallback_link,
        'year'          => get_post_meta($post_id, 'budget_overview_year', true),
        'ordinance_no'  => get_post_meta($post_id, 'budget_overview_ordinance_no', true),
        'total_budget'  => get_post_meta($post_id, 'budget_overview_total_budget', true),
        'pdf_id'        => $pdf_id,
        'pdf_url'       => $pdf_url,
        'download_link' => $download_link,
    );
}
