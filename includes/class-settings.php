<?php
/**
 * Votes > Settings: the suggestion limit and the rules shown on the Vote page, plus the
 * state of the form and page this plugin creates.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Settings {

	const GROUP        = 'nera_vote_settings';
	const OPTION_RULES = 'nera_vote_rules_text';
	const SLUG         = 'nera-vote-settings';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_nera_vote_create_page', array( __CLASS__, 'create_page' ) );
	}

	/** One rule per line. */
	public static function default_rules() {
		return implode(
			"\n",
			array(
				__( 'We build each week\'s shortlist from member suggestions we can source genuinely.', 'nera-vote' ),
				__( 'Results are published.', 'nera-vote' ),
				__( 'The most wanted bottles go into future drops.', 'nera-vote' ),
			)
		);
	}

	public static function rules_lines() {
		$text  = (string) get_option( self::OPTION_RULES, '' );
		$text  = '' === trim( $text ) ? self::default_rules() : $text;
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		return array_values( array_filter( array_map( 'trim', (array) $lines ), 'strlen' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nera_Vote_CPT::POST_TYPE,
			__( 'Vote settings', 'nera-vote' ),
			__( 'Settings', 'nera-vote' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function register() {
		register_setting(
			self::GROUP,
			Nera_Vote_Suggestions::OPTION_LIMIT,
			array(
				'type'              => 'integer',
				'default'           => Nera_Vote_Suggestions::DEFAULT_LIMIT,
				'sanitize_callback' => static function ( $value ) {
					return max( 1, min( 50, (int) $value ) );
				},
			)
		);
		register_setting(
			self::GROUP,
			self::OPTION_RULES,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => static function ( $value ) {
					return sanitize_textarea_field( (string) $value );
				},
			)
		);
	}

	public static function create_page() {
		check_admin_referer( 'nera_vote_create_page' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'nera-vote' ), '', array( 'response' => 403 ) );
		}
		delete_option( Nera_Vote_Page::OPTION_PAGE );
		Nera_Vote_Page::ensure_page();
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . Nera_Vote_CPT::POST_TYPE . '&page=' . self::SLUG ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$form_id = Nera_Vote_Suggestions::form_id();
		$page_id = (int) get_option( Nera_Vote_Page::OPTION_PAGE, 0 );
		$page    = $page_id ? get_post( $page_id ) : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vote settings', 'nera-vote' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="nera_vote_suggest_limit"><?php esc_html_e( 'Suggestions per Member each week', 'nera-vote' ); ?></label></th>
						<td>
							<input type="number" min="1" max="50" id="nera_vote_suggest_limit" name="<?php echo esc_attr( Nera_Vote_Suggestions::OPTION_LIMIT ); ?>" value="<?php echo esc_attr( (string) Nera_Vote_Suggestions::limit() ); ?>" class="small-text">
							<p class="description"><?php echo esc_html( sprintf( /* translators: %d: number of days. */ __( 'How many bottles one Member can suggest in any %d days. Suggesting the same bottle twice is never allowed.', 'nera-vote' ), Nera_Vote_Suggestions::WINDOW_DAYS ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="nera_vote_rules_text"><?php esc_html_e( 'Rules on the Vote page', 'nera-vote' ); ?></label></th>
						<td>
							<textarea id="nera_vote_rules_text" name="<?php echo esc_attr( self::OPTION_RULES ); ?>" rows="6" class="large-text"><?php echo esc_textarea( implode( "\n", self::rules_lines() ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One rule per line. Always say the most wanted bottles go into future drops; never promise that a winner drops next week.', 'nera-vote' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Created by this plugin', 'nera-vote' ); ?></h2>
			<table class="widefat striped" style="max-width:60rem">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Suggest a bottle form', 'nera-vote' ); ?></strong></td>
						<td>
							<?php
							if ( $form_id ) {
								echo wp_kses_post(
									sprintf(
										/* translators: 1: form ID, 2: link to the form. */
										__( 'Fluent Forms form #%1$d. %2$s', 'nera-vote' ),
										$form_id,
										'<a href="' . esc_url( admin_url( 'admin.php?page=fluent_forms&route=editor&form_id=' . $form_id ) ) . '">' . esc_html__( 'Edit the form', 'nera-vote' ) . '</a>'
									)
								);
							} elseif ( Nera_Vote_Suggestions::fluent_forms_active() ) {
								esc_html_e( 'Not created yet. It is created when this page or any admin page loads.', 'nera-vote' );
							} else {
								esc_html_e( 'Fluent Forms is not active. Activate it and the form is created.', 'nera-vote' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Vote page', 'nera-vote' ); ?></strong></td>
						<td>
							<?php
							if ( $page && 'trash' !== $page->post_status ) {
								echo wp_kses_post(
									sprintf(
										/* translators: 1: page title, 2: view link, 3: edit link. */
										__( '%1$s. %2$s | %3$s', 'nera-vote' ),
										esc_html( get_the_title( $page ) ),
										'<a href="' . esc_url( get_permalink( $page ) ) . '">' . esc_html__( 'View', 'nera-vote' ) . '</a>',
										'<a href="' . esc_url( get_edit_post_link( $page, '' ) ) . '">' . esc_html__( 'Edit', 'nera-vote' ) . '</a>'
									)
								);
							} else {
								esc_html_e( 'The Vote page was deleted or is in the trash.', 'nera-vote' );
								echo ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nera_vote_create_page' ), 'nera_vote_create_page' ) ) . '">' . esc_html__( 'Create the Vote page again', 'nera-vote' ) . '</a>';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="description"><?php esc_html_e( 'The plugin never edits your menus. Add the Vote page to the main menu yourself.', 'nera-vote' ); ?></p>
		</div>
		<?php
	}
}
