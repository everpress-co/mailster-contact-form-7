<?php

	$s = wp_parse_args(
		$post->prop( 'mailster' ),
		array(
			'enabled'       => false,
			'checkbox'      => false,
			'doubleoptin'   => true,
			'overwrite'     => false,
			'error_message' => __( 'You are already registered', 'mailster-cf7' ),
			'skip_mail'     => false,
			'checkboxfield' => 'your-checkbox',
			'lists'         => array(),
			'fields'        => array(
				'email'     => 'your-email',
				'firstname' => 'your-name',
			),
		)
	);

	if ( empty( $s['fields'] ) ) {
		$s['fields'] = array(
			'email' => 'your-email',
		);
	}

	wp_enqueue_script( 'cf7-mailster', $this->plugin_url . '/assets/js/script.js', array( 'jquery' ), MAILSTER_CF7_VERSION, true );
	wp_enqueue_style( 'cf7-mailster', $this->plugin_url . '/assets/css/style.css', array(), MAILSTER_CF7_VERSION );

	$tags       = $post->scan_form_tags();
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
			<input type="hidden" name="mailster[enabled]" value=""><input type="checkbox" name="mailster[enabled]" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Enabled this form for Mailster', 'mailster-cf7' ); ?>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Map Fields', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<p class="description"><?php esc_html_e( 'Define which field represents which value from your Mailster settings', 'mailster-cf7' ); ?></p>
<?php
	$fields = array(
		'email'     => mailster_text( 'email' ),
		'firstname' => mailster_text( 'firstname' ),
		'lastname'  => mailster_text( 'lastname' ),
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
		<a class="cf7-mailster-add-field button button-small" href="#"><?php esc_html_e( 'Add Field', 'mailster-cf7' ); ?></a>
		</td>
	</tr>
	<?php if ( ! empty( $checkboxes ) ) : ?>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Conditional Check', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<label><input type="hidden" name="mailster[checkbox]" value=""><input type="checkbox" name="mailster[checkbox]" value="1" <?php checked( $s['checkbox'] ); ?>> <?php printf( esc_html__( 'User must check field %s to get subscribed', 'mailster-cf7' ), $this->get_tags_dropdown( $checkboxes, $s['checkboxfield'], 'mailster[checkboxfield]' ) ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Set GDPR Timestamp', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<label>
			<input type="hidden" name="mailster[gdpr_timestamp]" value=""><input type="checkbox" name="mailster[gdpr_timestamp]" value="1" <?php checked( $s['gdpr_timestamp'] ); ?>> <?php esc_html_e( 'Store GDPR timestamp on signup', 'mailster-cf7' ); ?></label>
		</td>
	</tr>
	<?php endif; ?>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Double-opt-In', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<label>
			<input type="hidden" name="mailster[doubleoptin]" value=""><input type="checkbox" name="mailster[doubleoptin]" value="1" <?php checked( $s['doubleoptin'] ); ?>> <?php esc_html_e( 'User have to confirm their subscription', 'mailster-cf7' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Overwrite', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<label><input type="radio" name="mailster[overwrite]" value="0" <?php checked( ! $s['overwrite'] ); ?>> <?php esc_html_e( 'Do not overwrite with error message', 'mailster-cf7' ); ?></label><br>
			<label><input type="radio" name="mailster[overwrite]" value="2" <?php checked( $s['overwrite'], 2 ); ?>> <?php esc_html_e( 'Do not overwrite without error message', 'mailster-cf7' ); ?></label><br>
			<label><input type="radio" name="mailster[overwrite]" value="1" <?php checked( $s['overwrite'], 1 ); ?>> <?php esc_html_e( 'Always overwrite', 'mailster-cf7' ); ?></label><br>
			<label><input type="radio" name="mailster[overwrite]" value="3" <?php checked( $s['overwrite'], 3 ); ?>> <?php esc_html_e( 'Always overwrite and keep existing data', 'mailster-cf7' ); ?></label>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Error Message', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<label><input type="text" name="mailster[error_message]" value="<?php echo esc_attr( $s['error_message'] ); ?>" class="regular-text"></label>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Lists', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<ul id="mailster-lists">
			<?php
				$lists = mailster( 'lists' )->get();
			foreach ( $lists as $list ) {
				?>
				<li><label><input type="checkbox" name="mailster[lists][]" value="<?php echo $list->ID; ?>" <?php checked( in_array( $list->ID, $s['lists'] ) ); ?>> <?php echo esc_html( $list->name ); ?></label></li>
			<?php } ?>
			</ul>
		</td>
	</tr>
	<tr>
		<th scope="row">
			<label><?php esc_html_e( 'Skip Mail', 'mailster-cf7' ); ?></label>
		</th>
		<td>
			<label><input type="hidden" name="mailster[skip_mail]" value=""><input type="checkbox" name="mailster[skip_mail]" value="1" <?php checked( $s['skip_mail'] ); ?>><?php esc_html_e( 'Skip the Mail from this Contact Form 7', 'mailster-cf7' ); ?></label>
		</td>
	</tr>

</table>
