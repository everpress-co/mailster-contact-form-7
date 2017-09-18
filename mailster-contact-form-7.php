<?php
/*
Plugin Name: Mailster Contact Form 7
Plugin URI: https://mailster.co/?utm_campaign=wporg&utm_source=Contact+Form+7+for+Mailster
Description: Create your Signup Forms with Contact Form 7 and allow users to signup to your newsletter
Version: 1.1
Author: EverPress
Author URI: https://mailster.co
License: GPLv2 or later
Text Domain: mailster-cf7
*/

define( 'MAILSTER_CF7_VERSION', '1.1' );
define( 'MAILSTER_CF7_REQUIRED_VERSION', '2.2.10' );

class MailsterCF7 {

	private $plugin_path;
	private $plugin_url;

	private $userdata;

	public function __construct() {

		$this->plugin_path = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugin_dir_url( __FILE__ );

		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

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
		add_action( 'wpcf7_skip_mail', array( $this, 'skip_mail' ), 10 , 2 );

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
		$tag_keys = array_flip( array_filter( wp_list_pluck( $tags, 'name' ) ) );

		foreach ( $properties['fields'] as $field => $tag ) {
			$this->userdata[ $field ] = is_array( $posted_data[ $tag ] ) ? $posted_data[ $tag ][0] : $posted_data[ $tag ];
		}

		$this->userdata['status'] = $properties['doubleoptin'] ? 0 : 1;

		$this->userdata = apply_filters( 'mailster_verify_subscriber', $this->userdata );

		if ( is_wp_error( $this->userdata ) ) {

			$result->invalidate( $tags[ $tag_keys[ $properties['fields'][ $this->userdata->get_error_code() ] ] ], $this->userdata->get_error_message() );
			return $result;
		}

		$overwrite = $properties['overwrite'];

		if ( ! $overwrite && mailster( 'subscribers' )->get_by_mail( $this->userdata['email'] ) ) {
			$error_message = isset( $properties['error_message'] ) ? $properties['error_message'] : __( 'You are already registered!', 'mailster-cf7' );
			$result->invalidate( $tags[ $tag_keys[ $properties['fields']['email'] ] ],  $error_message );
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

		$list_ids = isset( $properties['lists'] ) ? (array) $properties['lists'] : null;

		// add subscriber
		$subscriber_id = mailster( 'subscribers' )->add( $this->userdata, 1 == $properties['overwrite'] );

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

		$properties['mailster'] = isset( $properties['mailster'] ) ? $properties['mailster'] : array() ;

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
			'title' => 'Mailster',
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

				echo '<div class="error inline"><p>You need the <a href="https://mailster.co/?utm_campaign=wporg&utm_source=Contact+Form+7+for+Mailster+Newsletter">Mailster Newsletter Plugin for WordPress</a> to use your Form with Mailster</p></div>';

			}

			return;
		}

		$s = wp_parse_args( $post->prop( 'mailster' ), array(
			'enabled' => false,
			'checkbox' => false,
			'doubleoptin' => true,
			'overwrite' => false,
			'error_message' => __( 'You are already registered', 'mailster-cf7' ),
			'skip_mail' => false,
			'checkboxfield' => 'your-checkbox',
			'lists' => array(),
			'fields' => array(
				'email' => 'your-email',
				'firstname' => 'your-name',
			),
		) );

		if ( empty( $s['fields'] ) ) {
			$s['fields'] = array(
				'email' => 'your-email',
			);
		}

		wp_enqueue_script( 'cf7-mailster', $this->plugin_url . '/assets/js/script.js', array( 'jquery' ) , MAILSTER_CF7_VERSION, true );
		wp_enqueue_style( 'cf7-mailster', $this->plugin_url . '/assets/css/style.css', array() , MAILSTER_CF7_VERSION );

		$tags = $post->form_scan_shortcode();
		$simpletags = wp_list_pluck( $tags, 'name' );
		$checkboxes = array();

		foreach ( $tags as $tag ) {
			if ( $tag['basetype'] == 'checkbox' ) {
				$checkboxes[] = $tag['name'];
			}
		}

?>


<table class="form-table" id="mailster-cf7-settings">
	<tr>
	<th scope="row">&nbsp;</th>
	<td>
		<input type="hidden" name="mailster[enabled]" value=""><input type="checkbox" name="mailster[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> enabled this form for Mailster
	</td>
	</tr>
	<tr>
	<th scope="row">
		<label><?php _e( 'Map Fields', 'mailster-cf7' ) ?></label>
	</th>
	<td>
		<p class="description"><?php _e( 'define which field represents which value from your Mailster settings', 'mailster-cf7' ) ?></p>
		<?php
		$fields = array(
			'email' => mailster_text( 'email' ),
			'firstname' => mailster_text( 'firstname' ),
			'lastname' => mailster_text( 'lastname' ),
		);

		if ( $customfields = mailster()->get_custom_fields() ) {
			foreach ( $customfields as $field => $data ) {
				$fields[ $field ] = $data['name'];
			}
		}

		echo '<ul id="mailster-map">';
		foreach ( $s['fields'] as $field => $tag ) {
			echo '<li> <label>' . $this->get_tags_dropdown( $simpletags, $tag, 'mailster[tags][]' ) . '</label> &#10152; <select name="mailster[fields][]">';
			echo '<option value="-1">' . __( 'not mapped', 'mailster-cf7' ) . '</option>';
			echo '<option value="-1">--------</option>';
			foreach ( $fields as $id => $name ) {
				echo '<option value="' . $id . '" ' . selected( $id, $field, false ) . '>' . $name . '</option>';
			}
			echo '</select> <a class="cf7-mailster-remove-field" href="#">&times;</a></li>';
		}
		echo '</ul>';

?>
		<a class="cf7-mailster-add-field button button-small" href="#">add field</a>
		</td>
	</tr>
	<?php if ( ! empty( $checkboxes ) ) : ?>
	<tr>
	<th scope="row">
		<label>Conditional Check</label>
	</th>
	<td>
		<label><input type="hidden" name="mailster[checkbox]" value=""><input type="checkbox" name="mailster[checkbox]" value="1" <?php checked( $s['checkbox'] ); ?>> user must check field <?php echo $this->get_tags_dropdown( $checkboxes, $s['checkboxfield'], 'mailster[checkboxfield]' ) ?> to get subscribed</label>
	</td>
	</tr>
	<?php endif; ?>
	<tr>
	<th scope="row">
		<label>Double-opt-In</label>
	</th>
	<td>
		<label>
		<input type="hidden" name="mailster[doubleoptin]" value=""><input type="checkbox" name="mailster[doubleoptin]" value="1" <?php checked( $s['doubleoptin'] ); ?>> user have to confirm their subscription</label>
	</td>
	</tr>
	<tr>
	<th scope="row">
		<label>Overwrite</label>
	</th>
	<td>
		<label><input type="radio" name="mailster[overwrite]" value="0" <?php checked( ! $s['overwrite'] ); ?>> Do not overwrite with error message</label><br>
		<label><input type="radio" name="mailster[overwrite]" value="2" <?php checked( $s['overwrite'], 2 ); ?>> Do not overwrite without error message</label><br>
		<label><input type="radio" name="mailster[overwrite]" value="1" <?php checked( $s['overwrite'] ); ?>> Always overwrite</label>
	</td>
	</tr>
	<tr>
	<th scope="row">
		<label>Error Message</label>
	</th>
	<td>
		<label><input type="text" name="mailster[error_message]" value="<?php echo esc_attr( $s['error_message'] ); ?>" class="regular-text"></label>
	</td>
	</tr>
	<tr>
	<th scope="row">
		<label>Lists</label>
	</th>
	<td>
	<ul id="mailster-lists">
	<?php
		$lists = mailster( 'lists' )->get();
	foreach ( $lists as $list ) { ?>
		<li><label><input type="checkbox" name="mailster[lists][]" value="<?php echo $list->ID ?>" <?php checked( in_array( $list->ID, $s['lists'] ) ); ?>> <?php echo esc_html( $list->name ) ?></label></li>
	<?php } ?>
	</ul>
	</td>
	</tr>
	<tr>
	<th scope="row">
		<label>Skip Mail</label>
	</th>
	<td>
		<label><input type="hidden" name="mailster[skip_mail]" value=""><input type="checkbox" name="mailster[skip_mail]" value="1" <?php checked( $s['skip_mail'] ); ?>> Skip the Mail from this Contact Form 7</label>
	</td>
	</tr>

</table>
	<?php

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

		$tagsdropdown = '<select name="' . esc_attr( $name ) . '">';
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

new MailsterCF7();
