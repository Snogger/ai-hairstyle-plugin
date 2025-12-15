<?php
/**
 * Plugin Name: AI Hairstyle Try-On
 * Plugin URI: https://example.com/ai-hairstyle-tryon
 * Description: A WordPress plugin for AI-powered hairstyle previews using Google Gemini API.
 * Version: 1.0.0
 * Author: xAI Grok
 * Author URI: https://x.ai
 * License: GPL v2 or later
 * Text Domain: ai-hairstyle-tryon
 * Domain Path: /languages
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants.
define( 'AIHT_VERSION', '1.0.0' );
define( 'AIHT_PATH', plugin_dir_path( __FILE__ ) );
define( 'AIHT_URL', plugin_dir_url( __FILE__ ) );

// Load includes.
require_once AIHT_PATH . 'inc/debug.php'; // Debug logging.
require_once AIHT_PATH . 'inc/prompts.php'; // Prompt templates.
require_once AIHT_PATH . 'inc/api.php'; // API integration.
require_once AIHT_PATH . 'inc/webhooks.php'; // Webhook handling.

// Register activation hook to pre-load hairstyles from assets.
register_activation_hook( __FILE__, 'aiht_activate' );
function aiht_activate() {
    aiht_preload_hairstyles_from_assets();
    aiht_log( 'Plugin activated.', 'info' );
}

// Pre-load hairstyles from assets folder on activation.
function aiht_preload_hairstyles_from_assets() {
    // Code to scan assets/men and assets/women, create CPT posts for each style.
    $genders = array( 'men', 'women' );
    foreach ( $genders as $gender ) {
        $dir = AIHT_PATH . 'assets/' . $gender;
        if ( is_dir( $dir ) ) {
            $styles = scandir( $dir );
            foreach ( $styles as $style ) {
                if ( $style !== '.' && $style !== '..' && is_dir( $dir . '/' . $style ) ) {
                    // Create CPT post.
                    $post_id = wp_insert_post( array(
                        'post_title' => ucfirst( $style ),
                        'post_type' => 'aiht_hairstyle',
                        'post_status' => 'publish',
                    ) );
                    if ( ! is_wp_error( $post_id ) ) {
                        update_post_meta( $post_id, 'aiht_gender', $gender );
                        // Add reference images (URLs or attachments).
                        $angles = array( 'front', 'back', 'left', 'right' );
                        foreach ( $angles as $angle ) {
                            $image_path = $dir . '/' . $style . '/' . $angle . '.png';
                            if ( file_exists( $image_path ) ) {
                                // Upload to WP media.
                                $upload = wp_upload_bits( basename( $image_path ), null, file_get_contents( $image_path ) );
                                if ( ! $upload['error'] ) {
                                    $attachment_id = wp_insert_attachment( array(
                                        'post_title' => $style . ' ' . $angle,
                                        'post_content' => '',
                                        'post_status' => 'inherit',
                                        'post_mime_type' => 'image/png',
                                    ), $upload['file'] );
                                    update_post_meta( $post_id, 'aiht_ref_' . $angle, $attachment_id );
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    aiht_log( 'Hairstyles pre-loaded from assets.', 'info' );
}

// Register CPT for Hairstyles.
add_action( 'init', 'aiht_register_cpts' );
function aiht_register_cpts() {
    // Hairstyles CPT.
    register_post_type( 'aiht_hairstyle', array(
        'labels' => array( 'name' => 'Hairstyles', 'singular_name' => 'Hairstyle' ),
        'public' => false,
        'show_ui' => true,
        'menu_position' => 20,
        'supports' => array( 'title' ),
        'capability_type' => 'post',
    ) );

    // Staff CPT.
    register_post_type( 'aiht_staff', array(
        'labels' => array( 'name' => 'Staff', 'singular_name' => 'Stylist' ),
        'public' => false,
        'show_ui' => true,
        'menu_position' => 21,
        'supports' => array( 'title', 'thumbnail' ),
        'capability_type' => 'post',
    ) );
}

// Add metaboxes for Hairstyles.
add_action( 'add_meta_boxes', 'aiht_add_metaboxes' );
function aiht_add_metaboxes() {
    add_meta_box( 'aiht_hairstyle_meta', 'Hairstyle Details', 'aiht_hairstyle_meta_callback', 'aiht_hairstyle' );
    add_meta_box( 'aiht_staff_meta', 'Stylist Details', 'aiht_staff_meta_callback', 'aiht_staff' );
}

function aiht_hairstyle_meta_callback( $post ) {
    wp_nonce_field( 'aiht_meta_nonce', 'aiht_meta_nonce' );
    $gender = get_post_meta( $post->ID, 'aiht_gender', true );
    $alt_names = get_post_meta( $post->ID, 'aiht_alt_names', true );
    $enabled = get_post_meta( $post->ID, 'aiht_enabled', true );
    // Fields for gender, alt names, enable, and 4 image uploads.
    echo '<label for="aiht_gender">Gender:</label><br>';
    echo '<select name="aiht_gender"><option value="male"' . selected( $gender, 'male' ) . '>Male</option><option value="female"' . selected( $gender, 'female' ) . '>Female</option></select><br>';
    echo '<label for="aiht_alt_names">Alternative Names (comma-separated):</label><br>';
    echo '<input type="text" name="aiht_alt_names" value="' . esc_attr( $alt_names ) . '"><br>';
    echo '<label for="aiht_enabled">Enabled:</label><br>';
    echo '<input type="checkbox" name="aiht_enabled" ' . checked( $enabled, true ) . '><br>';
    // Image fields for 4 angles.
    $angles = array( 'front', 'back', 'left', 'right' );
    foreach ( $angles as $angle ) {
        $image_id = get_post_meta( $post->ID, 'aiht_ref_' . $angle, true );
        echo '<label for="aiht_ref_' . $angle . '"> ' . ucfirst( $angle ) . ' Reference Image:</label><br>';
        if ( $image_id ) {
            echo wp_get_attachment_image( $image_id, 'thumbnail' );
        }
        echo '<input type="file" name="aiht_ref_' . $angle . '"><br>';
    }
}

function aiht_staff_meta_callback( $post ) {
    wp_nonce_field( 'aiht_meta_nonce', 'aiht_meta_nonce' );
    $email = get_post_meta( $post->ID, 'aiht_email', true );
    echo '<label for="aiht_email">Email:</label><br>';
    echo '<input type="email" name="aiht_email" value="' . esc_attr( $email ) . '"><br>';
    // Photo is handled by featured image.
}

add_action( 'save_post', 'aiht_save_meta' );
function aiht_save_meta( $post_id ) {
    if ( ! wp_verify_nonce( $_POST['aiht_meta_nonce'], 'aiht_meta_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( current_user_can( 'edit_post', $post_id ) ) {
        // Save hairstyle fields.
        if ( get_post_type( $post_id ) == 'aiht_hairstyle' ) {
            update_post_meta( $post_id, 'aiht_gender', sanitize_text_field( $_POST['aiht_gender'] ) );
            update_post_meta( $post_id, 'aiht_alt_names', sanitize_text_field( $_POST['aiht_alt_names'] ) );
            update_post_meta( $post_id, 'aiht_enabled', isset( $_POST['aiht_enabled'] ) ? true : false );
            // Handle image uploads.
            $angles = array( 'front', 'back', 'left', 'right' );
            foreach ( $angles as $angle ) {
                if ( ! empty( $_FILES['aiht_ref_' . $angle]['name'] ) ) {
                    $upload = wp_upload_bits( $_FILES['aiht_ref_' . $angle]['name'], null, file_get_contents( $_FILES['aiht_ref_' . $angle]['tmp_name'] ) );
                    if ( ! $upload['error'] ) {
                        $attachment_id = wp_insert_attachment( array(
                            'post_title' => sanitize_file_name( $_FILES['aiht_ref_' . $angle]['name'] ),
                            'post_content' => '',
                            'post_status' => 'inherit',
                            'post_mime_type' => $_FILES['aiht_ref_' . $angle]['type'],
                        ), $upload['file'] );
                        update_post_meta( $post_id, 'aiht_ref_' . $angle, $attachment_id );
                    }
                }
            }
        } elseif ( get_post_type( $post_id ) == 'aiht_staff' ) {
            update_post_meta( $post_id, 'aiht_email', sanitize_email( $_POST['aiht_email'] ) );
        }
    }
}

// Add plugin menu.
add_action( 'admin_menu', 'aiht_admin_menu' );
function aiht_admin_menu() {
    add_menu_page( 'AI Hairstyle Try-On', 'AI Hairstyle Try-On', 'manage_options', 'aiht', 'aiht_config_page', 'dashicons-art', 20 );
    add_submenu_page( 'aiht', 'Configuration', 'Configuration', 'manage_options', 'aiht', 'aiht_config_page' );
    add_submenu_page( 'aiht', 'Hairstyles', 'Hairstyles', 'manage_options', 'edit.php?post_type=aiht_hairstyle' );
    add_submenu_page( 'aiht', 'Staff', 'Staff', 'manage_options', 'edit.php?post_type=aiht_staff' );
    add_submenu_page( 'aiht', 'Analytics', 'Analytics', 'manage_options', 'aiht_analytics', 'aiht_analytics_page' );
    add_submenu_page( 'aiht', 'Styling', 'Styling', 'manage_options', 'aiht_styling', 'aiht_styling_page' );
}

// Configuration page.
function aiht_config_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( isset( $_POST['aiht_save_config'] ) ) {
        check_admin_referer( 'aiht_config_nonce' );
        update_option( 'aiht_gender', sanitize_text_field( $_POST['aiht_gender'] ) );
        update_option( 'aiht_primary_email', sanitize_email( $_POST['aiht_primary_email'] ) );
        update_option( 'aiht_gemini_api_key', sanitize_text_field( $_POST['aiht_gemini_api_key'] ) ); // Secure? Use WP's options, but for API key, consider encryption if needed.
        update_option( 'aiht_free_limit', intval( $_POST['aiht_free_limit'] ) );
        update_option( 'aiht_exploration_popup_id', sanitize_text_field( $_POST['aiht_exploration_popup_id'] ) );
        update_option( 'aiht_book_popup_id', sanitize_text_field( $_POST['aiht_book_popup_id'] ) );
        // Webhook mappings.
        update_option( 'aiht_elementor_stylist_field', sanitize_text_field( $_POST['aiht_elementor_stylist_field'] ) );
        // Watermark logo.
        if ( ! empty( $_FILES['aiht_watermark_logo']['name'] ) ) {
            $upload = wp_upload_bits( $_FILES['aiht_watermark_logo']['name'], null, file_get_contents( $_FILES['aiht_watermark_logo']['tmp_name'] ) );
            if ( ! $upload['error'] ) {
                update_option( 'aiht_watermark_logo', $upload['url'] );
            }
        }
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    $gender = get_option( 'aiht_gender', 'both' );
    $primary_email = get_option( 'aiht_primary_email', get_option( 'admin_email' ) );
    $api_key = get_option( 'aiht_gemini_api_key' );
    $free_limit = get_option( 'aiht_free_limit', 2 );
    $exploration_popup_id = get_option( 'aiht_exploration_popup_id' );
    $book_popup_id = get_option( 'aiht_book_popup_id' );
    $elementor_stylist_field = get_option( 'aiht_elementor_stylist_field' );
    $watermark_logo = get_option( 'aiht_watermark_logo' );
    $webhook_url = home_url( '/wp-json/aiht/v1/webhook' );
    ?>
    <div class="wrap">
        <h1>AI Hairstyle Try-On Configuration</h1>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'aiht_config_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th>Gender</th>
                    <td>
                        <label><input type="radio" name="aiht_gender" value="male" <?php checked( $gender, 'male' ); ?>> Male</input></label>
                        <label><input type="radio" name="aiht_gender" value="female" <?php checked( $gender, 'female' ); ?>> Female</input></label>
                        <label><input type="radio" name="aiht_gender" value="both" <?php checked( $gender, 'both' ); ?>> Both</input></label>
                    </td>
                </tr>
                <tr>
                    <th>Primary Email</th>
                    <td><input type="email" name="aiht_primary_email" value="<?php echo esc_attr( $primary_email ); ?>"></td>
                </tr>
                <tr>
                    <th>Gemini API Key</th>
                    <td><input type="password" name="aiht_gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>"></td>
                </tr>
                <tr>
                    <th>Free Generation Limit</th>
                    <td><input type="number" name="aiht_free_limit" value="<?php echo esc_attr( $free_limit ); ?>"></td>
                </tr>
                <tr>
                    <th>Exploration Popup/Form ID</th>
                    <td><input type="text" name="aiht_exploration_popup_id" value="<?php echo esc_attr( $exploration_popup_id ); ?>"></td>
                </tr>
                <tr>
                    <th>Book Now Popup/Form ID</th>
                    <td><input type="text" name="aiht_book_popup_id" value="<?php echo esc_attr( $book_popup_id ); ?>"></td>
                </tr>
                <tr>
                    <th>Elementor Stylist Field Name</th>
                    <td><input type="text" name="aiht_elementor_stylist_field" value="<?php echo esc_attr( $elementor_stylist_field ); ?>"></td>
                </tr>
                <tr>
                    <th>Webhook URL (auto-generated)</th>
                    <td><input type="text" value="<?php echo esc_url( $webhook_url ); ?>" readonly></td>
                </tr>
                <tr>
                    <th>Watermark Logo</th>
                    <td>
                        <?php if ( $watermark_logo ) echo '<img src="' . esc_url( $watermark_logo ) . '" style="max-width:100px;">'; ?>
                        <input type="file" name="aiht_watermark_logo">
                    </td>
                </tr>
            </table>
            <input type="submit" name="aiht_save_config" value="Save Settings" class="button-primary">
        </form>
    </div>
    <?php
}

// Styling page.
function aiht_styling_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( isset( $_POST['aiht_save_styling'] ) ) {
        check_admin_referer( 'aiht_styling_nonce' );
        update_option( 'aiht_custom_css', wp_kses_post( $_POST['aiht_custom_css'] ) );
        echo '<div class="updated"><p>Styling saved.</p></div>';
    }
    $custom_css = get_option( 'aiht_custom_css', '' );
    if ( empty( $custom_css ) ) {
        $custom_css = "/* Placeholder CSS - Adjust as needed */\n" .
            ".aiht-tabs { display: flex; justify-content: center; }\n" .
            ".aiht-hairstyle-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }\n" .
            ".aiht-upload-field { border: 2px dashed #ccc; padding: 20px; }\n" .
            ".aiht-color-picker { width: 100px; }\n" .
            ".aiht-gallery { display: grid; grid-template-columns: repeat(4, 1fr); }\n" .
            ".aiht-loading-spinner { display: none; animation: spin 1s linear infinite; }\n" .
            "/* Add more selectors as needed: .aiht-book-button, .aiht-download-button, .aiht-reset-button */\n";
    }
    ?>
    <div class="wrap">
        <h1>Custom Styling</h1>
        <form method="post">
            <?php wp_nonce_field( 'aiht_styling_nonce' ); ?>
            <textarea name="aiht_custom_css" rows="20" cols="80"><?php echo esc_textarea( $custom_css ); ?></textarea>
            <p>Use the selectors above to customize. This CSS will be enqueued on the frontend.</p>
            <input type="submit" name="aiht_save_styling" value="Save Styling" class="button-primary">
        </form>
    </div>
    <?php
}

// Enqueue custom CSS.
add_action( 'wp_head', 'aiht_enqueue_custom_css' );
function aiht_enqueue_custom_css() {
    $custom_css = get_option( 'aiht_custom_css' );
    if ( $custom_css ) {
        echo '<style>' . $custom_css . '</style>';
    }
}

// Analytics page.
function aiht_analytics_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    // Fetch totals from options or transients (increment on events).
    $generations = get_option( 'aiht_total_generations', 0 );
    $bookings = get_option( 'aiht_total_bookings', 0 );
    $api_calls = get_option( 'aiht_total_api_calls', 0 );
    // Popular hairstyles: use array in option.
    $popular = get_option( 'aiht_popular_hairstyles', array() );
    ?>
    <div class="wrap">
        <h1>Analytics</h1>
        <p>Total Generations: <?php echo esc_html( $generations ); ?></p>
        <p>Total Bookings: <?php echo esc_html( $bookings ); ?></p>
        <p>Total API Calls: <?php echo esc_html( $api_calls ); ?></p>
        <h2>Popular Hairstyles</h2>
        <ul>
            <?php foreach ( $popular as $id => $count ) { 
                $title = get_the_title( $id );
                echo '<li>' . esc_html( $title ) . ': ' . esc_html( $count ) . '</li>'; 
            } ?>
        </ul>
        <form method="post">
            <input type="submit" name="aiht_export_csv" value="Export CSV" class="button">
        </form>
        <?php
        if ( isset( $_POST['aiht_export_csv'] ) ) {
            // Generate CSV.
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="aiht_analytics.csv"' );
            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, array( 'Metric', 'Value' ) );
            fputcsv( $output, array( 'Generations', $generations ) );
            fputcsv( $output, array( 'Bookings', $bookings ) );
            fputcsv( $output, array( 'API Calls', $api_calls ) );
            fputcsv( $output, array( 'Popular Hairstyles' ) );
            foreach ( $popular as $id => $count ) {
                fputcsv( $output, array( get_the_title( $id ), $count ) );
            }
            fclose( $output );
            exit;
        }
        ?>
    </div>
    <?php
}

// Shortcode for frontend.
add_shortcode( 'ai-hairstyle-tryon', 'aiht_shortcode' );
function aiht_shortcode() {
    // Enqueue JS and CSS.
    wp_enqueue_script( 'aiht-frontend-js', AIHT_URL . 'js/frontend.js', array( 'jquery' ), AIHT_VERSION, true );
    wp_enqueue_style( 'aiht-frontend-css', AIHT_URL . 'css/frontend.css', array(), AIHT_VERSION );
    // Localize data.
    wp_localize_script( 'aiht-frontend-js', 'aiht_data', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'free_limit' => get_option( 'aiht_free_limit', 2 ),
        'exploration_popup_id' => get_option( 'aiht_exploration_popup_id' ),
        'book_popup_id' => get_option( 'aiht_book_popup_id' ),
        'staff' => aiht_get_staff(),
    ) );

    $gender = get_option( 'aiht_gender', 'both' );
    $hairstyles = get_posts( array( 'post_type' => 'aiht_hairstyle', 'posts_per_page' => -1, 'meta_query' => array( array( 'key' => 'aiht_enabled', 'value' => true ) ) ) );

    ob_start();
    ?>
    <div class="aiht-container">
        <?php if ( $gender === 'both' ) : ?>
            <div class="aiht-tabs">
                <button class="aiht-tab active" data-gender="male">Male</button>
                <button class="aiht-tab" data-gender="female">Female</button>
            </div>
        <?php endif; ?>
        <div class="aiht-hairstyle-list">
            <?php foreach ( $hairstyles as $style ) {
                $style_gender = get_post_meta( $style->ID, 'aiht_gender', true );
                if ( $gender === 'both' || $gender === $style_gender ) {
                    echo '<div class="aiht-style-item" data-style-id="' . esc_attr( $style->ID ) . '" data-gender="' . esc_attr( $style_gender ) . '">' . esc_html( $style->post_title ) . '</div>';
                }
            } ?>
        </div>
        <div class="aiht-upload-field">
            <p>Upload Your Photo(s) (up to 4: front, back, left, right)</p>
            <input type="file" multiple accept="image/jpeg,image/png" id="aiht-upload">
        </div>
        <div class="aiht-color-picker">
            <label>Choose Color:</label>
            <input type="color" id="aiht-color" value="#000000">
        </div>
        <div class="aiht-gallery"></div>
        <button class="aiht-book-button" style="display:none;">Book Now</button>
        <button class="aiht-download-button" style="display:none;">Download</button>
        <button class="aiht-reset-button">Reset</button>
        <div class="aiht-loading-spinner">Loading...</div>
    </div>
    <?php
    return ob_get_clean();
}

// Get staff for frontend.
function aiht_get_staff() {
    $staff = get_posts( array( 'post_type' => 'aiht_staff', 'posts_per_page' => -1 ) );
    $data = array();
    foreach ( $staff as $person ) {
        $data[] = array(
            'id' => $person->ID,
            'name' => $person->post_title,
            'photo' => get_the_post_thumbnail_url( $person->ID, 'thumbnail' ),
            'email' => get_post_meta( $person->ID, 'aiht_email', true ),
        );
    }
    return $data;
}

// AJAX handler for generation.
add_action( 'wp_ajax_aiht_generate', 'aiht_generate_handler' );
add_action( 'wp_ajax_nopriv_aiht_generate', 'aiht_generate_handler' );
function aiht_generate_handler() {
    check_ajax_referer( 'aiht_nonce', 'nonce' ); // Add nonce in JS.
    // Get data: style_id, color, uploads (handle files).
    $style_id = intval( $_POST['style_id'] );
    $color = sanitize_hex_color( $_POST['color'] );
    // Handle file uploads.
    $uploads = array();
    if ( ! empty( $_FILES['uploads'] ) ) {
        $files = $_FILES['uploads'];
        for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
            $tmp_name = $files['tmp_name'][ $i ];
            $name = sanitize_file_name( $files['name'][ $i ] );
            $upload_dir = wp_upload_dir();
            $temp_path = $upload_dir['basedir'] . '/aiht_temp/' . $name;
            wp_mkdir_p( $upload_dir['basedir'] . '/aiht_temp/' );
            move_uploaded_file( $tmp_name, $temp_path );
            $uploads[] = $temp_path;
        }
    }
    // Call API.
    $generated = aiht_call_gemini_api( $style_id, $color, $uploads );
    if ( is_wp_error( $generated ) ) {
        aiht_log( $generated->get_error_message() );
        wp_send_json_error( $generated->get_error_message() );
    }
    // Update analytics.
    $total_generations = get_option( 'aiht_total_generations', 0 );
    update_option( 'aiht_total_generations', ++$total_generations );
    $popular = get_option( 'aiht_popular_hairstyles', array() );
    $popular[ $style_id ] = isset( $popular[ $style_id ] ) ? $popular[ $style_id ] + 1 : 1;
    update_option( 'aiht_popular_hairstyles', $popular );
    $api_calls = get_option( 'aiht_total_api_calls', 0 );
    update_option( 'aiht_total_api_calls', $api_calls + count( $generated ) ); // Approx.
    // Clean temps.
    foreach ( $uploads as $file ) {
        unlink( $file );
    }
    wp_send_json_success( $generated );
}

// Register webhook REST endpoint.
add_action( 'rest_api_init', 'aiht_register_webhook' );
function aiht_register_webhook() {
    register_rest_route( 'aiht/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'aiht_webhook_callback',
        'permission_callback' => '__return_true', // Secure if needed.
    ) );
}

// We'll add more in other files.
