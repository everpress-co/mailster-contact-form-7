<?php

class MailsterCF7 {

	private $plugin_path;
	private $plugin_url;

	private $userdata;

	public function __construct() {

		$this->plugin_path = plugin_dir_path( MAILSTER_CF7_FILE );
		$this->plugin_url  = plugin_dir_url( MAILSTER_CF7_FILE );

		register_activation_hook( MAILSTER_CF7_FILE, array( &$this, 'activate' ) );
		register_deactivation_hook( MAILSTER_CF7_FILE, array( &$this, 'deactivate' ) );

		load_plugin_textdomain( 'mailster-cf7' );

		add_action( 'init', array( &$this, 'init' ) );
	}


	/**
	 *
	 *
	 * @param unknown $network_wide
	 */
	public function activate( $network_wide ) {}


	/**
	 *
	 *
	 * @param unknown $network_wide
	 */
	public function deactivate( $network_wide ) {}


	public function init() {

		add_action( 'wpcf7_validate', array( $this, 'validate' ), 10, 2 );
		add_filter( 'wpcf7_editor_panels', array( $this, 'panel' ) );
		add_filter( 'wpcf7_contact_form_properties', array( $this, 'form_properties' ), 10, 2 );
		add_action( 'wpcf7_save_contact_form', array( $this, 'save' ) );
		add_action( 'wpcf7_skip_mail', array( $this, 'skip_mail' ), 10, 2 );
		add_filter( 'wpcf7_pre_construct_contact_form_properties', array( $this, 'form_properties' ), 10, 2 );

	}


	/**
	 *
	 *
	 * @param unknown $result
	 * @param unknown $tags
	 * @return unknown
	 */
	public function validate( $result, $tags ) {

		if ( ! $result->is_valid() ) {
			return $result;
		}

		if ( ! function_exists( 'mailster' ) ) {
			return $result;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission || ! $posted_data = $submission->get_posted_data() ) {
			return $result;
		}

		$form = WPCF7_ContactForm::get_current();

		$properties = $form->get_properties();

		// no Mailster settings
		if ( ! isset( $properties['mailster'] ) ) {
			return $result;
		}
		$properties = $properties['mailster'];

		// not enabled
		if ( ! $properties['enabled'] ) {
			return $result;
		}

		// checkbox defined but not checked
		if ( isset( $properties['checkbox'] ) && $properties['checkbox'] && empty( $posted_data[ $properties['checkboxfield'] ][0] ) ) {
			return $result;
		}

		$this->userdata = array();
		$tag_keys       = array_flip( array_filter( wp_list_pluck( $tags, 'name' ) ) );

		foreach ( $properties['fields'] as $field => $tag ) {
			$this->userdata[ $field ] = is_array( $posted_data[ $tag ] ) ? $posted_data[ $tag ][0] : $posted_data[ $tag ];
		}

		$this->userdata['status'] = $properties['doubleoptin'] ? 0 : 1;

		if ( isset( $properties['gdpr_timestamp'] ) && $properties['gdpr_timestamp'] ) {
			$this->userdata['gdpr'] = time();
		}

		$this->userdata = apply_filters( 'mailster_verify_subscriber', $this->userdata );

		if ( is_wp_error( $this->userdata ) ) {

			$result->invalidate( $tags[ $tag_keys[ $properties['fields'][ $this->userdata->get_error_code() ] ] ], $this->userdata->get_error_message() );
			return $result;
		}

		$overwrite = $properties['overwrite'];

		if ( ! $overwrite && mailster( 'subscribers' )->get_by_mail( $this->userdata['email'] ) ) {
			$error_message = isset( $properties['error_message'] ) ? $properties['error_message'] : __( 'You are already registered!', 'mailster-cf7' );
			$result->invalidate( $tags[ $tag_keys[ $properties['fields']['email'] ] ], $error_message );
			return $result;
		}

		add_action( 'wpcf7_mail_sent', array( $this, 'add_subscriber' ) );
		return $result;

	}


	/**
	 *
	 *
	 * @param unknown $contact_form
	 */
	public function add_subscriber( $contact_form ) {

		$form = WPCF7_ContactForm::get_current();

		$properties = $form->get_properties();
		$properties = $properties['mailster'];

		$list_ids  = isset( $properties['lists'] ) ? (array) $properties['lists'] : null;
		$overwrite = 1 == $properties['overwrite'];
		$merge     = 3 == $properties['overwrite'];

		// add subscriber
		$subscriber_id = mailster( 'subscribers' )->add( $this->userdata, $overwrite || $merge, $merge );

		// no error
		if ( ! is_wp_error( $subscriber_id ) && $list_ids ) {

			mailster( 'subscribers' )->assign_lists( $subscriber_id, $list_ids );

		}
	}


	/**
	 *
	 *
	 * @param unknown $contact_form
	 */
	public function save( $contact_form ) {

		if ( ! isset( $_POST['mailster'] ) ) {
			return;
		}

		$properties['mailster'] = $_POST['mailster'];

		if ( ! isset( $properties['mailster']['fields'] ) ) {
			$properties['mailster']['fields'] = array();
		}
		if ( ! isset( $properties['mailster']['tags'] ) ) {
			$properties['mailster']['tags'] = array();
		}

		$properties['mailster']['fields'] = array_combine( $properties['mailster']['fields'], $properties['mailster']['tags'] );

		if ( isset( $properties['mailster']['fields'][-1] ) ) {
			unset( $properties['mailster']['fields'][-1] );
		}
		unset( $properties['mailster']['tags'] );

		$contact_form->set_properties( $properties );

	}


	/**
	 *
	 *
	 * @param unknown $properties
	 * @param unknown $form
	 * @return unknown
	 */
	public function form_properties( $properties, $form ) {

		$properties['mailster'] = isset( $properties['mailster'] ) ? $properties['mailster'] : array();

		return $properties;
	}


	/**
	 *
	 *
	 * @param unknown $skip_mail
	 * @param unknown $contact_form
	 * @return unknown
	 */
	public function skip_mail( $skip_mail, $contact_form ) {

		$properties = $contact_form->get_properties();

		if ( ! isset( $properties['mailster'] ) ) {
			return $skip_mail;
		}
		$properties = $properties['mailster'];

		return $properties['skip_mail'];

	}


	/**
	 *
	 *
	 * @param unknown $panels
	 * @return unknown
	 */
	public function panel( $panels ) {

		$panels['mailster'] = array(
			'title'    => 'Mailster',
			'callback' => array( $this, 'editor_panel' ),
		);

		return $panels;

	}


	/**
	 *
	 *
	 * @param unknown $post
	 */
	public function editor_panel( $post ) {

		// check if Mailster is enabled
		if ( ! function_exists( 'mailster' ) ) {

			$all_plugins = get_plugins();

			if ( isset( $all_plugins['mailster/mailster.php'] ) ) {

				echo '<div class="error inline"><p>Please enable the <a href="plugins.php#mailster-email-newsletter-plugin-for-wordpress">Mailster Newsletter Plugin</a> to get access to this tab</p></div>';

			} else {

				echo '<div class="error inline"><p>You need the <a href="https://mailster.co/?utm_campaign=wporg&utm_source=wordpress.org&utm_medium=plugin&utm_term=Contact+Form+7">Mailster Newsletter Plugin for WordPress</a> to use your Form with Mailster</p></div>';

			}

			return;
		}

		include $this->plugin_path . '/views/editorpanel.php';

	}


	/**
	 *
	 *
	 * @param unknown $tags
	 * @param unknown $selected
	 * @param unknown $name
	 * @return unknown
	 */
	private function get_tags_dropdown( $tags, $selected, $name ) {

		$tagsdropdown  = '<select name="' . esc_attr( $name ) . '">';
		$tagsdropdown .= '<option value="0">' . __( 'choose tag', 'mailster-cf7' ) . '</option>';
		foreach ( $tags as $tag ) {
			if ( ! empty( $tag ) ) {
				$tagsdropdown .= '<option value="' . esc_attr( $tag ) . '" ' . selected( $selected, $tag, false ) . '>[' . $tag . ']</option>';
			}
		}
		$tagsdropdown .= '<select>';

		return $tagsdropdown;
	}


}
