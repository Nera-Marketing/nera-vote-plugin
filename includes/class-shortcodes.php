<?php
/**
 * [nera_vote id="123"]: renders one Vote. The page shortcode [nera_vote_page] lives in
 * class-page.php and builds on this.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Shortcodes {

	/** The suggest dialog (with the Fluent Forms form in it) is printed once per request. */
	private static $suggest_dialog_printed = false;

	public static function init() {
		add_shortcode( 'nera_vote', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts    = shortcode_atts(
			array(
				'id'      => 0,
				'title'   => 'yes',
				'suggest' => 'yes',
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
		$mode      = Nera_Vote_CPT::choice_mode( $vote_id );
		$single    = ( 'single' === $mode );
		$suggest   = ( 'no' !== strtolower( (string) $atts['suggest'] ) );

		ob_start();
		printf(
			'<section class="nera-vote nera-vote--%1$s nera-vote--%6$s" id="%2$s" data-vote-id="%3$d" data-status="%1$s" data-mode="%6$s" data-sound="%4$s" data-logged-in="%5$s">',
			esc_attr( $status ),
			esc_attr( $dom_id ),
			(int) $vote_id,
			Nera_Vote_CPT::play_sound( $vote_id ) ? '1' : '0',
			$logged_in ? '1' : '0',
			esc_attr( $mode )
		);

		// Header: title and deadline on the left, the Suggest a bottle button on the right.
		echo '<header class="nera-vote__head"><div class="nera-vote__head-main">';
		if ( '' !== $title ) {
			printf( '<h3 class="nera-vote__title">%s</h3>', esc_html( $title ) );
		}

		if ( 'scheduled' === $status ) {
			$start = Nera_Vote_CPT::start( $vote_id );
			printf(
				'<p class="nera-vote__notice nera-vote__deadline" data-kind="opens" data-at="%1$d" data-now="%2$d"><strong class="nera-vote__deadline-date">%3$s</strong> <span class="nera-vote__countdown">%4$s</span></p>',
				(int) $start,
				time(),
				esc_html(
					sprintf(
						/* translators: %s: date and time the Vote opens. */
						__( 'Voting opens on %s.', 'nera-vote' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $start )
					)
				),
				esc_html( self::countdown_text( $start - time(), 'opens' ) )
			);
			self::close_head( $suggest );
			echo '</section>';
			return (string) ob_get_clean();
		}

		$end = Nera_Vote_CPT::end( $vote_id );
		if ( 'open' === $status && $end ) {
			printf(
				'<p class="nera-vote__deadline" data-kind="closes" data-at="%1$d" data-now="%2$d"><strong class="nera-vote__deadline-date">%3$s</strong> <span class="nera-vote__countdown">%4$s</span></p>',
				(int) $end,
				time(),
				esc_html(
					sprintf(
						/* translators: %s: day, date and time the Vote closes. */
						__( 'Voting closes %s', 'nera-vote' ),
						wp_date( 'D j M, H:i', $end )
					)
				),
				esc_html( self::countdown_text( $end - time(), 'closes' ) )
			);
		}
		self::close_head( $suggest );

		if ( 'closed' === $status ) {
			echo '<p class="nera-vote__notice">' . esc_html__( 'Voting has closed.', 'nera-vote' ) . '</p>';
		} elseif ( $logged_in ) {
			$hint = $single
				? __( 'Choose one bottle. Your choice saves automatically and you can change it until voting closes.', 'nera-vote' )
				: __( 'Tick the bottles you like. Your choices save automatically.', 'nera-vote' );
			echo '<p class="nera-vote__hint">' . esc_html( $hint ) . '</p>';
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
					'<label class="nera-vote__check"><input type="%7$s" class="nera-vote__input"%6$s value="%1$s"%2$s%3$s><span class="nera-vote__tick" aria-hidden="true"></span><span class="nera-vote__sr">%4$s</span></label>',
					esc_attr( $opt['id'] ),
					checked( in_array( $opt['id'], $mine, true ), true, false ),
					$can_tick ? '' : ' disabled',
					esc_html( sprintf( /* translators: %s: Option name. */ __( 'Vote for %s', 'nera-vote' ), $opt['name'] ) ),
					'',
					$single ? ' name="' . esc_attr( $dom_id ) . '-choice"' : '',
					$single ? 'radio' : 'checkbox'
				);
			}

			$thumb = $opt['image_id'] ? wp_get_attachment_image_url( $opt['image_id'], 'thumbnail' ) : '';
			if ( $thumb ) {
				printf(
					'<button type="button" class="nera-vote__thumb" data-option-id="%1$s" aria-haspopup="dialog" aria-label="%2$s"><img src="%3$s" alt="" width="56" height="56" loading="lazy" decoding="async"></button>',
					esc_attr( $opt['id'] ),
					esc_attr( sprintf( /* translators: %s: Option name. */ __( 'More about %s', 'nera-vote' ), $opt['name'] ) ),
					esc_url( $thumb )
				);
			} else {
				printf(
					'<button type="button" class="nera-vote__thumb nera-vote__thumb--empty" data-option-id="%1$s" aria-haspopup="dialog" aria-label="%2$s"><span aria-hidden="true">%3$s</span></button>',
					esc_attr( $opt['id'] ),
					esc_attr( sprintf( /* translators: %s: Option name. */ __( 'More about %s', 'nera-vote' ), $opt['name'] ) ),
					esc_html( strtoupper( function_exists( 'mb_substr' ) ? mb_substr( $opt['name'], 0, 1 ) : substr( $opt['name'], 0, 1 ) ) )
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

		if ( $suggest ) {
			echo self::suggest_dialog(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
		}

		echo '</section>';
		return (string) ob_get_clean();
	}

	/**
	 * Ends the section header: the left column is open, then the Suggest a bottle button goes
	 * in the right column. The dialog itself is printed at the end of the section.
	 */
	private static function close_head( $suggest ) {
		echo '</div>';
		if ( $suggest ) {
			$button = self::suggest_button();
			if ( '' !== $button ) {
				echo '<div class="nera-vote__head-action">' . $button . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
			}
		}
		echo '</header>';
	}

	/**
	 * "2 days 4 hours left" / "Opens in 3 hours 5 minutes". The page script keeps it ticking;
	 * this is what shows without JavaScript.
	 */
	public static function countdown_text( $seconds, $kind ) {
		$seconds = max( 0, (int) $seconds );
		$days    = (int) floor( $seconds / DAY_IN_SECONDS );
		$hours   = (int) floor( ( $seconds % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $seconds % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		$unit = static function ( $n, $one, $many ) {
			return sprintf( _n( $one, $many, $n, 'nera-vote' ), $n ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralSingle, WordPress.WP.I18n.NonSingularStringLiteralPlural
		};

		if ( $days >= 1 ) {
			$parts = array( $unit( $days, '%d day', '%d days' ) );
			if ( $hours ) {
				$parts[] = $unit( $hours, '%d hour', '%d hours' );
			}
			$span = implode( ' ', $parts );
		} elseif ( $hours >= 1 ) {
			$parts = array( $unit( $hours, '%d hour', '%d hours' ) );
			if ( $minutes ) {
				$parts[] = $unit( $minutes, '%d minute', '%d minutes' );
			}
			$span = implode( ' ', $parts );
		} elseif ( $minutes >= 1 ) {
			$span = $unit( $minutes, '%d minute', '%d minutes' );
		} else {
			$span = __( 'less than a minute', 'nera-vote' );
		}

		if ( 'opens' === $kind ) {
			/* translators: %s: a span of time such as "2 days 4 hours". */
			return sprintf( __( 'Opens in %s', 'nera-vote' ), $span );
		}
		/* translators: %s: a span of time such as "2 days 4 hours". */
		return sprintf( __( '%s left', 'nera-vote' ), $span );
	}

	/**
	 * The "Suggest a bottle" button. Members get a dialog holding the Fluent Forms form
	 * (printed once, however many Votes are on the page); visitors get a sign-in link.
	 * Nothing shows when Fluent Forms is not active, so a Member is never offered a form
	 * that cannot save.
	 */
	public static function suggest_cta() {
		$button = self::suggest_button();
		if ( '' === $button ) {
			return '';
		}
		return '<p class="nera-vote__suggest">' . $button . '</p>' . self::suggest_dialog();
	}

	/** The button (or sign-in link) alone; empty text never. Notice for editors when Fluent Forms is off. */
	public static function suggest_button() {
		$form_id = Nera_Vote_Suggestions::form_id();
		if ( ! $form_id ) {
			return self::editor_notice( __( 'Nera – Vote: the "Suggest a bottle" button is hidden because Fluent Forms is not active.', 'nera-vote' ) );
		}
		Nera_Vote_Assets::enqueue();

		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<a class="nera-vote__cta" href="%1$s">%2$s</a>',
				esc_url( self::login_url() ),
				esc_html__( 'Sign in to suggest a bottle', 'nera-vote' )
			);
		}

		return sprintf(
			'<button type="button" class="nera-vote__cta" data-nera-vote-suggest aria-haspopup="dialog">%s</button>',
			esc_html__( 'Suggest a bottle', 'nera-vote' )
		);
	}

	/** The Suggest a bottle dialog, printed once per request and only for signed-in Members. */
	public static function suggest_dialog() {
		$form_id = Nera_Vote_Suggestions::form_id();
		if ( ! $form_id || ! is_user_logged_in() || self::$suggest_dialog_printed ) {
			return '';
		}
		self::$suggest_dialog_printed = true;
		$limit = Nera_Vote_Suggestions::limit();
		return '<dialog class="nera-vote__dialog nera-vote__suggest-dialog" id="nera-vote-suggest" aria-labelledby="nera-vote-suggest-title">'
			. '<button type="button" class="nera-vote__dialog-close" data-nera-vote-close aria-label="' . esc_attr__( 'Close', 'nera-vote' ) . '">×</button>'
			. '<div class="nera-vote__dialog-body">'
			. '<h4 class="nera-vote__dialog-title" id="nera-vote-suggest-title">' . esc_html__( 'Suggest a bottle', 'nera-vote' ) . '</h4>'
			. '<p class="nera-vote__dialog-text">' . esc_html(
				sprintf(
					/* translators: %d: suggestions allowed each week. */
					_n( 'Tell us a fragrance you would like to see. You can suggest %d bottle each week.', 'Tell us a fragrance you would like to see. You can suggest up to %d bottles each week.', $limit, 'nera-vote' ),
					$limit
				)
			) . '</p>'
			. do_shortcode( '[fluentform id="' . (int) $form_id . '"]' )
			. '</div></dialog>';
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

	public static function login_url() {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
		if ( ! $url ) {
			$url = wp_login_url( get_permalink() );
		}
		return (string) apply_filters( 'nera_vote_login_url', $url );
	}

	public static function editor_notice( $message ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return '';
		}
		return '<p class="nera-vote__notice nera-vote__notice--editor">' . esc_html( $message ) . '</p>';
	}
}
