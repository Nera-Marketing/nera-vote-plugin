<?php
/**
 * Creates the Ballot table on activation and on upgrade.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Activator {

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
	}

	public static function activate() {
		Nera_Vote_Votes::create_table();
	}

	public static function maybe_upgrade() {
		if ( get_option( 'nera_vote_db_version' ) !== Nera_Vote_Votes::DB_VERSION ) {
			Nera_Vote_Votes::create_table();
		}
	}
}
