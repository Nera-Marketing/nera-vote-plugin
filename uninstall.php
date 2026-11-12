<?php
/**
 * Uninstall: keep Votes and Ballots unless removal is asked for explicitly.
 *
 * To delete everything on uninstall, define NERA_VOTE_REMOVE_DATA_ON_UNINSTALL as true
 * in wp-config.php before deleting the plugin.
 *
 * @package Nera_Vote
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'NERA_VOTE_REMOVE_DATA_ON_UNINSTALL' ) || ! NERA_VOTE_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

$vote_ids = get_posts(
	array(
		'post_type'      => 'nera_vote',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $vote_ids as $vote_id ) {
	wp_delete_post( (int) $vote_id, true );
}

$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nera_vote_ballots' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'nera_vote_db_version' );
delete_option( 'nera_vote_demo_hidden' );
delete_option( 'nera_vote_suggest_limit' );
delete_option( 'nera_vote_rules_text' );
delete_option( 'nera_vote_endpoint_version' );
// The Suggest a bottle form, its entries and the Vote page are kept: they hold Members' words and a live page.
delete_option( 'nera_vote_form_id' );
delete_option( 'nera_vote_page_id' );
