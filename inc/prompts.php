<?php
/**
 * Prompt templates for AI Hairstyle Try-On plugin.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get description prompt for user image.
 *
 * @return string
 */
function aiht_user_image_desc_prompt() {
    return "Describe this person's appearance in detail, including face shape, skin tone, eye color, body type, pose, clothing, background, but do not describe the hair. Be precise for realistic recreation.";
}

/**
 * Get description prompt for reference hairstyle.
 *
 * @return string
 */
function aiht_ref_hairstyle_desc_prompt() {
    return "Describe this hairstyle in detail, including length, texture, style, layers, volume, and how it sits on the head. Be precise for accurate application.";
}

/**
 * Get generation prompt template.
 *
 * @param string $user_desc User description.
 * @param string $hairstyle_desc Hairstyle description.
 * @param string $color Hex color.
 * @param string $angle Angle (front, back, left, right).
 * @return string
 */
function aiht_generation_prompt( $user_desc, $hairstyle_desc, $color, $angle ) {
    return "Generate a realistic image of a person with {$user_desc}, applying the hairstyle: {$hairstyle_desc} in color {$color}. Make the hairstyle fit naturally to the head shape. Only change the hair; keep the rest authentic. Slight improvements to overall look. Blur background 15-20%. Improve lighting subtly. View from {$angle}. High quality, photorealistic, no animation.";
}