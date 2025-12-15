<?php
/**
 * API integration for AI Hairstyle Try-On plugin.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Call Gemini API to generate images.
 *
 * @param int $style_id Hairstyle post ID.
 * @param string $color Hex color.
 * @param array $uploads Array of temp file paths for user uploads (1-4).
 * @return array|WP_Error Array of generated image URLs or error.
 */
function aiht_call_gemini_api( $style_id, $color, $uploads ) {
    $api_key = get_option( 'aiht_gemini_api_key' );
    if ( empty( $api_key ) ) {
        return new WP_Error( 'no_api_key', 'API key not set.' );
    }

    $angles = array( 'front', 'back', 'left', 'right' );
    $user_uploads = array_pad( $uploads, 4, $uploads[0] ?? '' ); // Fallback to first for missing.
    $ref_images = array();
    foreach ( $angles as $angle ) {
        $ref_id = get_post_meta( $style_id, 'aiht_ref_' . $angle, true );
        $ref_images[ $angle ] = wp_get_attachment_url( $ref_id );
    }

    $generated = array();
    foreach ( $angles as $idx => $angle ) {
        $user_file = $user_uploads[ $idx ];
        if ( empty( $user_file ) ) {
            $user_file = $user_uploads[0]; // Approximate.
        }

        // Step 1: Describe user image using Gemini vision.
        $user_desc = aiht_describe_image( $api_key, $user_file, aiht_user_image_desc_prompt() );
        if ( is_wp_error( $user_desc ) ) {
            aiht_log( $user_desc->get_error_message() );
            return $user_desc;
        }

        // Step 2: Describe reference hairstyle.
        $ref_file = $ref_images[ $angle ];
        $hairstyle_desc = aiht_describe_image( $api_key, $ref_file, aiht_ref_hairstyle_desc_prompt() );
        if ( is_wp_error( $hairstyle_desc ) ) {
            aiht_log( $hairstyle_desc->get_error_message() );
            return $hairstyle_desc;
        }

        // Step 3: Generate image using Imagen.
        $prompt = aiht_generation_prompt( $user_desc, $hairstyle_desc, $color, $angle );
        $image_data = aiht_generate_image( $api_key, $prompt );
        if ( is_wp_error( $image_data ) ) {
            aiht_log( $image_data->get_error_message() );
            return $image_data;
        }

        // Save generated image temporarily.
        $upload_dir = wp_upload_dir();
        $temp_path = $upload_dir['basedir'] . '/aiht_temp/generated_' . $angle . '_' . time() . '.png';
        file_put_contents( $temp_path, base64_decode( $image_data ) );
        $generated[ $angle ] = $upload_dir['baseurl'] . '/aiht_temp/generated_' . $angle . '_' . time() . '.png';

        // Apply watermark if set (but no local processing, so skip or use API if possible; for now, note in comments).
        // Note: Watermark requires local processing, but spec forbids. Perhaps add in prompt: "Add semi-transparent logo in bottom corner: [describe logo]".
    }

    // Costs/privacy: Images sent to Google; assume user consent via site policy.
    return $generated;
}

/**
 * Describe an image using Gemini API.
 *
 * @param string $api_key
 * @param string $image_path Local path to image.
 * @param string $prompt
 * @return string|WP_Error Description or error.
 */
function aiht_describe_image( $api_key, $image_path, $prompt ) {
    $image_data = base64_encode( file_get_contents( $image_path ) );
    $mime = wp_check_filetype( $image_path )['type'];

    $data = array(
        'contents' => array(
            array(
                'parts' => array(
                    array( 'text' => $prompt ),
                    array(
                        'inlineData' => array(
                            'mimeType' => $mime,
                            'data' => $image_data,
                        ),
                    ),
                ),
            ),
        ),
    );

    $response = aiht_curl_request( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key, $data );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $json = json_decode( $response, true );
    if ( isset( $json['candidates'][0]['content']['parts'][0]['text'] ) ) {
        return $json['candidates'][0]['content']['parts'][0]['text'];
    }

    return new WP_Error( 'api_error', 'Failed to get description.' );
}

/**
 * Generate image using Imagen via Gemini API.
 *
 * @param string $api_key
 * @param string $prompt
 * @return string|WP_Error Base64 image data or error.
 */
function aiht_generate_image( $api_key, $prompt ) {
    $data = array(
        'instances' => array(
            array( 'prompt' => $prompt ),
        ),
        'parameters' => array(
            'sampleCount' => 1,
        ),
    );

    $model = 'imagen-3.0-generate-002'; // From docs.
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':predict?key=' . $api_key;

    $response = aiht_curl_request( $url, $data );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $json = json_decode( $response, true );
    if ( isset( $json['predictions'][0]['bytesBase64Encoded'] ) ) {
        return $json['predictions'][0]['bytesBase64Encoded'];
    }

    return new WP_Error( 'api_error', 'Failed to generate image.' );
}

/**
 * Helper for cURL request with retry.
 *
 * @param string $url
 * @param array $data
 * @return string|WP_Error Response body or error.
 */
function aiht_curl_request( $url, $data ) {
    $retries = 3;
    while ( $retries > 0 ) {
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json' ) );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
        $response = curl_exec( $ch );
        $error = curl_error( $ch );
        curl_close( $ch );

        if ( $error ) {
            $retries--;
            if ( $retries === 0 ) {
                // Notify admin if quota exceeded or persistent.
                $admin_email = get_option( 'aiht_primary_email', get_option( 'admin_email' ) );
                wp_mail( $admin_email, 'AIHT API Error', $error );
                return new WP_Error( 'curl_error', $error );
            }
            sleep( 2 ); // Backoff.
        } else {
            return $response;
        }
    }
}