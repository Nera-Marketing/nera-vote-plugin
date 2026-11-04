<?php
/**
 * Hooks Ballots into the WordPress personal-data export and erase tools.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'delete_user', array( 'Nera_Vote_Votes', 'delete_for_user' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['nera-vote'] = array(
			'exporter_friendly_name' => __( 'Nera – Vote ballots', 'nera-vote' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['nera-vote'] = array(
			'eraser_friendly_name' => __( 'Nera – Vote ballots', 'nera-vote' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			foreach ( Nera_Vote_Votes::for_user( $user->ID ) as $ballot ) {
				$name = $ballot->option_id;
				foreach ( Nera_Vote_CPT::options( (int) $ballot->vote_id ) as $opt ) {
					if ( $opt['id'] === $ballot->option_id ) {
						$name = $opt['name'];
						break;
					}
				}
				$data[] = array(
					'group_id'    => 'nera-vote-ballots',
					'group_label' => __( 'Vote ballots', 'nera-vote' ),
					'item_id'     => 'nera-vote-' . $ballot->vote_id . '-' . $ballot->option_id,
					'data'        => array(
						array( 'name' => __( 'Vote', 'nera-vote' ), 'value' => get_the_title( (int) $ballot->vote_id ) ),
						array( 'name' => __( 'Option', 'nera-vote' ), 'value' => $name ),
						array( 'name' => __( 'Date (UTC)', 'nera-vote' ), 'value' => $ballot->created_at ),
					),
				);
			}
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function erase( $email ) {
		$user    = get_user_by( 'email', $email );
		$removed = $user ? Nera_Vote_Votes::delete_for_user( $user->ID ) : 0;
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
