<?php
/**
 * [nera_vote id="123"] — renders one Vote.
 *
 * Scheduled: only the opening date. Open: tickable list. Closed: read-only
 * list, with Results when the Vote allows Members to see them.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Shortcodes {

	public static function init() {
		add_shortcode( 'nera_vote', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts    = shortcode_atts(
			array(
				'id'    => 0,
				'title' => 'yes',
			),
			$atts,
			'nera_vote'
		);
		$vote_id = (int) $atts['id'];
		$post    = $vote_id ? get_post( $vote_id ) : null;

		if ( ! $post || Nera_Vote_CPT::POST_TYPE !== $post->post_type ) {
			return self::editor_notice( __( 'Nera – Vote: no Vote with that ID.', 'nera-vote' ) );
		}
		if ( 'publish' !== $post->post_status ) {
			return self::editor_notice( __( 'Nera – Vote: this Vote is not published, so visitors do not see it.', 'nera-vote' ) );
		}

		Nera_Vote_Assets::enqueue();

		$status    = Nera_Vote_CPT::status( $vote_id );
		$logged_in = is_user_logged_in();
		$user_id   = get_current_user_id();
		$options   = Nera_Vote_CPT::visible_options( $vote_id );
		$title     = 'no' !== strtolower( (string) $atts['title'] ) ? get_the_title( $post ) : '';
		$dom_id    = 'nera-vote-' . $vote_id;

		ob_start();
		printf(
			'<section class="nera-vote nera-vote--%1$s" id="%2$s" data-vote-id="%3$d" data-status="%1$s" data-sound="%4$s" data-logged-in="%5$s">',
			esc_attr( $status ),
			esc_attr( $dom_id ),
			(int) $vote_id,
			Nera_Vote_CPT::play_sound( $vote_id ) ? '1' : '0',
			$logged_in ? '1' : '0'
		);

		if ( '' !== $title ) {
			printf( '<h3 class="nera-vote__title">%s</h3>', esc_html( $title ) );
		}

		if ( 'scheduled' === $status ) {
			$start = Nera_Vote_CPT::start( $vote_id );
			printf(
				'<p class="nera-vote__notice">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: date and time the Vote opens. */
						__( 'Voting opens on %s.', 'nera-vote' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start )
					)
				)
			);
			echo '</section>';
			return (string) ob_get_clean();
		}

		if ( 'closed' === $status ) {
			echo '<p class="nera-vote__notice">' . esc_html__( 'Voting has closed.', 'nera-vote' ) . '</p>';
		} elseif ( $logged_in ) {
			echo '<p class="nera-vote__hint">' . esc_html__( 'Tick the bottles you like. Your choices save automatically.', 'nera-vote' ) . '</p>';
		} else {
			printf(
				'<p class="nera-vote__notice">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Sign in to vote.', 'nera-vote' ),
				esc_url( self::login_url() ),
				esc_html__( 'Sign in', 'nera-vote' )
			);
		}

		$mine    = $logged_in ? Nera_Vote_Votes::user_option_ids( $vote_id, $user_id ) : array();
		$results = array();
		if ( $logged_in && Nera_Vote_CPT::members_can_see_results( $vote_id ) ) {
			foreach ( Nera_Vote_Results::rows( $vote_id, false ) as $row ) {
				$results[ $row['id'] ] = $row;
			}
		}

		// With Results available, list in ranked order.
		if ( $results ) {
			usort(
				$options,
				static function ( $a, $b ) use ( $results ) {
					return $results[ $a['id'] ]['rank'] <=> $results[ $b['id'] ]['rank'];
				}
			);
		}

		$can_tick = ( 'open' === $status && $logged_in );

		echo '<ul class="nera-vote__list">';
		foreach ( $options as $opt ) {
			printf( '<li class="nera-vote__item" data-option-id="%s">', esc_attr( $opt['id'] ) );

			if ( 'open' === $status ) {
				printf(
					'<label class="nera-vote__check"><input type="checkbox" class="nera-vote__input" value="%1$s"%2$s%3$s><span class="nera-vote__tick" aria-hidden="true"></span><span class="nera-vote__sr">%4$s</span></label>',
					esc_attr( $opt['id'] ),
					checked( in_array( $opt['id'], $mine, true ), true, false ),
					$can_tick ? '' : ' disabled',
					esc_html( sprintf( /* translators: %s: Option name. */ __( 'Vote for %s', 'nera-vote' ), $opt['name'] ) )
				);
			}

			printf(
				'<button type="button" class="nera-vote__name" data-option-id="%1$s" aria-haspopup="dialog">%2$s</button>',
				esc_attr( $opt['id'] ),
				esc_html( $opt['name'] )
			);

			echo '<span class="nera-vote__result"';
			if ( ! isset( $results[ $opt['id'] ] ) ) {
				echo ' hidden';
			}
			echo '>';
			if ( isset( $results[ $opt['id'] ] ) ) {
				echo self::result_html( $results[ $opt['id'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
			echo '</span>';

			echo '</li>';
		}
		echo '</ul>';

		// Data for the popup. Hex-encoded so it can never close the script tag.
		$data = array();
		foreach ( $options as $opt ) {
			$image          = $opt['image_id'] ? wp_get_attachment_image_url( $opt['image_id'], 'large' ) : '';
			$data[ $opt['id'] ] = array(
				'name'        => $opt['name'],
				'image'       => $image ? esc_url_raw( $image ) : '',
				'description' => $opt['description'],
				'link'        => $opt['link'] ? esc_url_raw( $opt['link'] ) : '',
			);
		}
		printf(
			'<script type="application/json" class="nera-vote__data">%s</script>',
			wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);

		echo '</section>';
		return (string) ob_get_clean();
	}

	/** Bar plus numbers for one ranked row. */
	public static function result_html( array $row ) {
		return Nera_Vote_Results::bar_html( $row ) . sprintf(
			'<span class="nera-vote__figures"><strong>%1$s</strong> %2$s · %3$s%% %4$s</span>',
			esc_html( number_format_i18n( $row['count'] ) ),
			esc_html__( 'Ballots', 'nera-vote' ),
			esc_html( number_format_i18n( $row['pct_voters'], 1 ) ),
			esc_html__( 'of voters', 'nera-vote' )
		);
	}

	private static function login_url() {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		if ( ! $url ) {
			$url = wp_login_url( get_permalink() );
		}
		return (string) apply_filters( 'nera_vote_login_url', $url );
	}

	private static function editor_notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="nera-vote__notice nera-vote__notice--editor">' . esc_html( $message ) . '</p>';
	}
}
