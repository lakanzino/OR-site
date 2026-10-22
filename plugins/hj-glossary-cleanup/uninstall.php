<?php
/**
 * Clean up internal plugin state options on uninstall while preserving all cleaned glossary terms in MySQL.
 *
 * @package HJ_Glossary_Cleanup
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'hj_glossary_cleanup_state_v1' );
delete_option( 'hj_glossary_cleanup_flash_notice' );
delete_transient( 'hj_glossary_cleanup_running_lock' );
