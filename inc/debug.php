<?php
/**
 * Debug logging helper for AI Hairstyle Try-On plugin.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Log a message to debug.log in the plugin root.
 *
 * @param string $message The message to log.
 * @param string $level The log level (e.g., 'error', 'info'). Default 'error'.
 */
function aiht_log( $message, $level = 'error' ) {
    $plugin_dir = plugin_dir_path( dirname( __FILE__ ) . '/..' ); // Get root plugin dir.
    $log_file = $plugin_dir . 'debug.log';
    $timestamp = gmdate( 'Y-m-d H:i:s' );
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;

    // Append to file (create if not exists).
    file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );
}