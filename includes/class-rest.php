<?php
/**
 * REST endpoints used by the front end. Cookie authentication with the
 * standard wp_rest nonce; only signed-in Members can read state or cast Ballots.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_REST {

	const NAMESPACE_V1 = 'nera-vote/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/votes/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_state' ),
				'permission_callback' => array( __CLASS__, 'must_be_member' ),
			)
		);
		register_rest_route(
			self::NAMESPACE_V1,
			'/votes/(?P<id>\d+)/ballot',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'set_ballot' ),
				'permission_callback' => array( __CLASS__, 'must_be_member' ),
				'args'                => array(
					'option_id' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'checked'   => array(
						'required'          => true,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);
	}

	public static function must_be_member() {
		return is_user_logged_in();
	}

	private static function vote( WP_REST_Request $request ) {
		$vote_id = (int) $request['id'];
		$post    = get_post( $vote_id );
		if ( ! $post || Nera_Vote_CPT::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'nera_vote_not_found', __( 'This Vote does not exist.', 'nera-vote' ), array( 'status' => 404 ) );
		}
		return $vote_id;
	}

	public static function state( $vote_id, $user_id ) {
		$option_ids = array_map(
			static function ( $o ) {
				return $o['id'];
			},
			Nera_Vote_CPT::visible_options( $vote_id )
		);
		$mine   = array_values( array_intersect( Nera_Vote_Votes::user_option_ids( $vote_id, $user_id ), $option_ids ) );
		$result = array(
			'status'  => Nera_Vote_CPT::status( $vote_id ),
			'mode'    => Nera_Vote_CPT::choice_mode( $vote_id ),
			'checked' => $mine,
			'results' => null,
		);
		if ( Nera_Vote_CPT::members_can_see_results( $vote_id ) ) {
			$result['results'] = array();
			foreach ( Nera_Vote_Results::rows( $vote_id, false ) as $row ) {
				$result['results'][ $row['id'] ] = array(
					'count'      => $row['count'],
					'pctVoters'  => $row['pct_voters'],
					'pctLeader'  => $row['pct_leader'],
					'band'       => $row['band'],
				);
			}
		}
		return $result;
	}

	public static function get_state( WP_REST_Request $request ) {
		$vote_id = self::vote( $request );
		if ( is_wp_error( $vote_id ) ) {
			return $vote_id;
		}
		return rest_ensure_response( self::state( $vote_id, get_current_user_id() ) );
	}

	public static function set_ballot( WP_REST_Request $request ) {
		$vote_id = self::vote( $request );
		if ( is_wp_error( $vote_id ) ) {
			return $vote_id;
		}
		if ( 'open' !== Nera_Vote_CPT::status( $vote_id ) ) {
			return new WP_Error( 'nera_vote_not_open', __( 'This Vote is not open.', 'nera-vote' ), array( 'status' => 409 ) );
		}

		$option_id = (string) $request['option_id'];
		if ( ! Nera_Vote_CPT::has_option( $vote_id, $option_id, true ) ) {
			return new WP_Error( 'nera_vote_bad_option', __( 'That Option is not on this Vote.', 'nera-vote' ), array( 'status' => 400 ) );
		}

		$user_id = get_current_user_id();
		$single  = ( 'single' === Nera_Vote_CPT::choice_mode( $vote_id ) );
		if ( $request['checked'] ) {
			if ( $single ) {
				// One Ballot only: choosing another Option moves it, in this one request.
				foreach ( Nera_Vote_Votes::user_option_ids( $vote_id, $user_id ) as $other ) {
					if ( $other !== $option_id ) {
						Nera_Vote_Votes::remove( $vote_id, $other, $user_id );
					}
				}
			}
			Nera_Vote_Votes::add( $vote_id, $option_id, $user_id );
		} elseif ( $single ) {
			return new WP_Error( 'nera_vote_single_no_withdraw', __( 'In this Vote you can change your choice but not withdraw it.', 'nera-vote' ), array( 'status' => 409 ) );
		} else {
			Nera_Vote_Votes::remove( $vote_id, $option_id, $user_id );
		}

		return rest_ensure_response( self::state( $vote_id, $user_id ) );
	}
}
