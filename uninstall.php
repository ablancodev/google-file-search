<?php
/**
 * Uninstall script
 * Fired when the plugin is uninstalled
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('gfs_gemini_api_key');
delete_option('gfs_corpus_id');
delete_option('gfs_auto_sync');
delete_option('gfs_sync_on_save');
delete_option('gfs_last_bulk_sync');
delete_option('gfs_last_bulk_sync_results');

// Delete product meta
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_gfs_document_id'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_gfs_last_sync'");

// Drop custom table
$table_name = $wpdb->prefix . 'gfs_sync_log';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Clear any cached data
wp_cache_flush();
