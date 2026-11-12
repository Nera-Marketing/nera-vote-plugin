<?php
/**
 * Creates the Ballot table on activation and on upgrade, and the Suggest a bottle form and the Vote page on activation.
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
		// The Suggest a bottle form and the Vote page are each created once; activating again finds them.
		Nera_Vote_Suggestions::ensure_form();
		Nera_Vote_Page::ensure_page();
		// The My account tab needs its rewrite rule; clearing the stamp makes init rebuild the rules.
		delete_option( Nera_Vote_Account::OPTION_FLUSHED );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'nera_vote_db_version' ) !== Nera_Vote_Votes::DB_VERSION ) {
			Nera_Vote_Votes::create_table();
		}
	}
}
