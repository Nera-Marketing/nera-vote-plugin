<?php
/**
 * Front-end assets. They are enqueued from the shortcode, because the shortcode
 * can sit in a Page Components field where WordPress cannot see it up front.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Assets {

	private static $enqueued = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		wp_register_style( 'nera-vote', NERA_VOTE_PLUGIN_URL . 'assets/css/nera-vote.css', array(), self::version( 'assets/css/nera-vote.css' ) );
		wp_register_script( 'nera-vote', NERA_VOTE_PLUGIN_URL . 'assets/js/nera-vote.js', array(), self::version( 'assets/js/nera-vote.js' ), true );
	}

	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;

		if ( ! wp_style_is( 'nera-vote', 'registered' ) ) {
			self::register();
		}

		wp_enqueue_style( 'nera-vote' );
		wp_enqueue_script( 'nera-vote' );
		wp_localize_script(
			'nera-vote',
			'NeraVote',
			array(
				'rest'          => esc_url_raw( rest_url( 'nera-vote/v1/' ) ),
				'nonce'         => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'themeFeedback' => self::theme_has_feedback(),
				'i18n'          => array(
					'saved'    => __( 'Saved', 'nera-vote' ),
					'error'    => __( 'Your vote could not be saved. Please try again.', 'nera-vote' ),
					'close'    => __( 'Close', 'nera-vote' ),
					'learn'    => __( 'Learn more', 'nera-vote' ),
					'ballots'  => __( 'Ballots', 'nera-vote' ),
					'ofVoters' => __( 'of voters', 'nera-vote' ),
					'left'     => __( '%s left', 'nera-vote' ),
					'opensIn'  => __( 'Opens in %s', 'nera-vote' ),
					'ended'    => __( 'Voting has closed', 'nera-vote' ),
					'opensNow' => __( 'Voting is open now', 'nera-vote' ),
					'units'    => array(
						'day'            => __( '%d day', 'nera-vote' ),
						'days'           => __( '%d days', 'nera-vote' ),
						'hour'           => __( '%d hour', 'nera-vote' ),
						'hours'          => __( '%d hours', 'nera-vote' ),
						'minute'         => __( '%d minute', 'nera-vote' ),
						'minutes'        => __( '%d minutes', 'nera-vote' ),
						'lessThanMinute' => __( 'less than a minute', 'nera-vote' ),
					),
				),
			)
		);
	}

	/**
	 * Whether the active theme (or its parent) ships the add-to-cart chime that
	 * we borrow for "Saved". If not, the plugin plays its own copy.
	 */
	public static function theme_has_feedback() {
		$has = false;
		foreach ( array_unique( array( get_stylesheet_directory(), get_template_directory() ) ) as $dir ) {
			if ( file_exists( trailingslashit( $dir ) . 'frontend/assets/js/cart-sound.js' ) ) {
				$has = true;
				break;
			}
		}
		return (bool) apply_filters( 'nera_vote_theme_has_feedback', $has );
	}

	private static function version( $relative ) {
		$path = NERA_VOTE_PLUGIN_DIR . $relative;
		return file_exists( $path ) ? (string) filemtime( $path ) : NERA_VOTE_VERSION;
	}
}
