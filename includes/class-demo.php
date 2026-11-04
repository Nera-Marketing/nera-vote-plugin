<?php
/**
 * Tools > Nera Vote Demo Seeder: creates realistic demo Votes, Members and Ballots, and
 * removes exactly those again. Everything it creates carries a marker, and the
 * remove step only ever touches marked records.
 *
 * Every operation runs over AJAX in small steps, so the page can show progress.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Demo {

	const VOTE_META = '_nera_vote_demo';
	const USER_META = '_nera_vote_demo_user';
	const NONCE     = 'nera_vote_demo';
	const PAGE      = 'nera-vote-demo';
	const BATCH     = 15;
	const IMG_BATCH = 6;
	const HIDE_OPT  = 'nera_vote_demo_hidden';

	/**
	 * Whether the tool has been switched off with its "Finish and hide" button.
	 * To bring it back: delete the option, or define NERA_VOTE_SHOW_DEMO as true.
	 */
	public static function is_hidden() {
		$hidden = (bool) get_option( self::HIDE_OPT ) && ! ( defined( 'NERA_VOTE_SHOW_DEMO' ) && NERA_VOTE_SHOW_DEMO );
		return (bool) apply_filters( 'nera_vote_demo_hidden', $hidden );
	}

	public static function init() {
		if ( self::is_hidden() ) {
			return;
		}
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_nera_vote_demo_status', array( __CLASS__, 'ajax_status' ) );
		add_action( 'wp_ajax_nera_vote_demo_step', array( __CLASS__, 'ajax_step' ) );
		add_action( 'wp_ajax_nera_vote_demo_hide', array( __CLASS__, 'ajax_hide' ) );
	}

	/** Creating demo data is blocked on production unless explicitly allowed. Removing is never blocked. */
	public static function creation_allowed() {
		$allowed = 'production' !== wp_get_environment_type() || ( defined( 'NERA_VOTE_ALLOW_DEMO' ) && NERA_VOTE_ALLOW_DEMO );
		return (bool) apply_filters( 'nera_vote_allow_demo', $allowed );
	}

	/* ---------------------------------------------------------------- UI */

	public static function menu() {
		add_management_page(
			__( 'Nera Vote Demo Seeder', 'nera-vote' ),
			__( 'Nera Vote Demo Seeder', 'nera-vote' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}
		wp_enqueue_style( 'nera-vote-admin', NERA_VOTE_PLUGIN_URL . 'assets/css/admin.css', array(), (string) filemtime( NERA_VOTE_PLUGIN_DIR . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'nera-vote-demo', NERA_VOTE_PLUGIN_URL . 'assets/js/demo.js', array(), (string) filemtime( NERA_VOTE_PLUGIN_DIR . 'assets/js/demo.js' ), true );
		wp_localize_script(
			'nera-vote-demo',
			'NeraVoteDemo',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'status'  => self::status(),
				'allowed' => self::creation_allowed(),
				'i18n'    => array(
					'confirmRemove' => __( 'Remove all demo Votes, demo Members, their Ballots and the demo images? Nothing else is touched.', 'nera-vote' ),
					'confirmHide'   => __( 'Hide this tool? The Tools > Nera Vote Demo Seeder menu item disappears. Nothing else changes.', 'nera-vote' ),
					'working'       => __( 'Working…', 'nera-vote' ),
					'failed'        => __( 'Failed', 'nera-vote' ),
					'done'          => __( 'Done', 'nera-vote' ),
					'edit'          => __( 'Edit', 'nera-vote' ),
					'none'          => __( 'No demo data yet.', 'nera-vote' ),
				),
			)
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use this tool.', 'nera-vote' ) );
		}
		?>
		<div class="wrap nera-vote-demo">
			<h1><?php esc_html_e( 'Nera Vote Demo Seeder', 'nera-vote' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Creates three demo Votes (open, scheduled and closed) with 3 to 4 Options each, demo Members and random Ballots so you can see the front end and the Results screen. Each Option gets a generated image so the popup can be checked. Remove deletes only what this tool created, images included.', 'nera-vote' ); ?></p>

			<?php if ( ! self::creation_allowed() ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'This looks like a production site, so creating demo data is switched off. You can still remove demo data. To allow creating it, define NERA_VOTE_ALLOW_DEMO as true.', 'nera-vote' ); ?></p></div>
			<?php endif; ?>

			<table class="widefat striped nera-vote-demo__counts" style="max-width:420px">
				<tbody>
					<tr><td><?php esc_html_e( 'Demo Votes', 'nera-vote' ); ?></td><td id="nv-demo-votes">0</td></tr>
					<tr><td><?php esc_html_e( 'Demo Members', 'nera-vote' ); ?></td><td id="nv-demo-members">0</td></tr>
					<tr><td><?php esc_html_e( 'Demo Ballots', 'nera-vote' ); ?></td><td id="nv-demo-ballots">0</td></tr>
					<tr><td><?php esc_html_e( 'Demo images', 'nera-vote' ); ?></td><td id="nv-demo-images">0</td></tr>
				</tbody>
			</table>

			<p class="nera-vote-demo__actions">
				<label for="nv-demo-count"><?php esc_html_e( 'Demo Members to create', 'nera-vote' ); ?></label>
				<input type="number" id="nv-demo-count" min="5" max="200" value="40" style="width:80px">
				<button type="button" class="button button-primary" id="nv-demo-create"><?php esc_html_e( 'Create demo data', 'nera-vote' ); ?></button>
				<button type="button" class="button" id="nv-demo-remove"><?php esc_html_e( 'Remove demo data', 'nera-vote' ); ?></button>
				<button type="button" class="button" id="nv-demo-hide" style="margin-left:auto" disabled><?php esc_html_e( 'Finish and hide this tool', 'nera-vote' ); ?></button>
			</p>
			<p class="description" id="nv-demo-hide-hint"><?php esc_html_e( 'Hiding is available once there is no demo data. It only removes this menu item.', 'nera-vote' ); ?></p>

			<div class="nera-vote-demo__progress" aria-live="polite">
				<div class="nera-vote-demo__track"><div class="nera-vote-demo__bar" id="nv-demo-bar"></div></div>
				<p class="nera-vote-demo__label" id="nv-demo-label"></p>
			</div>

			<h2><?php esc_html_e( 'Demo Votes', 'nera-vote' ); ?></h2>
			<table class="widefat striped" id="nv-demo-table">
				<thead><tr><th><?php esc_html_e( 'Vote', 'nera-vote' ); ?></th><th><?php esc_html_e( 'Status', 'nera-vote' ); ?></th><th><?php esc_html_e( 'Shortcode', 'nera-vote' ); ?></th><th></th></tr></thead>
				<tbody id="nv-demo-rows"></tbody>
			</table>
			<p class="description"><?php esc_html_e( 'To see a demo Vote on the site, paste its shortcode into a Rich Text component or any page.', 'nera-vote' ); ?></p>

			<h2><?php esc_html_e( 'Log', 'nera-vote' ); ?></h2>
			<pre class="nera-vote-demo__log" id="nv-demo-log" aria-live="polite"></pre>
		</div>
		<?php
	}

	/* ------------------------------------------------------------- Status */

	public static function demo_vote_ids() {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => Nera_Vote_CPT::POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_key'       => self::VOTE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				)
			)
		);
	}

	public static function demo_user_ids( $limit = -1 ) {
		return array_map(
			'intval',
			get_users(
				array(
					'meta_key' => self::USER_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'fields'   => 'ID',
					'number'   => $limit,
					'orderby'  => 'ID',
				)
			)
		);
	}

	/** @return int[] Attachment IDs made by this tool. */
	public static function demo_image_ids( $limit = -1 ) {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => $limit,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'meta_key'       => self::VOTE_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				)
			)
		);
	}

	private static function ballot_count() {
		global $wpdb;
		$ids = self::demo_vote_ids();
		if ( ! $ids ) {
			return 0;
		}
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Nera_Vote_Votes::table() . ' WHERE vote_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function status() {
		$votes = array();
		foreach ( self::demo_vote_ids() as $id ) {
			$votes[] = array(
				'id'        => $id,
				'title'     => html_entity_decode( get_the_title( $id ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'status'    => Nera_Vote_CPT::status( $id ),
				'ballots'   => Nera_Vote_Votes::total( $id ),
				'shortcode' => '[nera_vote id="' . $id . '"]',
				'edit'      => get_edit_post_link( $id, 'raw' ),
			);
		}
		return array(
			'votes'   => $votes,
			'members' => count( self::demo_user_ids() ),
			'ballots' => self::ballot_count(),
			'images'  => count( self::demo_image_ids() ),
		);
	}

	public static function ajax_status() {
		self::guard();
		wp_send_json_success( self::status() );
	}

	/** Hides the tool. Refuses while demo data still exists, so none is left unreachable. */
	public static function ajax_hide() {
		self::guard();
		if ( self::demo_vote_ids() || self::demo_user_ids( 1 ) || self::demo_image_ids( 1 ) ) {
			wp_send_json_error( array( 'message' => __( 'Remove the demo data first.', 'nera-vote' ) ) );
		}
		update_option( self::HIDE_OPT, time(), true );
		wp_send_json_success( array( 'redirect' => admin_url( 'edit.php?post_type=' . Nera_Vote_CPT::POST_TYPE ) ) );
	}

	private static function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
	}

	/* ------------------------------------------------------------- Steps */

	public static function ajax_step() {
		self::guard();
		$op     = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : '';
		$step   = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : 'start';
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$count  = isset( $_POST['members'] ) ? max( 5, min( 200, absint( $_POST['members'] ) ) ) : 40;

		try {
			if ( 'create' === $op ) {
				$result = self::create_step( $step, $offset, $count );
			} elseif ( 'remove' === $op ) {
				$result = self::remove_step( $step, $offset );
			} else {
				wp_send_json_error( array( 'message' => 'Unknown operation.' ) );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$result['status'] = self::status();
		wp_send_json_success( $result );
	}

	/**
	 * @return array{message:string,percent:int,next:?array,log:string[]}|WP_Error
	 */
	private static function create_step( $step, $offset, $count ) {
		switch ( $step ) {
			case 'start':
				if ( ! self::creation_allowed() ) {
					return new WP_Error( 'nv_demo_blocked', __( 'Creating demo data is switched off on production.', 'nera-vote' ) );
				}
				if ( self::demo_vote_ids() || self::demo_user_ids( 1 ) || self::demo_image_ids( 1 ) ) {
					return new WP_Error( 'nv_demo_exists', __( 'Demo data already exists. Remove it first.', 'nera-vote' ) );
				}
				return self::out( __( 'Checked: no demo data yet.', 'nera-vote' ), 3, array( 'step' => 'votes' ) );

			case 'votes':
				$made = array();
				foreach ( self::definitions() as $def ) {
					$made[] = self::create_vote( $def );
				}
				return self::out(
					sprintf( /* translators: %d: number of Votes. */ __( 'Created %d demo Votes.', 'nera-vote' ), count( $made ) ),
					10,
					array( 'step' => 'images' )
				);

			case 'images':
				if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) {
					return self::out(
						__( 'The PHP GD extension is not available, so the demo Options have no images.', 'nera-vote' ),
						30,
						array(
							'step'   => 'members',
							'offset' => 0,
						)
					);
				}
				$made    = self::create_images( self::IMG_BATCH );
				$missing = self::options_without_image();
				$total   = self::demo_option_total();
				$done    = max( 0, $total - $missing );
				$percent = 10 + (int) round( ( $total ? $done / $total : 1 ) * 20 );
				return self::out(
					sprintf( /* translators: 1: images made so far, 2: total. */ __( 'Created images %1$d of %2$d.', 'nera-vote' ), $done, $total ),
					$percent,
					$missing && $made ? array( 'step' => 'images' ) : array(
						'step'   => 'members',
						'offset' => 0,
					)
				);

			case 'members':
				$end = min( $count, $offset + self::BATCH );
				for ( $n = $offset + 1; $n <= $end; $n++ ) {
					self::create_member( $n );
				}
				$percent = 30 + (int) round( ( $end / $count ) * 40 );
				$next    = $end < $count ? array( 'step' => 'members', 'offset' => $end ) : array( 'step' => 'ballots' );
				return self::out(
					sprintf( /* translators: 1: created so far, 2: total. */ __( 'Created Members %1$d of %2$d.', 'nera-vote' ), $end, $count ),
					$percent,
					$next
				);

			case 'ballots':
				$total = self::cast_ballots();
				return self::out(
					sprintf( /* translators: %s: number of Ballots. */ __( 'Cast %s demo Ballots on the open and closed Votes.', 'nera-vote' ), number_format_i18n( $total ) ),
					94,
					array( 'step' => 'finish' )
				);

			case 'finish':
				return self::out( __( 'Demo data created.', 'nera-vote' ), 100, null );
		}
		return new WP_Error( 'nv_demo_step', 'Unknown step.' );
	}

	private static function remove_step( $step, $offset ) {
		switch ( $step ) {
			case 'start':
				$members = count( self::demo_user_ids() );
				$votes   = count( self::demo_vote_ids() );
				$images  = count( self::demo_image_ids() );
				if ( ! $members && ! $votes && ! $images ) {
					return self::out( __( 'There is no demo data to remove.', 'nera-vote' ), 100, null );
				}
				return self::out(
					sprintf( /* translators: 1: Votes, 2: Members, 3: images. */ __( 'Found %1$d demo Votes, %2$d demo Members and %3$d demo images.', 'nera-vote' ), $votes, $members, $images ),
					5,
					array( 'step' => 'votes' )
				);

			case 'votes':
				$ids = self::demo_vote_ids();
				foreach ( $ids as $id ) {
					wp_delete_post( $id, true ); // Also deletes the Vote's Ballots.
				}
				return self::out(
					sprintf( /* translators: %d: number of Votes. */ __( 'Deleted %d demo Votes and their Ballots.', 'nera-vote' ), count( $ids ) ),
					20,
					array( 'step' => 'images' )
				);

			case 'images':
				$batch = self::demo_image_ids( self::BATCH );
				foreach ( $batch as $att_id ) {
					wp_delete_attachment( $att_id, true ); // Also deletes the files.
				}
				$left = count( self::demo_image_ids() );
				return self::out(
					sprintf( /* translators: 1: deleted in this step, 2: still to delete. */ __( 'Deleted %1$d demo images (%2$d left).', 'nera-vote' ), count( $batch ), $left ),
					$left ? 35 : 45,
					$left && $batch ? array( 'step' => 'images' ) : array(
						'step'   => 'members',
						'offset' => 0,
					)
				);

			case 'members':
				require_once ABSPATH . 'wp-admin/includes/user.php';
				$batch   = self::demo_user_ids( self::BATCH );
				$deleted = 0;
				foreach ( $batch as $uid ) {
					// Marked users only, and never an administrator or the signed-in user.
					if ( get_current_user_id() === $uid || user_can( $uid, 'manage_options' ) ) {
						delete_user_meta( $uid, self::USER_META );
						continue;
					}
					if ( wp_delete_user( $uid ) ) {
						++$deleted;
					}
				}
				$left    = count( self::demo_user_ids() );
				$percent = $left ? 70 : 95;
				return self::out(
					sprintf( /* translators: 1: deleted in this step, 2: still to delete. */ __( 'Deleted %1$d demo Members (%2$d left).', 'nera-vote' ), $deleted, $left ),
					$percent,
					$left ? array( 'step' => 'members', 'offset' => 0 ) : array( 'step' => 'finish' )
				);

			case 'finish':
				return self::out( __( 'Demo data removed.', 'nera-vote' ), 100, null );
		}
		return new WP_Error( 'nv_demo_step', 'Unknown step.' );
	}

	private static function out( $message, $percent, $next ) {
		return array(
			'message' => $message,
			'percent' => (int) $percent,
			'next'    => $next,
		);
	}

	/* -------------------------------------------------------------- Data */

	private static function bottles() {
		return array(
			array( 'Cedar Dusk', 'Dry cedar and warm paper, with a faint smoke at the end.' ),
			array( 'Salt & Rose', 'A sea-washed rose with mineral salt and pale musk.' ),
			array( 'Green Vetiver', 'Cut grass, vetiver root and a crisp bitter-green opening.' ),
			array( 'Fig Smoke', 'Milky fig leaf over birch tar and a little vanilla.' ),
			array( 'Midnight Tonka', 'Tonka bean, black pepper and a dark, powdery sweetness.' ),
			array( 'Amber Orchard', 'Baked apple, amber resin and soft cinnamon.' ),
			array( 'Wet Stone', 'Rain on slate, moss and cold metal.' ),
			array( 'Black Tea & Honey', 'Smoky tea leaves stirred into warm beeswax.' ),
			array( 'Linen Rain', 'Clean cotton, ozone and a hint of white flower.' ),
			array( 'Burnt Sugar', 'Caramel edges, toasted almond and soft leather.' ),
			array( 'Pine Resin', 'Sticky conifer sap, juniper and a cool balsamic base.' ),
			array( 'Violet Leaf', 'Green violet leaf, cucumber skin and powdery iris.' ),
		);
	}

	private static function definitions() {
		$now = time();
		$day = DAY_IN_SECONDS;
		return array(
			array(
				'title'   => 'Demo: Which bottle should we list next?',
				'start'   => $now - $day,
				'end'     => $now + 6 * $day,
				'show'    => 'while_open',
				'options' => array_slice( self::bottles(), 0, 4 ),
			),
			array(
				'title'   => 'Demo: Next month\'s box',
				'start'   => $now + 3 * $day,
				'end'     => $now + 10 * $day,
				'show'    => 'after_close',
				'options' => array_slice( self::bottles(), 6, 3 ),
			),
			array(
				'title'   => 'Demo: Last week\'s pick',
				'start'   => $now - 8 * $day,
				'end'     => $now - $day,
				'show'    => 'after_close',
				'options' => array_slice( self::bottles(), 2, 3 ),
			),
		);
	}

	private static function create_vote( array $def ) {
		$id = wp_insert_post(
			array(
				'post_type'   => Nera_Vote_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $def['title'],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $id->get_error_message() ) );
		}

		$options = array();
		foreach ( $def['options'] as $i => $bottle ) {
			$options[] = array(
				'id'          => 'opt_' . strtolower( wp_generate_password( 8, false, false ) ),
				'name'        => $bottle[0],
				'image_id'    => 0,
				'description' => $bottle[1],
				// A few Options carry a link so the popup's "Learn more" button can be checked.
				'link'        => ( 0 === $i % 3 ) ? home_url( '/drops/' ) : '',
				'hidden'      => false,
			);
		}

		update_post_meta( $id, Nera_Vote_CPT::META_START, (int) $def['start'] );
		update_post_meta( $id, Nera_Vote_CPT::META_END, (int) $def['end'] );
		update_post_meta( $id, Nera_Vote_CPT::META_SHOW_RESULTS, $def['show'] );
		update_post_meta( $id, Nera_Vote_CPT::META_PLAY_SOUND, 'yes' );
		update_post_meta( $id, Nera_Vote_CPT::META_OPTIONS, wp_slash( $options ) );
		update_post_meta( $id, self::VOTE_META, time() );
		return (int) $id;
	}

	/** Options of the demo Votes that still have no image (hidden Options are skipped). */
	private static function options_without_image() {
		$n = 0;
		foreach ( self::demo_vote_ids() as $vote_id ) {
			foreach ( Nera_Vote_CPT::options( $vote_id ) as $opt ) {
				if ( ! $opt['hidden'] && ! $opt['image_id'] ) {
					++$n;
				}
			}
		}
		return $n;
	}

	private static function demo_option_total() {
		$n = 0;
		foreach ( self::demo_vote_ids() as $vote_id ) {
			foreach ( Nera_Vote_CPT::options( $vote_id ) as $opt ) {
				if ( ! $opt['hidden'] ) {
					++$n;
				}
			}
		}
		return $n;
	}

	/** Makes up to $limit images and attaches them to Options. Returns how many were made. */
	private static function create_images( $limit ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$made = 0;
		foreach ( self::demo_vote_ids() as $v => $vote_id ) {
			$options = Nera_Vote_CPT::options( $vote_id );
			$changed = false;
			foreach ( $options as $i => $opt ) {
				if ( $made >= $limit ) {
					break;
				}
				if ( $opt['hidden'] || $opt['image_id'] ) {
					continue;
				}
				$att = self::make_image( $opt['name'], $v * 5 + $i );
				if ( $att ) {
					$options[ $i ]['image_id'] = $att;
					$changed                   = true;
					++$made;
				}
			}
			if ( $changed ) {
				update_post_meta( $vote_id, Nera_Vote_CPT::META_OPTIONS, wp_slash( $options ) );
			}
			if ( $made >= $limit ) {
				break;
			}
		}
		return $made;
	}

	/** A generated placeholder: a bottle silhouette on a coloured gradient. Returns the attachment ID or 0. */
	private static function make_image( $name, $seed ) {
		$w   = 900;
		$h   = 900;
		$img = imagecreatetruecolor( $w, $h );
		if ( ! $img ) {
			return 0;
		}

		// Two related colours from the seed.
		$hue = ( $seed * 47 ) % 360;
		list( $r1, $g1, $b1 ) = self::hsl( $hue, 0.35, 0.82 );
		list( $r2, $g2, $b2 ) = self::hsl( $hue, 0.45, 0.45 );
		for ( $y = 0; $y < $h; $y++ ) {
			$t   = $y / ( $h - 1 );
			$col = imagecolorallocate( $img, (int) ( $r1 + ( $r2 - $r1 ) * $t ), (int) ( $g1 + ( $g2 - $g1 ) * $t ), (int) ( $b1 + ( $b2 - $b1 ) * $t ) );
			imageline( $img, 0, $y, $w, $y, $col );
		}

		// Bottle: cap, neck, shoulders, body, label.
		imagealphablending( $img, true );
		$glass = imagecolorallocatealpha( $img, 255, 255, 255, 38 );
		$dark  = imagecolorallocatealpha( $img, 20, 20, 20, 20 );
		$label = imagecolorallocatealpha( $img, 255, 255, 255, 8 );
		$cx    = (int) ( $w / 2 );
		imagefilledrectangle( $img, $cx - 70, 150, $cx + 70, 230, $dark );
		imagefilledrectangle( $img, $cx - 45, 230, $cx + 45, 330, $glass );
		imagefilledellipse( $img, $cx, 340, 330, 150, $glass );
		imagefilledrectangle( $img, $cx - 165, 340, $cx + 165, 740, $glass );
		imagefilledrectangle( $img, $cx - 110, 470, $cx + 110, 640, $label );
		$font = 5;
		$text = substr( $name, 0, 22 );
		$tw   = imagefontwidth( $font ) * strlen( $text );
		imagestring( $img, $font, (int) ( $cx - $tw / 2 ), 545, $text, imagecolorallocate( $img, 30, 30, 30 ) );

		$dir  = wp_upload_dir();
		$file = trailingslashit( $dir['path'] ) . wp_unique_filename( $dir['path'], 'nera-vote-demo-' . sanitize_title( $name ) . '.jpg' );
		$ok   = imagejpeg( $img, $file, 86 );
		imagedestroy( $img );
		if ( ! $ok ) {
			return 0;
		}

		$att_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $name . ' (demo)',
				'post_status'    => 'inherit',
			),
			$file
		);
		if ( ! $att_id || is_wp_error( $att_id ) ) {
			wp_delete_file( $file );
			return 0;
		}
		wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $file ) );
		update_post_meta( $att_id, '_wp_attachment_image_alt', $name );
		update_post_meta( $att_id, self::VOTE_META, time() );
		return (int) $att_id;
	}

	/** @return int[] r, g, b (0 to 255). */
	private static function hsl( $h, $s, $l ) {
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;
		if ( $h < 60 ) {
			$rgb = array( $c, $x, 0 );
		} elseif ( $h < 120 ) {
			$rgb = array( $x, $c, 0 );
		} elseif ( $h < 180 ) {
			$rgb = array( 0, $c, $x );
		} elseif ( $h < 240 ) {
			$rgb = array( 0, $x, $c );
		} elseif ( $h < 300 ) {
			$rgb = array( $x, 0, $c );
		} else {
			$rgb = array( $c, 0, $x );
		}
		return array( (int) round( ( $rgb[0] + $m ) * 255 ), (int) round( ( $rgb[1] + $m ) * 255 ), (int) round( ( $rgb[2] + $m ) * 255 ) );
	}

	private static function create_member( $n ) {
		$login = 'nera_vote_demo_' . $n;
		if ( username_exists( $login ) ) {
			return;
		}
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 24 ),
				'user_email'   => 'nera-vote-demo-' . $n . '@example.test',
				'display_name' => 'Demo Member ' . $n,
				'role'         => get_role( 'customer' ) ? 'customer' : 'subscriber',
			)
		);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $id->get_error_message() ) );
		}
		update_user_meta( $id, self::USER_META, time() );
	}

	/** Random Ballots on the open and closed demo Votes. Returns how many were stored. */
	private static function cast_ballots() {
		$users = self::demo_user_ids();
		$now   = time();
		$total = 0;

		foreach ( self::demo_vote_ids() as $vote_id ) {
			$status = Nera_Vote_CPT::status( $vote_id );
			if ( 'scheduled' === $status ) {
				continue;
			}
			$options = Nera_Vote_CPT::options( $vote_id );
			if ( ! $options ) {
				continue;
			}
			shuffle( $options ); // A different favourite for each Vote.
			$weights = array();
			foreach ( $options as $i => $opt ) {
				$weights[ $opt['id'] ] = 1 / pow( $i + 1, 0.9 );
			}

			$from = Nera_Vote_CPT::start( $vote_id );
			$to   = min( $now, Nera_Vote_CPT::end( $vote_id ) ? Nera_Vote_CPT::end( $vote_id ) : $now );

			foreach ( $users as $uid ) {
				if ( wp_rand( 1, 100 ) > 80 ) {
					continue; // Not everyone votes.
				}
				$picks = self::weighted_pick( $weights, wp_rand( 1, min( 3, count( $options ) ) ) );
				foreach ( $picks as $option_id ) {
					$when = $from < $to ? wp_rand( $from, $to ) : $to;
					if ( Nera_Vote_Votes::add( $vote_id, $option_id, $uid, gmdate( 'Y-m-d H:i:s', $when ) ) ) {
						++$total;
					}
				}
			}
		}
		return $total;
	}

	/** @return string[] Distinct keys, chosen with probability proportional to weight. */
	private static function weighted_pick( array $weights, $how_many ) {
		$chosen = array();
		for ( $i = 0; $i < $how_many && $weights; $i++ ) {
			$sum = array_sum( $weights );
			$r   = ( wp_rand( 0, 1000000 ) / 1000000 ) * $sum;
			foreach ( $weights as $key => $w ) {
				$r -= $w;
				if ( $r <= 0 ) {
					$chosen[] = $key;
					unset( $weights[ $key ] );
					break;
				}
			}
		}
		return $chosen;
	}
}
