<?php
/**
 * Suggestions: bottles Members propose through a Fluent Forms form that this plugin
 * creates. Fluent Forms stores the entries; this class owns the rules around them
 * (who may submit, how many, duplicates), reads them back grouped by bottle, and keeps
 * each Suggestion's status.
 *
 * A Suggestion's status lives in Fluent Forms' submission meta, so nothing here adds a
 * table: `nera_vote_status` is shortlisted or declined (no row means New) and
 * `nera_vote_option` links a shortlisted Suggestion to the Option it became.
 *
 * @package Nera_Vote
 */

defined( 'ABSPATH' ) || exit;

class Nera_Vote_Suggestions {

	const FORM_TITLE   = 'Nera Vote suggest a bottle';
	const OPTION_FORM  = 'nera_vote_form_id';
	const OPTION_LIMIT = 'nera_vote_suggest_limit';
	const WINDOW_DAYS  = 7;
	const DEFAULT_LIMIT = 3;

	const META_STATUS = 'nera_vote_status';
	const META_OPTION = 'nera_vote_option';

	public static function init() {
		add_filter( 'fluentform/validation_errors', array( __CLASS__, 'validate' ), 10, 4 );
		add_action( 'admin_init', array( __CLASS__, 'ensure_form' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice_missing_fluent_forms' ) );
	}

	/* ------------------------------------------------------------ settings */

	public static function limit() {
		$limit = (int) get_option( self::OPTION_LIMIT, self::DEFAULT_LIMIT );
		return max( 1, min( 50, $limit ? $limit : self::DEFAULT_LIMIT ) );
	}

	/* ---------------------------------------------------------------- form */

	/** The plugin is active (not merely its tables left behind). */
	public static function fluent_forms_active() {
		return defined( 'FLUENTFORM' ) && self::tables_exist();
	}

	private static function tables_exist() {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_forms';
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private static function form_exists( $form_id ) {
		global $wpdb;
		return (int) $form_id > 0 && (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE id = %d", $form_id ) ) === (int) $form_id;
	}

	/** The form to render, or 0 when Fluent Forms is not active or the form does not exist yet. */
	public static function form_id() {
		if ( ! self::fluent_forms_active() ) {
			return 0;
		}
		$stored = (int) get_option( self::OPTION_FORM, 0 );
		if ( $stored && self::form_exists( $stored ) ) {
			return $stored;
		}
		global $wpdb;
		$found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}fluentform_forms WHERE title = %s ORDER BY id ASC LIMIT 1", self::FORM_TITLE ) );
		if ( $found ) {
			update_option( self::OPTION_FORM, $found, false );
		}
		return $found;
	}

	/** Finds the form (by id, then by title) or creates it. Safe to call any number of times. */
	public static function ensure_form() {
		if ( ! self::fluent_forms_active() ) {
			return 0;
		}
		$id = self::form_id();
		if ( $id ) {
			return $id;
		}
		$id = self::create_form();
		if ( $id ) {
			update_option( self::OPTION_FORM, $id, false );
		}
		return $id;
	}

	public static function notice_missing_fluent_forms() {
		if ( ! current_user_can( 'manage_options' ) || self::fluent_forms_active() ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || Nera_Vote_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Nera – Vote: Fluent Forms is not active, so the "Suggest a bottle" button is hidden on the site. Activate Fluent Forms to let Members suggest bottles.', 'nera-vote' ) . '</p></div>';
	}

	private static function text_field( $index, $name, $label, $placeholder ) {
		return array(
			'index'          => $index,
			'element'        => 'input_text',
			'attributes'     => array(
				'type'        => 'text',
				'name'        => $name,
				'value'       => '',
				'id'          => '',
				'class'       => '',
				'placeholder' => $placeholder,
				'maxlength'   => '120',
			),
			'settings'       => array(
				'container_class'    => 'nera-vote-ff-' . $name,
				'label'              => $label,
				'label_placement'    => 'top',
				'admin_field_label'  => $label,
				'help_message'       => '',
				'validation_rules'   => array(
					'required' => array(
						'value'   => true,
						'message' => __( 'This field is required', 'nera-vote' ),
						'global'  => false,
					),
				),
				'conditional_logics' => array(),
			),
			'editor_options' => array(
				'title'      => $label,
				'icon_class' => 'ff-edit-text',
				'template'   => 'inputText',
			),
			'uniqElKey'      => 'el_nera_vote_' . $name,
		);
	}

	private static function create_form() {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$fields = array(
			'fields'       => array(
				self::text_field( 1, 'bottle_name', __( 'Bottle name', 'nera-vote' ), __( 'For example Santal 33', 'nera-vote' ) ),
				self::text_field( 2, 'brand', __( 'Brand', 'nera-vote' ), __( 'For example Le Labo', 'nera-vote' ) ),
				array(
					'index'          => 3,
					'element'        => 'textarea',
					'attributes'     => array(
						'name'        => 'note',
						'value'       => '',
						'id'          => '',
						'class'       => '',
						'placeholder' => __( 'Why should we list it? (optional)', 'nera-vote' ),
						'rows'        => 4,
						'cols'        => 2,
						'maxlength'   => '500',
					),
					'settings'       => array(
						'container_class'    => 'nera-vote-ff-note',
						'label'              => __( 'Why this bottle', 'nera-vote' ),
						'admin_field_label'  => __( 'Why this bottle', 'nera-vote' ),
						'label_placement'    => 'top',
						'help_message'       => '',
						'prefix_label'       => '',
						'suffix_label'       => '',
						'validation_rules'   => array(
							'required' => array(
								'value'   => false,
								'message' => '',
								'global'  => false,
							),
						),
						'conditional_logics' => array(),
					),
					'editor_options' => array(
						'title'      => __( 'Text Area', 'nera-vote' ),
						'icon_class' => 'ff-edit-textarea',
						'template'   => 'inputTextarea',
					),
					'uniqElKey'      => 'el_nera_vote_note',
				),
			),
			'submitButton' => array(
				'uniqElKey'      => 'el_nera_vote_submit',
				'element'        => 'button',
				'attributes'     => array(
					'type'  => 'submit',
					'class' => 'nera-vote-ff-submit',
				),
				'settings'       => array(
					'align'            => 'left',
					'button_style'     => 'default',
					'container_class'  => 'nera-vote-ff-submit-wrap',
					'help_message'     => '',
					'background_color' => '#000000',
					'button_size'      => 'md',
					'color'            => '#ffffff',
					'button_ui'        => array(
						'type'    => 'default',
						'text'    => __( 'Send suggestion', 'nera-vote' ),
						'img_url' => '',
					),
				),
				'editor_options' => array(
					'title' => __( 'Submit Button', 'nera-vote' ),
				),
			),
		);

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'fluentform_forms',
			array(
				'title'       => self::FORM_TITLE,
				'status'      => 'published',
				'form_fields' => wp_json_encode( $fields ),
				'has_payment' => 0,
				'type'        => 'form',
				'created_by'  => get_current_user_id(),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return 0;
		}
		$form_id = (int) $wpdb->insert_id;

		$settings = class_exists( '\FluentForm\App\Models\Form' ) ? \FluentForm\App\Models\Form::getFormsDefaultSettings() : array();
		if ( ! isset( $settings['confirmation'] ) || ! is_array( $settings['confirmation'] ) ) {
			$settings['confirmation'] = array();
		}
		$settings['confirmation']['redirectTo']            = 'samePage';
		$settings['confirmation']['messageToShow']         = __( 'Thanks. We have saved your suggestion.', 'nera-vote' );
		$settings['confirmation']['samePageFormBehavior']  = 'hide_form';
		if ( ! isset( $settings['layout'] ) || ! is_array( $settings['layout'] ) ) {
			$settings['layout'] = array();
		}
		$settings['layout']['labelPlacement'] = 'top';

		$wpdb->insert(
			$wpdb->prefix . 'fluentform_form_meta',
			array(
				'form_id'  => $form_id,
				'meta_key' => 'formSettings',
				'value'    => wp_json_encode( $settings ),
			),
			array( '%d', '%s', '%s' )
		);
		$wpdb->insert(
			$wpdb->prefix . 'fluentform_form_meta',
			array(
				'form_id'  => $form_id,
				'meta_key' => 'template_name',
				'value'    => 'blank_form',
			),
			array( '%d', '%s', '%s' )
		);

		do_action(
			'fluentform/inserted_new_form',
			$form_id,
			array(
				'title'       => self::FORM_TITLE,
				'form_fields' => wp_json_encode( $fields ),
			)
		);

		return $form_id;
	}

	/* ---------------------------------------------------------- validation */

	/**
	 * A bottle and brand as one comparable string: lower case, no accents, single spaces,
	 * punctuation dropped. "Santal 33", "SANTAL  33!" and "Santal 33" are the same bottle.
	 */
	public static function key( $name, $brand ) {
		$norm = static function ( $text ) {
			$text = remove_accents( wp_strip_all_tags( (string) $text ) );
			$text = strtolower( $text );
			$text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
			return trim( (string) $text );
		};
		return $norm( $name ) . '|' . $norm( $brand );
	}

	/** Fluent Forms validation: members only, one entry per bottle, a limit per week. */
	public static function validate( $errors, $data, $form, $fields ) {
		unset( $fields );
		if ( ! is_object( $form ) || (int) $form->id !== self::form_id() || ! self::form_id() ) {
			return $errors;
		}

		if ( ! is_user_logged_in() ) {
			$errors['bottle_name'] = array( __( 'Sign in to suggest a bottle.', 'nera-vote' ) );
			return $errors;
		}

		$name  = isset( $data['bottle_name'] ) ? (string) $data['bottle_name'] : '';
		$brand = isset( $data['brand'] ) ? (string) $data['brand'] : '';
		$key   = self::key( $name, $brand );
		$user  = get_current_user_id();

		$mine = self::rows_for_user( $user );
		foreach ( $mine as $row ) {
			if ( self::key( $row['bottle'], $row['brand'] ) === $key ) {
				$errors['bottle_name'] = array( __( 'You\'ve already suggested this bottle.', 'nera-vote' ) );
				return $errors;
			}
		}

		$since  = time() - ( self::WINDOW_DAYS * DAY_IN_SECONDS );
		$recent = 0;
		foreach ( $mine as $row ) {
			if ( $row['time'] >= $since ) {
				++$recent;
			}
		}
		if ( $recent >= self::limit() ) {
			$errors['bottle_name'] = array( __( 'You\'ve used all your suggestions for now. Please try again later in the week.', 'nera-vote' ) );
		}
		return $errors;
	}

	/* ---------------------------------------------------------------- data */

	/**
	 * One normalised row per stored submission.
	 *
	 * @return array<int,array{id:int,user_id:int,bottle:string,brand:string,note:string,time:int}>
	 */
	private static function rows( $where_user = 0 ) {
		global $wpdb;
		$form_id = self::form_id();
		if ( ! $form_id ) {
			return array();
		}
		$sql  = "SELECT id, user_id, response, created_at FROM {$wpdb->prefix}fluentform_submissions WHERE form_id = %d AND status != 'trashed'";
		$args = array( $form_id );
		if ( $where_user ) {
			$sql   .= ' AND user_id = %d';
			$args[] = (int) $where_user;
		}
		$sql .= ' ORDER BY id ASC';
		$out  = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$resp = json_decode( (string) $r->response, true );
			if ( ! is_array( $resp ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (int) $r->id,
				'user_id' => (int) $r->user_id,
				'bottle'  => isset( $resp['bottle_name'] ) ? sanitize_text_field( $resp['bottle_name'] ) : '',
				'brand'   => isset( $resp['brand'] ) ? sanitize_text_field( $resp['brand'] ) : '',
				'note'    => isset( $resp['note'] ) ? sanitize_textarea_field( $resp['note'] ) : '',
				'time'    => (int) strtotime( $r->created_at . ' ' . wp_timezone_string() ),
			);
		}
		return $out;
	}

	private static function rows_for_user( $user_id ) {
		return self::rows( (int) $user_id );
	}

	/** Status and link of every submission, keyed by submission id. */
	private static function meta_map( array $ids ) {
		global $wpdb;
		$map = array();
		if ( ! $ids ) {
			return $map;
		}
		$list = implode( ',', array_map( 'intval', $ids ) );
		$res  = $wpdb->get_results( "SELECT response_id, meta_key, value FROM {$wpdb->prefix}fluentform_submission_meta WHERE response_id IN ({$list}) AND meta_key IN ('" . self::META_STATUS . "','" . self::META_OPTION . "')" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		foreach ( (array) $res as $m ) {
			$map[ (int) $m->response_id ][ $m->meta_key ] = (string) $m->value;
		}
		return $map;
	}

	public static function set_status( array $submission_ids, $status, $option_link = '' ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'fluentform_submission_meta';
		$form_id = self::form_id();
		foreach ( $submission_ids as $sid ) {
			$sid = (int) $sid;
			$wpdb->delete( $table, array( 'response_id' => $sid, 'meta_key' => self::META_STATUS ), array( '%d', '%s' ) );
			$wpdb->delete( $table, array( 'response_id' => $sid, 'meta_key' => self::META_OPTION ), array( '%d', '%s' ) );
			if ( 'new' === $status ) {
				continue;
			}
			$now = current_time( 'mysql' );
			$wpdb->insert(
				$table,
				array( 'response_id' => $sid, 'form_id' => $form_id, 'meta_key' => self::META_STATUS, 'value' => $status, 'created_at' => $now, 'updated_at' => $now ),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
			if ( $option_link ) {
				$wpdb->insert(
					$table,
					array( 'response_id' => $sid, 'form_id' => $form_id, 'meta_key' => self::META_OPTION, 'value' => $option_link, 'created_at' => $now, 'updated_at' => $now ),
					array( '%d', '%d', '%s', '%s', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Suggestions grouped by bottle, most-suggested first.
	 *
	 * @return array<string,array{key:string,bottle:string,brand:string,count:int,notes:array,ids:array,status:string,first:int,last:int,links:array}>
	 */
	public static function groups() {
		$rows = self::rows();
		$meta = self::meta_map( wp_list_pluck( $rows, 'id' ) );
		$out  = array();
		foreach ( $rows as $row ) {
			if ( '' === self::key( $row['bottle'], $row['brand'] ) || '|' === self::key( $row['bottle'], $row['brand'] ) ) {
				continue;
			}
			$k = self::key( $row['bottle'], $row['brand'] );
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array(
					'key'      => $k,
					'bottle'   => $row['bottle'],
					'brand'    => $row['brand'],
					'users'    => array(),
					'notes'    => array(),
					'ids'      => array(),
					'statuses' => array(),
					'links'    => array(),
					'first'    => $row['time'],
					'last'     => $row['time'],
				);
			}
			$g                    = &$out[ $k ];
			$g['users'][ $row['user_id'] ] = true;
			if ( '' !== $row['note'] ) {
				$g['notes'][] = $row['note'];
			}
			$g['ids'][]      = $row['id'];
			$g['statuses'][] = isset( $meta[ $row['id'] ][ self::META_STATUS ] ) ? $meta[ $row['id'] ][ self::META_STATUS ] : 'new';
			if ( isset( $meta[ $row['id'] ][ self::META_OPTION ] ) ) {
				$g['links'][] = $meta[ $row['id'] ][ self::META_OPTION ];
			}
			$g['last'] = max( $g['last'], $row['time'] );
			unset( $g );
		}
		foreach ( $out as &$g ) {
			$g['count'] = count( $g['users'] );
			if ( in_array( 'shortlisted', $g['statuses'], true ) ) {
				$g['status'] = 'shortlisted';
			} elseif ( ! in_array( 'new', $g['statuses'], true ) ) {
				$g['status'] = 'declined';
			} else {
				$g['status'] = 'new';
			}
			unset( $g['users'], $g['statuses'] );
		}
		unset( $g );
		uasort(
			$out,
			static function ( $a, $b ) {
				return $b['count'] <=> $a['count'] ?: $b['last'] <=> $a['last'];
			}
		);
		return $out;
	}

	/** Adds each chosen Suggestion to a Vote as a new Option and marks it On a shortlist. */
	public static function add_to_vote( array $keys, $vote_id ) {
		$vote_id = (int) $vote_id;
		if ( Nera_Vote_CPT::POST_TYPE !== get_post_type( $vote_id ) || 'closed' === Nera_Vote_CPT::status( $vote_id ) ) {
			return 0;
		}
		$groups  = self::groups();
		$options = Nera_Vote_CPT::options( $vote_id );
		$added   = 0;
		foreach ( $keys as $key ) {
			if ( ! isset( $groups[ $key ] ) ) {
				continue;
			}
			$g       = $groups[ $key ];
			$present = null;
			foreach ( $options as $opt ) {
				if ( $opt['source'] === $key ) {
					$present = $opt;
					break;
				}
			}
			if ( $present ) {
				self::set_status( $g['ids'], 'shortlisted', $vote_id . ':' . $present['id'] );
				continue;
			}
			$taken = array();
			foreach ( $options as $opt ) {
				$taken[ $opt['id'] ] = true;
			}
			do {
				$oid = 'opt_' . strtolower( wp_generate_password( 8, false, false ) );
			} while ( isset( $taken[ $oid ] ) );
			$options[] = array(
				'id'          => $oid,
				'name'        => '' !== $g['brand'] ? sprintf( /* translators: 1: bottle name, 2: brand. */ __( '%1$s by %2$s', 'nera-vote' ), $g['bottle'], $g['brand'] ) : $g['bottle'],
				'image_id'    => 0,
				'description' => '',
				'link'        => '',
				'hidden'      => false,
				'source'      => $key,
			);
			self::set_status( $g['ids'], 'shortlisted', $vote_id . ':' . $oid );
			++$added;
		}
		if ( $added ) {
			update_post_meta( $vote_id, Nera_Vote_CPT::META_OPTIONS, wp_slash( $options ) );
		}
		return $added;
	}

	public static function decline( array $keys ) {
		$groups = self::groups();
		$n      = 0;
		foreach ( $keys as $key ) {
			if ( isset( $groups[ $key ] ) && 'shortlisted' !== $groups[ $key ]['status'] ) {
				self::set_status( $groups[ $key ]['ids'], 'declined' );
				++$n;
			}
		}
		return $n;
	}

	public static function reset( array $keys ) {
		$groups = self::groups();
		foreach ( $keys as $key ) {
			if ( isset( $groups[ $key ] ) && 'declined' === $groups[ $key ]['status'] ) {
				self::set_status( $groups[ $key ]['ids'], 'new' );
			}
		}
	}

	/**
	 * A Member's own Suggestions, newest first, with status and the Vote they are on.
	 *
	 * @return array<int,array{bottle:string,brand:string,time:int,status:string,vote_id:int,vote_title:string,vote_status:string}>
	 */
	public static function for_user( $user_id ) {
		$rows = array_reverse( self::rows( (int) $user_id ) );
		$meta = self::meta_map( wp_list_pluck( $rows, 'id' ) );
		$out  = array();
		foreach ( $rows as $row ) {
			$status  = isset( $meta[ $row['id'] ][ self::META_STATUS ] ) ? $meta[ $row['id'] ][ self::META_STATUS ] : 'new';
			$link    = isset( $meta[ $row['id'] ][ self::META_OPTION ] ) ? $meta[ $row['id'] ][ self::META_OPTION ] : '';
			$vote_id = $link ? (int) strtok( $link, ':' ) : 0;
			if ( $vote_id && Nera_Vote_CPT::POST_TYPE !== get_post_type( $vote_id ) ) {
				$vote_id = 0;
			}
			$out[] = array(
				'bottle'      => $row['bottle'],
				'brand'       => $row['brand'],
				'time'        => $row['time'],
				'status'      => $status,
				'vote_id'     => $vote_id,
				'vote_title'  => $vote_id ? get_the_title( $vote_id ) : '',
				'vote_status' => $vote_id ? Nera_Vote_CPT::status( $vote_id ) : '',
			);
		}
		return $out;
	}

	/** Removes a Member's Suggestions and the Fluent Forms rows that hang off them. */
	public static function erase_for_user( $user_id ) {
		global $wpdb;
		$ids = wp_list_pluck( self::rows( (int) $user_id ), 'id' );
		if ( ! $ids ) {
			return 0;
		}
		$list = implode( ',', array_map( 'intval', $ids ) );
		$p    = $wpdb->prefix . 'fluentform_';
		$wpdb->query( "DELETE FROM {$p}entry_details WHERE submission_id IN ({$list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$p}submission_meta WHERE response_id IN ({$list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DELETE FROM {$p}submissions WHERE id IN ({$list})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return count( $ids );
	}

	public static function export_for_user( $user_id ) {
		return self::rows( (int) $user_id );
	}

	public static function status_label( $status ) {
		$labels = array(
			'new'         => __( 'New', 'nera-vote' ),
			'shortlisted' => __( 'On a shortlist', 'nera-vote' ),
			'declined'    => __( 'Declined', 'nera-vote' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
