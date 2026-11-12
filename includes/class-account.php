<?php
/**
 * My account tab "My suggestions and votes": a WooCommerce endpoint showing a Member's
 * Suggestions with their status and the Votes they took part in.
 *
 * Built only on WooCommerce's own hooks, so any theme that renders the standard account
 * navigation and content actions shows it.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Account {

	const ENDPOINT        = 'my-votes';
	const OPTION_FLUSHED  = 'nera_vote_endpoint_version';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_items' ), 20 );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	public static function add_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	/** Rewrite rules are rebuilt once per plugin version (and on activation). */
	public static function maybe_flush() {
		if ( get_option( self::OPTION_FLUSHED ) !== NERA_VOTE_VERSION . '-1' ) {
			flush_rewrite_rules( false );
			update_option( self::OPTION_FLUSHED, NERA_VOTE_VERSION . '-1', false );
		}
	}

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function menu_items( $items ) {
		$label = __( 'My suggestions and votes', 'nera-vote' );
		$out   = array();
		$added = false;
		foreach ( $items as $key => $text ) {
			if ( 'customer-logout' === $key ) {
				$out[ self::ENDPOINT ] = $label;
				$added                 = true;
			}
			$out[ $key ] = $text;
		}
		if ( ! $added ) {
			$out[ self::ENDPOINT ] = $label;
		}
		return $out;
	}

	public static function title() {
		return __( 'My suggestions and votes', 'nera-vote' );
	}

	public static function render() {
		Nera_Vote_Assets::enqueue(); // The tab can load on a page the shortcode never touches.
		$user_id     = get_current_user_id();
		$suggestions = Nera_Vote_Suggestions::for_user( $user_id );

		echo '<div class="nera-vote-account">';
		// Without the form there is nothing to suggest with, so the section is left out rather than shown empty.
		$has_form = (bool) Nera_Vote_Suggestions::form_id();
		if ( $has_form ) {
			echo '<h3 class="nera-vote-account__title">' . esc_html__( 'My suggestions', 'nera-vote' ) . '</h3>';
		}
		if ( ! $has_form ) {
			$suggestions = array();
		} elseif ( ! $suggestions ) {
			echo '<p class="nera-vote-account__empty">' . esc_html__( 'You have not suggested a bottle yet. Use the Suggest a bottle button on the Vote page.', 'nera-vote' ) . '</p>';
		} else {
			echo '<ul class="nera-vote-account__list">';
			foreach ( $suggestions as $s ) {
				$note = '';
				if ( 'shortlisted' === $s['status'] ) {
					$note = ( 'open' === $s['vote_status'] )
						? __( 'On this week\'s vote', 'nera-vote' )
						: ( 'scheduled' === $s['vote_status'] ? __( 'On the next vote', 'nera-vote' ) : __( 'Was on a vote', 'nera-vote' ) );
				}
				printf(
					'<li class="nera-vote-account__item"><span class="nera-vote-account__bottle"><strong>%1$s</strong>%2$s</span> <span class="nera-vote-pill nera-vote-pill--%3$s">%4$s</span>%5$s<span class="nera-vote-account__date">%6$s</span></li>',
					esc_html( $s['bottle'] ),
					'' !== $s['brand'] ? ' <span class="nera-vote-account__brand">' . esc_html( $s['brand'] ) . '</span>' : '',
					esc_attr( $s['status'] ),
					esc_html( Nera_Vote_Suggestions::status_label( $s['status'] ) ),
					'' !== $note ? ' <span class="nera-vote-account__note">' . esc_html( $note ) . '</span> ' : ' ',
					esc_html( wp_date( get_option( 'date_format' ), $s['time'] ) )
				);
			}
			echo '</ul>';
		}

		echo '<h3 class="nera-vote-account__title">' . esc_html__( 'Votes I took part in', 'nera-vote' ) . '</h3>';
		$votes = array();
		foreach ( Nera_Vote_Votes::for_user( $user_id ) as $ballot ) {
			$votes[ (int) $ballot->vote_id ][] = (string) $ballot->option_id;
		}
		$shown = 0;
		if ( $votes ) {
			echo '<ul class="nera-vote-account__list">';
			foreach ( $votes as $vote_id => $option_ids ) {
				if ( Nera_Vote_CPT::POST_TYPE !== get_post_type( $vote_id ) ) {
					continue;
				}
				$names = array();
				foreach ( Nera_Vote_CPT::options( $vote_id ) as $opt ) {
					if ( in_array( $opt['id'], $option_ids, true ) && ! $opt['hidden'] ) {
						$names[] = $opt['name'];
					}
				}
				++$shown;
				printf(
					'<li class="nera-vote-account__item"><span class="nera-vote-account__bottle"><strong>%1$s</strong></span> <span class="nera-vote-pill nera-vote-pill--%2$s">%3$s</span>%4$s</li>',
					esc_html( get_the_title( $vote_id ) ),
					esc_attr( Nera_Vote_CPT::status( $vote_id ) ),
					esc_html( ucfirst( Nera_Vote_CPT::status( $vote_id ) ) ),
					$names ? ' <span class="nera-vote-account__note">' . esc_html( sprintf( /* translators: %s: bottle names. */ __( 'You chose: %s', 'nera-vote' ), implode( ', ', $names ) ) ) . '</span>' : ''
				);
			}
			echo '</ul>';
		}
		if ( ! $shown ) {
			echo '<p class="nera-vote-account__empty">' . esc_html__( 'You have not voted yet.', 'nera-vote' ) . '</p>';
		}
		echo '</div>';
	}
}
