<?php
/**
 * Ballot storage. One row per (Vote, Option, Member); the unique key is what
 * stops a Member's Ballot for an Option being counted twice.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Votes {

	const DB_VERSION = '1';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'nera_vote_ballots';
	}

	public static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				vote_id bigint(20) unsigned NOT NULL,
				option_id varchar(32) NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY ballot (vote_id,option_id,user_id),
				KEY vote_id (vote_id),
				KEY user_id (user_id)
			) {$charset};"
		);
		update_option( 'nera_vote_db_version', self::DB_VERSION );
	}

	/**
	 * Returns true when a new Ballot was stored, false when it already existed.
	 *
	 * @param string|null $created_at UTC 'Y-m-d H:i:s'. Defaults to now; the demo tool backdates.
	 */
	public static function add( $vote_id, $option_id, $user_id, $created_at = null ) {
		global $wpdb;
		$rows = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO ' . self::table() . ' (vote_id, option_id, user_id, created_at) VALUES (%d, %s, %d, %s)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$vote_id,
				$option_id,
				$user_id,
				$created_at ? $created_at : current_time( 'mysql', true )
			)
		);
		return 1 === (int) $rows;
	}

	public static function remove( $vote_id, $option_id, $user_id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			self::table(),
			array(
				'vote_id'   => (int) $vote_id,
				'option_id' => (string) $option_id,
				'user_id'   => (int) $user_id,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/** @return string[] Option IDs the Member has ticked. */
	public static function user_option_ids( $vote_id, $user_id ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_id FROM ' . self::table() . ' WHERE vote_id = %d AND user_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$vote_id,
				$user_id
			)
		);
		return array_map( 'strval', (array) $ids );
	}

	/** @return array<string,int> Option ID => number of Ballots. */
	public static function counts( $vote_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT option_id, COUNT(*) AS n FROM ' . self::table() . ' WHERE vote_id = %d GROUP BY option_id', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$vote_id
			)
		);
		$out = array();
		foreach ( (array) $rows as $r ) {
			$out[ (string) $r->option_id ] = (int) $r->n;
		}
		return $out;
	}

	public static function option_count( $vote_id, $option_id ) {
		$counts = self::counts( $vote_id );
		return isset( $counts[ $option_id ] ) ? (int) $counts[ $option_id ] : 0;
	}

	/** Members who hold at least one Ballot on this Vote. */
	public static function voter_count( $vote_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(DISTINCT user_id) FROM ' . self::table() . ' WHERE vote_id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$vote_id
			)
		);
	}

	public static function total( $vote_id ) {
		return array_sum( self::counts( $vote_id ) );
	}

	public static function delete_for_vote( $vote_id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'vote_id' => (int) $vote_id ), array( '%d' ) );
	}

	public static function delete_for_user( $user_id ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), array( 'user_id' => (int) $user_id ), array( '%d' ) );
	}

	/** @return array<int,object> */
	public static function for_user( $user_id ) {
		global $wpdb;
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT vote_id, option_id, created_at FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$user_id
			)
		);
	}
}
