<?php
/**
 * The Vote page: created once when the plugin is activated, holding [nera_vote_page],
 * which shows the Vote that matters right now, the Suggest a bottle button and the rules.
 *
 * The page is created once and remembered by ID. If it is later deleted or trashed it is
 * not made again behind the admin's back; an admin notice offers to. Menus are never touched.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Page {

	const OPTION_PAGE = 'nera_vote_page_id';
	const SHORTCODE   = 'nera_vote_page';

	public static function init() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_missing_page' ) );
	}

	/**
	 * Creates the page unless it was created before. Returns the page ID, or 0 when it is
	 * missing and was deliberately not recreated.
	 */
	public static function ensure_page() {
		$stored = (int) get_option( self::OPTION_PAGE, 0 );
		if ( $stored ) {
			return $stored;
		}

		// A page that already holds the shortcode is adopted rather than duplicated.
		$existing = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				's'              => '[' . self::SHORTCODE,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		foreach ( $existing as $id ) {
			if ( has_shortcode( (string) get_post_field( 'post_content', $id ), self::SHORTCODE ) ) {
				update_option( self::OPTION_PAGE, (int) $id, false );
				return (int) $id;
			}
		}

		// wp_insert_post gives a taken slug a suffix (vote-2), so an existing page is never overwritten.
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Vote', 'nera-vote' ),
				'post_name'    => 'vote',
				'post_content' => '[' . self::SHORTCODE . ']',
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_option( self::OPTION_PAGE, (int) $id, false );
		return (int) $id;
	}

	public static function notice_missing_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Nera_Vote_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$stored = (int) get_option( self::OPTION_PAGE, 0 );
		if ( ! $stored ) {
			return;
		}
		$page = get_post( $stored );
		if ( $page && 'trash' !== $page->post_status ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Nera – Vote: the Vote page was deleted or is in the trash.', 'nera-vote' )
			. ' <a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nera_vote_create_page' ), 'nera_vote_create_page' ) ) . '">' . esc_html__( 'Create it again', 'nera-vote' ) . '</a></p></div>';
	}

	/**
	 * The Vote to show: the open Vote closing soonest, else the next to open, else the
	 * last to close.
	 */
	public static function current_vote_id() {
		$ids = get_posts(
			array(
				'post_type'      => Nera_Vote_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$open      = array();
		$scheduled = array();
		$closed    = array();
		foreach ( $ids as $id ) {
			switch ( Nera_Vote_CPT::status( $id ) ) {
				case 'open':
					$end             = Nera_Vote_CPT::end( $id );
					$open[ $id ]     = $end ? $end : PHP_INT_MAX;
					break;
				case 'scheduled':
					$scheduled[ $id ] = Nera_Vote_CPT::start( $id );
					break;
				default:
					$closed[ $id ] = Nera_Vote_CPT::end( $id );
			}
		}
		if ( $open ) {
			asort( $open );
			return (int) key( $open );
		}
		if ( $scheduled ) {
			asort( $scheduled );
			return (int) key( $scheduled );
		}
		if ( $closed ) {
			arsort( $closed );
			return (int) key( $closed );
		}
		return 0;
	}

	public static function render() {
		Nera_Vote_Assets::enqueue();

		$vote_id = self::current_vote_id();
		ob_start();
		echo '<div class="nera-vote-page">';
		if ( $vote_id ) {
			echo do_shortcode( '[nera_vote id="' . (int) $vote_id . '" suggest="yes"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the shortcode escapes its own output.
		} else {
			echo '<section class="nera-vote nera-vote--none"><p class="nera-vote__notice">' . esc_html__( 'There is no vote right now. Suggest a bottle and we will use it to build the next shortlist.', 'nera-vote' ) . '</p>';
			echo Nera_Vote_Shortcodes::suggest_cta(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			echo '</section>';
		}

		$rules = Nera_Vote_Settings::rules_lines();
		if ( $rules ) {
			echo '<section class="nera-vote-rules"><h3 class="nera-vote-rules__title">' . esc_html__( 'How the vote works', 'nera-vote' ) . '</h3><ul class="nera-vote-rules__list">';
			foreach ( $rules as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></section>';
		}
		echo '</div>';
		return (string) ob_get_clean();
	}
}
