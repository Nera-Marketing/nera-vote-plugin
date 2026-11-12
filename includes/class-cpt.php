<?php
/**
 * The Vote post type and the accessors for everything stored on it.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_CPT {

	const POST_TYPE = 'nera_vote';

	const META_START        = '_nera_vote_start';
	const META_END          = '_nera_vote_end';
	const META_SHOW_RESULTS = '_nera_vote_show_results';
	const META_PLAY_SOUND   = '_nera_vote_play_sound';
	const META_OPTIONS      = '_nera_vote_options';
	const META_CHOICE_MODE  = '_nera_vote_choice_mode';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
	}

	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Votes', 'nera-vote' ),
					'singular_name' => __( 'Vote', 'nera-vote' ),
					'add_new_item'  => __( 'Add new Vote', 'nera-vote' ),
					'edit_item'     => __( 'Edit Vote', 'nera-vote' ),
					'new_item'      => __( 'New Vote', 'nera-vote' ),
					'all_items'     => __( 'All Votes', 'nera-vote' ),
					'search_items'  => __( 'Search Votes', 'nera-vote' ),
					'not_found'     => __( 'No Votes found.', 'nera-vote' ),
					'menu_name'     => __( 'Votes', 'nera-vote' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-thumbs-up',
				'menu_position'   => 58,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
			)
		);
	}

	/** Deleting a whole Vote deletes its Ballots too. */
	public static function on_delete( $post_id ) {
		if ( get_post_type( $post_id ) === self::POST_TYPE ) {
			Nera_Vote_Votes::delete_for_vote( (int) $post_id );
		}
	}

	/** Unix timestamp (UTC) or 0 when not set. */
	public static function start( $vote_id ) {
		return (int) get_post_meta( $vote_id, self::META_START, true );
	}

	public static function end( $vote_id ) {
		return (int) get_post_meta( $vote_id, self::META_END, true );
	}

	/** scheduled | open | closed. An empty start is open from now; an empty end never closes. */
	public static function status( $vote_id, $now = null ) {
		$now   = null === $now ? time() : (int) $now;
		$start = self::start( $vote_id );
		$end   = self::end( $vote_id );
		if ( $start && $now < $start ) {
			return 'scheduled';
		}
		if ( $end && $now >= $end ) {
			return 'closed';
		}
		return 'open';
	}

	/** after_close | while_open */
	public static function show_results_mode( $vote_id ) {
		$mode = get_post_meta( $vote_id, self::META_SHOW_RESULTS, true );
		return 'while_open' === $mode ? 'while_open' : 'after_close';
	}

	/** multiple (default, also for a Vote saved before the setting existed) | single */
	public static function choice_mode( $vote_id ) {
		return 'single' === get_post_meta( $vote_id, self::META_CHOICE_MODE, true ) ? 'single' : 'multiple';
	}

	/** The Choice mode is locked once any Member has cast a Ballot. */
	public static function choice_mode_locked( $vote_id ) {
		return Nera_Vote_Votes::total( $vote_id ) > 0;
	}

	public static function play_sound( $vote_id ) {
		return 'no' !== get_post_meta( $vote_id, self::META_PLAY_SOUND, true );
	}

	/**
	 * `source` is the normalised bottle and brand of the Suggestion an Option came from ('' for typed Options).
	 *
	 * @return array<int,array{id:string,name:string,image_id:int,description:string,link:string,hidden:bool,source:string}>
	 */
	public static function options( $vote_id ) {
		$raw = get_post_meta( $vote_id, self::META_OPTIONS, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $opt ) {
			if ( ! is_array( $opt ) || empty( $opt['id'] ) || ! isset( $opt['name'] ) ) {
				continue;
			}
			$out[] = array(
				'id'          => (string) $opt['id'],
				'name'        => (string) $opt['name'],
				'image_id'    => isset( $opt['image_id'] ) ? (int) $opt['image_id'] : 0,
				'description' => isset( $opt['description'] ) ? (string) $opt['description'] : '',
				'link'        => isset( $opt['link'] ) ? (string) $opt['link'] : '',
				'hidden'      => ! empty( $opt['hidden'] ),
				'source'      => isset( $opt['source'] ) ? (string) $opt['source'] : '',
			);
		}
		return $out;
	}

	/** Options Members can see. */
	public static function visible_options( $vote_id ) {
		return array_values(
			array_filter(
				self::options( $vote_id ),
				static function ( $o ) {
					return ! $o['hidden'];
				}
			)
		);
	}

	public static function has_option( $vote_id, $option_id, $visible_only = true ) {
		$list = $visible_only ? self::visible_options( $vote_id ) : self::options( $vote_id );
		foreach ( $list as $o ) {
			if ( $o['id'] === $option_id ) {
				return true;
			}
		}
		return false;
	}

	/** Whether Members may see counts right now. */
	public static function members_can_see_results( $vote_id ) {
		$status = self::status( $vote_id );
		if ( 'scheduled' === $status ) {
			return false;
		}
		if ( 'closed' === $status ) {
			return true;
		}
		return 'while_open' === self::show_results_mode( $vote_id );
	}
}
