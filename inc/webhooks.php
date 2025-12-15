<?php
/**
 * Webhook handling for AI Hairstyle Try-On plugin.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Webhook callback for Elementor forms.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function aiht_webhook_callback( $request ) {
    $params = $request->get_params();
    // Determine form type based on form ID or field.
    $exploration_id = get_option( 'aiht_exploration_popup_id' );
    $book_id = get_option( 'aiht_book_popup_id' );
    $form_id = $params['form_id'] ?? ''; // Assume Elementor sends form_id.

    if ( $form_id === $exploration_id ) {
        // Handle exploration: set flag for unlimited (but since local storage, perhaps send JS response).
        // For webhook, perhaps log or add to marketing.
        aiht_log( 'Exploration form submitted.', 'info' );
        // No email, just allow Elementor actions.
    } elseif ( $form_id === $book_id ) {
        // Handle book now.
        $stylist_field = get_option( 'aiht_elementor_stylist_field' );
        $stylist_name = $params[ $stylist_field ] ?? '';
        $stylist = get_posts( array( 'post_type' => 'aiht_staff', 'title' => $stylist_name, 'posts_per_page' => 1 ) );
        $stylist_email = $stylist ? get_post_meta( $stylist[0]->ID, 'aiht_email', true ) : '';
        $primary_email = get_option( 'aiht_primary_email', get_option( 'admin_email' ) );

        // Attachments: original + generated (from transient or params if sent).
        $attachments = array(); // Assume passed in params or use transients.
        // E.g., if user sends images via form, but since webhook, assume Elementor handles, or add logic.

        $subject = 'New Booking Request';
        $body = 'Booking details: ' . print_r( $params, true );
        if ( $stylist_email ) {
            wp_mail( $stylist_email, $subject, $body, '', $attachments );
            wp_mail( $primary_email, $subject, $body, '', $attachments ); // CC.
        } else {
            wp_mail( $primary_email, $subject, $body . "\nStylist: " . $stylist_name, '', $attachments );
        }

        // Clean temps after email.
        foreach ( $attachments as $file ) {
            unlink( $file );
        }

        // Update analytics.
        $total_bookings = get_option( 'aiht_total_bookings', 0 );
        update_option( 'aiht_total_bookings', ++$total_bookings );

        aiht_log( 'Book now form submitted.', 'info' );
    }

    return new WP_REST_Response( 'Success', 200 );
}