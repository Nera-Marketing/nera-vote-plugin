<?php
/**
 * Admin: the Vote edit screen (schedule, settings, Options, shortcode,
 * Results), list columns and the CSV export.
 *
 * Results are aggregated. Nothing here shows which Member ticked what.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Admin {

	const NONCE_ACTION = 'nera_vote_save';
	const NONCE_FIELD  = 'nera_vote_nonce';

	public static function init() {
		add_action( 'add_meta_boxes_' . Nera_Vote_CPT::POST_TYPE, array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Nera_Vote_CPT::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_nera_vote_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_filter( 'manage_' . Nera_Vote_CPT::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Nera_Vote_CPT::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
	}

	/* ---------------------------------------------------------------- UI */

	public static function add_meta_boxes() {
		add_meta_box( 'nera_vote_schedule', __( 'Schedule and settings', 'nera-vote' ), array( __CLASS__, 'box_schedule' ), Nera_Vote_CPT::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'nera_vote_options', __( 'Options', 'nera-vote' ), array( __CLASS__, 'box_options' ), Nera_Vote_CPT::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'nera_vote_results', __( 'Results', 'nera-vote' ), array( __CLASS__, 'box_results' ), Nera_Vote_CPT::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'nera_vote_shortcode', __( 'Shortcode', 'nera-vote' ), array( __CLASS__, 'box_shortcode' ), Nera_Vote_CPT::POST_TYPE, 'side', 'high' );
	}

	public static function enqueue( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || Nera_Vote_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'nera-vote-admin', NERA_VOTE_PLUGIN_URL . 'assets/css/admin.css', array(), (string) filemtime( NERA_VOTE_PLUGIN_DIR . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'nera-vote-admin', NERA_VOTE_PLUGIN_URL . 'assets/js/admin.js', array(), (string) filemtime( NERA_VOTE_PLUGIN_DIR . 'assets/js/admin.js' ), true );
		wp_localize_script(
			'nera-vote-admin',
			'NeraVoteAdmin',
			array(
				'chooseImage' => __( 'Choose an image', 'nera-vote' ),
				'useImage'    => __( 'Use this image', 'nera-vote' ),
				'copied'      => __( 'Copied', 'nera-vote' ),
			)
		);
	}

	private static function status_label( $status ) {
		$labels = array(
			'scheduled' => __( 'Scheduled', 'nera-vote' ),
			'open'      => __( 'Open', 'nera-vote' ),
			'closed'    => __( 'Closed', 'nera-vote' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	private static function datetime_value( $timestamp ) {
		return $timestamp ? wp_date( 'Y-m-d\TH:i', $timestamp ) : '';
	}

	public static function box_schedule( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$id     = (int) $post->ID;
		$status = Nera_Vote_CPT::status( $id );
		?>
		<p class="nera-vote-admin__status">
			<?php esc_html_e( 'Status:', 'nera-vote' ); ?>
			<span class="nera-vote-pill nera-vote-pill--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( self::status_label( $status ) ); ?></span>
			<span class="description"><?php echo esc_html( sprintf( /* translators: %s: time zone name. */ __( 'Times use the site time zone (%s).', 'nera-vote' ), wp_timezone_string() ) ); ?></span>
		</p>
		<table class="form-table nera-vote-admin__table" role="presentation">
			<tr>
				<th><label for="nera_vote_start"><?php esc_html_e( 'Opens', 'nera-vote' ); ?></label></th>
				<td>
					<input type="datetime-local" id="nera_vote_start" name="nera_vote_start" value="<?php echo esc_attr( self::datetime_value( Nera_Vote_CPT::start( $id ) ) ); ?>">
					<p class="description"><?php esc_html_e( 'Leave empty to open as soon as the Vote is published.', 'nera-vote' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="nera_vote_end"><?php esc_html_e( 'Closes', 'nera-vote' ); ?></label></th>
				<td>
					<input type="datetime-local" id="nera_vote_end" name="nera_vote_end" value="<?php echo esc_attr( self::datetime_value( Nera_Vote_CPT::end( $id ) ) ); ?>">
					<p class="description"><?php esc_html_e( 'Leave empty to keep it open until you set a date. Moving this date later re-opens a Closed Vote.', 'nera-vote' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="nera_vote_show_results"><?php esc_html_e( 'Members can see results', 'nera-vote' ); ?></label></th>
				<td>
					<select id="nera_vote_show_results" name="nera_vote_show_results">
						<option value="after_close" <?php selected( Nera_Vote_CPT::show_results_mode( $id ), 'after_close' ); ?>><?php esc_html_e( 'Only after the Vote closes', 'nera-vote' ); ?></option>
						<option value="while_open" <?php selected( Nera_Vote_CPT::show_results_mode( $id ), 'while_open' ); ?>><?php esc_html_e( 'While the Vote is open (and after)', 'nera-vote' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'You can always see the results below.', 'nera-vote' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="nera_vote_play_sound"><?php esc_html_e( 'Play a sound when saved', 'nera-vote' ); ?></label></th>
				<td>
					<select id="nera_vote_play_sound" name="nera_vote_play_sound">
						<option value="yes" <?php selected( Nera_Vote_CPT::play_sound( $id ), true ); ?>><?php esc_html_e( 'Yes', 'nera-vote' ); ?></option>
						<option value="no" <?php selected( Nera_Vote_CPT::play_sound( $id ), false ); ?>><?php esc_html_e( 'No', 'nera-vote' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function box_options( $post ) {
		$id      = (int) $post->ID;
		$options = Nera_Vote_CPT::options( $id );
		$locked  = ( 'closed' === Nera_Vote_CPT::status( $id ) && $options );
		$counts  = Nera_Vote_Votes::counts( $id );

		if ( $locked ) {
			echo '<p class="nera-vote-admin__lock">' . esc_html__( 'This Vote is Closed, so its Options are locked. Moving the closing date later re-opens it.', 'nera-vote' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Members see each Option\'s name. Clicking the name opens a popup with the image, description and link. An Option that already has Ballots cannot be deleted; tick Hidden to take it off the list instead.', 'nera-vote' ) . '</p>';
		}

		echo '<div id="nera-vote-options" class="nera-vote-options" data-locked="' . ( $locked ? '1' : '0' ) . '">';
		foreach ( $options as $i => $opt ) {
			$n = isset( $counts[ $opt['id'] ] ) ? (int) $counts[ $opt['id'] ] : 0;
			self::row_html( (string) $i, $opt, $n, (bool) $locked );
		}
		echo '</div>';

		if ( ! $locked ) {
			echo '<p><button type="button" class="button" id="nera-vote-add-option">' . esc_html__( 'Add Option', 'nera-vote' ) . '</button></p>';
			echo '<script type="text/template" id="nera-vote-option-template">';
			self::row_html(
				'__i__',
				array(
					'id'          => '',
					'name'        => '',
					'image_id'    => 0,
					'description' => '',
					'link'        => '',
					'hidden'      => false,
				),
				0,
				false
			);
			echo '</script>';
		}
	}

	private static function row_html( $index, array $opt, $ballots, $locked ) {
		$field    = 'nera_vote_options[' . $index . ']';
		$disabled = $locked ? ' disabled' : '';
		$image    = $opt['image_id'] ? wp_get_attachment_image_url( $opt['image_id'], 'thumbnail' ) : '';
		?>
		<div class="nera-vote-option<?php echo $opt['hidden'] ? ' is-hidden' : ''; ?>">
			<input type="hidden" name="<?php echo esc_attr( $field ); ?>[id]" value="<?php echo esc_attr( $opt['id'] ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="nera-vote-option__media">
				<input type="hidden" class="nera-vote-option__image-id" name="<?php echo esc_attr( $field ); ?>[image_id]" value="<?php echo esc_attr( (string) $opt['image_id'] ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<img class="nera-vote-option__preview" src="<?php echo esc_url( (string) $image ); ?>" alt=""<?php echo $image ? '' : ' hidden'; ?>>
				<?php if ( ! $locked ) : ?>
					<button type="button" class="button button-small nera-vote-option__pick"><?php esc_html_e( 'Image', 'nera-vote' ); ?></button>
					<button type="button" class="button-link nera-vote-option__unpick"<?php echo $image ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'nera-vote' ); ?></button>
				<?php endif; ?>
			</div>
			<div class="nera-vote-option__fields">
				<p>
					<label><?php esc_html_e( 'Name', 'nera-vote' ); ?>
						<input type="text" class="widefat" name="<?php echo esc_attr( $field ); ?>[name]" value="<?php echo esc_attr( $opt['name'] ); ?>"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Short description', 'nera-vote' ); ?>
						<textarea class="widefat" rows="2" maxlength="300" name="<?php echo esc_attr( $field ); ?>[description]"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( $opt['description'] ); ?></textarea>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'Link (optional)', 'nera-vote' ); ?>
						<input type="url" class="widefat" name="<?php echo esc_attr( $field ); ?>[link]" value="<?php echo esc_attr( $opt['link'] ); ?>" placeholder="https://"<?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					</label>
				</p>
			</div>
			<div class="nera-vote-option__side">
				<label class="nera-vote-option__hide">
					<input type="checkbox" name="<?php echo esc_attr( $field ); ?>[hidden]" value="1" <?php checked( $opt['hidden'] ); ?><?php echo $disabled; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php esc_html_e( 'Hidden', 'nera-vote' ); ?>
				</label>
				<?php if ( ! $locked ) : ?>
					<span class="nera-vote-option__move">
						<button type="button" class="button-link nera-vote-option__up" aria-label="<?php esc_attr_e( 'Move up', 'nera-vote' ); ?>">&uarr;</button>
						<button type="button" class="button-link nera-vote-option__down" aria-label="<?php esc_attr_e( 'Move down', 'nera-vote' ); ?>">&darr;</button>
					</span>
				<?php endif; ?>
				<?php if ( $ballots > 0 ) : ?>
					<p class="nera-vote-option__ballots description"><?php echo esc_html( sprintf( /* translators: %s: number of Ballots. */ _n( 'Has %s Ballot. It cannot be deleted; hide it instead.', 'Has %s Ballots. It cannot be deleted; hide it instead.', $ballots, 'nera-vote' ), number_format_i18n( $ballots ) ) ); ?></p>
				<?php elseif ( ! $locked ) : ?>
					<button type="button" class="button-link button-link-delete nera-vote-option__remove"><?php esc_html_e( 'Delete', 'nera-vote' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function box_results( $post ) {
		$id   = (int) $post->ID;
		$rows = Nera_Vote_Results::rows( $id, true );
		if ( ! $rows ) {
			echo '<p class="description">' . esc_html__( 'Add Options and publish the Vote to see results here.', 'nera-vote' ) . '</p>';
			return;
		}
		$voters = Nera_Vote_Votes::voter_count( $id );
		echo '<p class="nera-vote-admin__summary">' . esc_html(
			sprintf(
				/* translators: 1: Members who voted, 2: total Ballots. */
				__( '%1$s Members have voted, with %2$s Ballots in total. Names are never shown.', 'nera-vote' ),
				number_format_i18n( $voters ),
				number_format_i18n( Nera_Vote_Votes::total( $id ) )
			)
		) . '</p>';
		?>
		<table class="widefat striped nera-vote-results">
			<thead>
				<tr>
					<th class="nera-vote-results__rank">#</th>
					<th><?php esc_html_e( 'Option', 'nera-vote' ); ?></th>
					<th class="nera-vote-results__bar"><?php esc_html_e( 'Against the leader', 'nera-vote' ); ?></th>
					<th><?php esc_html_e( 'Ballots', 'nera-vote' ); ?></th>
					<th><?php esc_html_e( 'Of voters', 'nera-vote' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $row['rank'] ); ?></td>
					<td>
						<strong><?php echo esc_html( $row['name'] ); ?></strong>
						<?php if ( $row['hidden'] ) : ?>
							<span class="nera-vote-pill nera-vote-pill--hidden"><?php esc_html_e( 'Hidden', 'nera-vote' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo Nera_Vote_Results::bar_html( $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></td>
					<td><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $row['pct_voters'], 1 ) ); ?>%</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php echo esc_html( sprintf( /* translators: 1: green threshold, 2: amber threshold. */ __( 'Bar colour: green from %1$d%% of the leader\'s Ballots, amber from %2$d%%, red below.', 'nera-vote' ), Nera_Vote_Results::BAND_HIGH, Nera_Vote_Results::BAND_MID ) ); ?></p>
		<p>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nera_vote_export&vote_id=' . $id ), 'nera_vote_export_' . $id ) ); ?>"><?php esc_html_e( 'Export CSV', 'nera-vote' ); ?></a>
		</p>
		<?php
	}

	public static function box_shortcode( $post ) {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Save the Vote to get its shortcode.', 'nera-vote' ) . '</p>';
			return;
		}
		$code = '[nera_vote id="' . (int) $post->ID . '"]';
		?>
		<p class="description"><?php esc_html_e( 'Paste this into the text field of a page component to show the Vote there.', 'nera-vote' ); ?></p>
		<p>
			<input type="text" readonly class="widefat nera-vote-shortcode" value="<?php echo esc_attr( $code ); ?>" onfocus="this.select()">
		</p>
		<p><button type="button" class="button nera-vote-copy" data-copy="<?php echo esc_attr( $code ); ?>"><?php esc_html_e( 'Copy', 'nera-vote' ); ?></button></p>
		<?php
	}

	/* -------------------------------------------------------------- Save */

	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Settings.
		$mode = isset( $_POST['nera_vote_show_results'] ) ? sanitize_key( wp_unslash( $_POST['nera_vote_show_results'] ) ) : 'after_close';
		update_post_meta( $post_id, Nera_Vote_CPT::META_SHOW_RESULTS, 'while_open' === $mode ? 'while_open' : 'after_close' );
		$sound = isset( $_POST['nera_vote_play_sound'] ) ? sanitize_key( wp_unslash( $_POST['nera_vote_play_sound'] ) ) : 'yes';
		update_post_meta( $post_id, Nera_Vote_CPT::META_PLAY_SOUND, 'no' === $sound ? 'no' : 'yes' );

		// Options are locked while the Vote is Closed (decided from what is stored, before this save).
		$was_closed = ( 'closed' === Nera_Vote_CPT::status( $post_id ) && Nera_Vote_CPT::options( $post_id ) );

		// Schedule.
		$start = self::parse_time( isset( $_POST['nera_vote_start'] ) ? wp_unslash( $_POST['nera_vote_start'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed below.
		$end   = self::parse_time( isset( $_POST['nera_vote_end'] ) ? wp_unslash( $_POST['nera_vote_end'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $start && $end && $end <= $start ) {
			$end = 0;
			self::flash( 'bad_dates' );
		}
		update_post_meta( $post_id, Nera_Vote_CPT::META_START, $start );
		update_post_meta( $post_id, Nera_Vote_CPT::META_END, $end );

		if ( $was_closed ) {
			return;
		}

		$existing = array();
		foreach ( Nera_Vote_CPT::options( $post_id ) as $opt ) {
			$existing[ $opt['id'] ] = $opt;
		}

		$submitted = isset( $_POST['nera_vote_options'] ) && is_array( $_POST['nera_vote_options'] ) ? wp_unslash( $_POST['nera_vote_options'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per field below.

		$clean = array();
		$seen  = array();
		foreach ( $submitted as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$oid = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( '' === $oid || ! isset( $existing[ $oid ] ) || isset( $seen[ $oid ] ) ) {
				$oid = self::new_option_id( $existing, $seen );
			}
			$seen[ $oid ] = true;

			$link = isset( $row['link'] ) ? esc_url_raw( trim( $row['link'] ) ) : '';
			$clean[] = array(
				'id'          => $oid,
				'name'        => $name,
				'image_id'    => isset( $row['image_id'] ) ? absint( $row['image_id'] ) : 0,
				'description' => isset( $row['description'] ) ? sanitize_textarea_field( $row['description'] ) : '',
				'link'        => $link,
				'hidden'      => ! empty( $row['hidden'] ),
			);
		}

		// An Option that has Ballots can never be deleted: keep it, hidden.
		$preserved = false;
		foreach ( $existing as $oid => $opt ) {
			if ( ! isset( $seen[ $oid ] ) && Nera_Vote_Votes::option_count( $post_id, $oid ) > 0 ) {
				$opt['hidden'] = true;
				$clean[]       = $opt;
				$preserved     = true;
			}
		}
		if ( $preserved ) {
			self::flash( 'preserved' );
		}

		update_post_meta( $post_id, Nera_Vote_CPT::META_OPTIONS, wp_slash( $clean ) );
	}

	private static function parse_time( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return 0;
		}
		try {
			$dt = new DateTime( $value, wp_timezone() );
			return (int) $dt->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	private static function new_option_id( array $existing, array $seen ) {
		do {
			$id = 'opt_' . strtolower( wp_generate_password( 8, false, false ) );
		} while ( isset( $existing[ $id ] ) || isset( $seen[ $id ] ) );
		return $id;
	}

	private static function flash( $code ) {
		$key   = 'nera_vote_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		$flash = is_array( $flash ) ? $flash : array();
		$flash[ $code ] = true;
		set_transient( $key, $flash, 60 );
	}

	public static function notices() {
		$key   = 'nera_vote_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( ! is_array( $flash ) ) {
			return;
		}
		delete_transient( $key );
		if ( isset( $flash['preserved'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'An Option that already has Ballots cannot be deleted. It was kept and set to Hidden instead.', 'nera-vote' ) . '</p></div>';
		}
		if ( isset( $flash['bad_dates'] ) ) {
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'The closing date must be after the opening date, so the closing date was cleared.', 'nera-vote' ) . '</p></div>';
		}
	}

	/* ---------------------------------------------------------- Columns */

	public static function columns( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['nera_status']    = __( 'Status', 'nera-vote' );
				$new['nera_options']   = __( 'Options', 'nera-vote' );
				$new['nera_ballots']   = __( 'Ballots', 'nera-vote' );
				$new['nera_voters']    = __( 'Voters', 'nera-vote' );
				$new['nera_shortcode'] = __( 'Shortcode', 'nera-vote' );
			}
		}
		return $new;
	}

	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'nera_status':
				$status = Nera_Vote_CPT::status( $post_id );
				printf( '<span class="nera-vote-pill nera-vote-pill--%s">%s</span>', esc_attr( $status ), esc_html( self::status_label( $status ) ) );
				break;
			case 'nera_options':
				echo esc_html( (string) count( Nera_Vote_CPT::options( $post_id ) ) );
				break;
			case 'nera_ballots':
				echo esc_html( number_format_i18n( Nera_Vote_Votes::total( $post_id ) ) );
				break;
			case 'nera_voters':
				echo esc_html( number_format_i18n( Nera_Vote_Votes::voter_count( $post_id ) ) );
				break;
			case 'nera_shortcode':
				echo '<code>[nera_vote id="' . (int) $post_id . '"]</code>';
				break;
		}
	}

	/* -------------------------------------------------------------- CSV */

	public static function export_csv() {
		$vote_id = isset( $_GET['vote_id'] ) ? absint( $_GET['vote_id'] ) : 0;
		check_admin_referer( 'nera_vote_export_' . $vote_id );
		if ( ! $vote_id || Nera_Vote_CPT::POST_TYPE !== get_post_type( $vote_id ) || ! current_user_can( 'edit_post', $vote_id ) ) {
			wp_die( esc_html__( 'You are not allowed to export this Vote.', 'nera-vote' ), '', array( 'response' => 403 ) );
		}

		$filename = 'vote-' . $vote_id . '-results-' . gmdate( 'Y-m-d' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'Rank', 'Option', 'Hidden', 'Ballots', 'Percent of voters', 'Percent of leader' ) );
		foreach ( Nera_Vote_Results::rows( $vote_id, true ) as $row ) {
			fputcsv( $out, array( $row['rank'], self::csv_safe( $row['name'] ), $row['hidden'] ? 'yes' : 'no', $row['count'], $row['pct_voters'], $row['pct_leader'] ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Stops a spreadsheet treating an Option name as a formula. */
	private static function csv_safe( $value ) {
		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}
}
