<?php
/**
 * Votes > Suggestions: the team's inbox of bottles Members suggested, grouped by bottle,
 * with the actions to put chosen bottles on a Vote's shortlist or decline them.
 *
 * Names of the Members behind a Suggestion are not shown here, only how many people asked.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Suggestions_Admin {

	const SLUG   = 'nera-vote-suggestions';
	const ACTION = 'nera_vote_suggestions';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nera_Vote_CPT::POST_TYPE,
			__( 'Suggestions', 'nera-vote' ),
			__( 'Suggestions', 'nera-vote' ),
			'edit_posts',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	private static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'post_type' => Nera_Vote_CPT::POST_TYPE, 'page' => self::SLUG ), $args ), admin_url( 'edit.php' ) );
	}

	public static function handle() {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'nera-vote' ), '', array( 'response' => 403 ) );
		}
		$keys = isset( $_POST['keys'] ) && is_array( $_POST['keys'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['keys'] ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by array_map.
		$act  = isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : '';
		$args = array();

		if ( ! $keys ) {
			$args['nv_msg'] = 'none';
		} elseif ( 'add' === $act ) {
			$vote_id = isset( $_POST['vote_id'] ) ? absint( $_POST['vote_id'] ) : 0;
			if ( ! $vote_id || Nera_Vote_CPT::POST_TYPE !== get_post_type( $vote_id ) || 'closed' === Nera_Vote_CPT::status( $vote_id ) || ! current_user_can( 'edit_post', $vote_id ) ) {
				$args['nv_msg'] = 'badvote';
			} else {
				$args['nv_msg'] = 'added';
				$args['nv_n']   = Nera_Vote_Suggestions::add_to_vote( $keys, $vote_id );
				$args['nv_v']   = $vote_id;
			}
		} elseif ( 'decline' === $act ) {
			$args['nv_msg'] = 'declined';
			$args['nv_n']   = Nera_Vote_Suggestions::decline( $keys );
		} elseif ( 'restore' === $act ) {
			Nera_Vote_Suggestions::reset( $keys );
			$args['nv_msg'] = 'restored';
		}
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	public static function notices() {
		if ( ! isset( $_GET['page'] ) || self::SLUG !== $_GET['page'] || ! isset( $_GET['nv_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$n    = isset( $_GET['nv_n'] ) ? absint( $_GET['nv_n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = sanitize_key( wp_unslash( $_GET['nv_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map  = array(
			'none'     => array( 'warning', __( 'Select at least one Suggestion.', 'nera-vote' ) ),
			'badvote'  => array( 'warning', __( 'Choose a Vote that is not Closed.', 'nera-vote' ) ),
			'added'    => array( 'success', sprintf( /* translators: %d: number of Options added. */ _n( 'Added %d Option to the Vote.', 'Added %d Options to the Vote.', $n, 'nera-vote' ), $n ) ),
			'declined' => array( 'success', __( 'Declined.', 'nera-vote' ) ),
			'restored' => array( 'success', __( 'Moved back to New.', 'nera-vote' ) ),
		);
		if ( isset( $map[ $code ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $map[ $code ][0] ), esc_html( $map[ $code ][1] ) );
		}
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'new'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $filter, array( 'all', 'new', 'shortlisted', 'declined' ), true ) ) {
			$filter = 'new';
		}
		$groups = Nera_Vote_Suggestions::groups();
		$counts = array( 'all' => count( $groups ), 'new' => 0, 'shortlisted' => 0, 'declined' => 0 );
		foreach ( $groups as $g ) {
			++$counts[ $g['status'] ];
		}
		$shown = array_filter(
			$groups,
			static function ( $g ) use ( $filter ) {
				return 'all' === $filter || $g['status'] === $filter;
			}
		);
		$votes = get_posts(
			array(
				'post_type'      => Nera_Vote_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$votes = array_filter(
			$votes,
			static function ( $v ) {
				return 'closed' !== Nera_Vote_CPT::status( $v->ID );
			}
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Suggestions', 'nera-vote' ); ?></h1>
			<?php if ( ! Nera_Vote_Suggestions::form_id() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'The Suggest a bottle form does not exist yet. It is created when Fluent Forms is active.', 'nera-vote' ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Bottles Members suggested, grouped by bottle and brand. The most suggested come first. Add the ones you can source to a Vote to build its shortlist.', 'nera-vote' ); ?></p>

			<ul class="subsubsub">
				<?php
				$labels = array(
					'new'         => __( 'New', 'nera-vote' ),
					'shortlisted' => __( 'On a shortlist', 'nera-vote' ),
					'declined'    => __( 'Declined', 'nera-vote' ),
					'all'         => __( 'All', 'nera-vote' ),
				);
				$i = 0;
				foreach ( $labels as $key => $label ) {
					printf(
						'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>%5$s</li>',
						esc_url( self::url( array( 'status' => $key ) ) ),
						$filter === $key ? ' class="current" aria-current="page"' : '',
						esc_html( $label ),
						(int) $counts[ $key ],
						++$i < count( $labels ) ? ' |' : ''
					);
				}
				?>
			</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>
				<div class="tablenav top" style="height:auto">
					<div class="alignleft actions" style="padding:6px 0">
						<label for="nv-vote" class="screen-reader-text"><?php esc_html_e( 'Vote', 'nera-vote' ); ?></label>
						<select id="nv-vote" name="vote_id">
							<option value=""><?php esc_html_e( 'Choose a Vote…', 'nera-vote' ); ?></option>
							<?php foreach ( $votes as $v ) : ?>
								<option value="<?php echo esc_attr( (string) $v->ID ); ?>"><?php echo esc_html( get_the_title( $v ) . ' (' . ucfirst( Nera_Vote_CPT::status( $v->ID ) ) . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" name="bulk" value="add" class="button button-primary"><?php esc_html_e( 'Add to Vote', 'nera-vote' ); ?></button>
						<button type="submit" name="bulk" value="decline" class="button"><?php esc_html_e( 'Decline', 'nera-vote' ); ?></button>
						<button type="submit" name="bulk" value="restore" class="button"><?php esc_html_e( 'Move back to New', 'nera-vote' ); ?></button>
					</div>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.nv-key').forEach(function(c){c.checked=this.checked}.bind(this))"></td>
							<th><?php esc_html_e( 'Bottle', 'nera-vote' ); ?></th>
							<th><?php esc_html_e( 'Brand', 'nera-vote' ); ?></th>
							<th><?php esc_html_e( 'Members', 'nera-vote' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'nera-vote' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nera-vote' ); ?></th>
							<th><?php esc_html_e( 'Last suggested', 'nera-vote' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( ! $shown ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'Nothing here yet.', 'nera-vote' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $shown as $g ) : ?>
						<tr>
							<th class="check-column"><input type="checkbox" class="nv-key" name="keys[]" value="<?php echo esc_attr( $g['key'] ); ?>"></th>
							<td><strong><?php echo esc_html( $g['bottle'] ); ?></strong></td>
							<td><?php echo esc_html( $g['brand'] ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $g['count'] ) ); ?></td>
							<td>
								<?php
								foreach ( array_slice( $g['notes'], 0, 3 ) as $note ) {
									echo '<p style="margin:0 0 .4em">' . esc_html( $note ) . '</p>';
								}
								if ( count( $g['notes'] ) > 3 ) {
									echo '<span class="description">' . esc_html( sprintf( /* translators: %d: number of further notes. */ __( '+ %d more', 'nera-vote' ), count( $g['notes'] ) - 3 ) ) . '</span>';
								}
								?>
							</td>
							<td>
								<span class="nera-vote-pill nera-vote-pill--<?php echo esc_attr( $g['status'] ); ?>"><?php echo esc_html( Nera_Vote_Suggestions::status_label( $g['status'] ) ); ?></span>
								<?php
								foreach ( array_unique( $g['links'] ) as $link ) {
									$vid = (int) strtok( $link, ':' );
									if ( $vid && Nera_Vote_CPT::POST_TYPE === get_post_type( $vid ) ) {
										echo '<br><a href="' . esc_url( get_edit_post_link( $vid, '' ) ) . '">' . esc_html( get_the_title( $vid ) ) . '</a>';
									}
								}
								?>
							</td>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ), $g['last'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}
}
