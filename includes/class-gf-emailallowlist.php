<?php
/**
 * Gravity Forms Email Allowlist Handler.
 *
 * @class   GFEmailAllowlist
 * @package GFEmailAllowlist
 */

defined( 'ABSPATH' ) || exit;

GFForms::include_addon_framework();

class GFEmailAllowlist extends GFAddOn {

	protected $_version                  = '1.0.0';
	protected $_min_gravityforms_version = '2.8';
	protected $_slug                     = 'gf_email_allowlist';
	protected $_path                     = 'gravity-forms-email-allowlist/gf-emailallowlist.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms Email Allowlist';
	protected $_short_title              = 'Email Allowlist';

	private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return GFEmailAllowlist
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new GFEmailAllowlist();
		}

		return self::$_instance;
	}

	/**
	 * Add tasks or filters here that you want to perform only in admin.
	 */
	public function init_admin() {
		parent::init_admin();
		add_action( 'gform_editor_js', array( $this, 'rmgf_gform_editor_js' ) );
		add_action( 'gform_field_standard_settings', array( $this, 'rmgf_field_settings' ), 10, 2 );
		add_filter( 'gform_tooltips', array( $this, 'rmgf_field_tooltips' ) );
	}

	/**
	 * Add tasks or filters here that you want to perform only in the front end.
	 */
	public function init_frontend() {
		parent::init_frontend();
		add_filter( 'gform_validation', array( $this, 'rmgf_validation' ) );
	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 2.5
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return 'gform-icon--mail';
	}

	/**
	 * Add the additional Email Allowlist
	 *
	 * @return array Additional plugin setting fields in the Gravity Forms Settings API.
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => esc_html__( 'Email Allowlist Global Settings', 'gf-email-allowlist' ),
				'description' => esc_html__( 'Use Email Allowlist to secure your forms. If a domain extension is used that isn\'t on your allowlist, the form will throw an error on submission. You can enable and disable this allowlist for each email field on your forms.', 'gf-email-allowlist' ),
				'fields'      => array(
					array(
						'label'   => __( 'Allowlisted Emails', 'gf-email-allowlist' ),
						'type'    => 'textarea',
						'name'    => 'emailallowlist',
						'tooltip' => __( 'One domain extension (ex. *.com) per line.', 'gf-email-allowlist' ),
					),
					array(
						'label'   => esc_html__( 'Validation Message', 'gf-email-allowlist' ),
						'type'    => 'text',
						'name'    => 'emailallowlist_error_msg',
						'tooltip' => esc_html__( 'Please enter an error message if a allowlisted email isn\'t submitted. ', 'gf-email-allowlist' ),
					),
				),
			),
		);
	}

	/**
	 * Add email allowlist setting to the email fields advanced settings.
	 *
	 * @param integer $position Specifies the position that the settings will be displayed.
	 * @param integer $form_id  The ID of the form from which the entry value was submitted.
	 */
	public function rmgf_field_settings( $position, $form_id = null ) {

		// Create settings on position 1375 (right after Field Enable Email Confirmation).
		if ( 1375 === $position ) {
			?>

		<li class="rmgf field_setting">
			<input type="checkbox" id="field_rmgf_allowlist" onclick="SetFieldProperty('rmgf_email_allowlist', this.checked);" onkeypress="SetFieldProperty('rmgf_email_allowlist', this.checked);" />
			<label for="field_rmgf_allowlist" class="inline">
				<?php esc_html_e( 'Enable Email Allowlist', 'gf-email-allowlist' ); ?>
				<?php gform_tooltip( 'form_field_rmgf_enable_allowlist' ); ?>
			</label>
		</li>
			<?php
		}
	}

	/**
	 * Add the additional tooltips to the new fields.
	 *
	 * @param array $tooltips tooltip associative array.
	 * @return array modified tooltips
	 */
	public function rmgf_field_tooltips( $tooltips ) {
		$admin_url                                    = get_admin_url() . 'admin.php?page=gf_settings&subview=ibrains-gf';
		$tooltips['form_field_rmgf_enable_allowlist'] = __( "<strong>Enable Email Allowlist</strong>Enable the global allowlist for this field as defined in the <a href=\"$admin_url\" target=\"_blank\">form settings</a>.", 'gf-email-allowlist' );

		return $tooltips;
	}

	/**
	 * Inject Javascript into the form editor page for the email allowlist fields.
	 */
	public function rmgf_gform_editor_js() {
		?>
	<script type='text/javascript'>
		jQuery(document).ready(function($) {
			// Alter the setting offered for the email input type.
			fieldSettings["email"] = fieldSettings["email"] + ", .rmgf.field_setting"; // this will show all fields that Paragraph Text field shows plus my custom setting

			// Binding to the load field settings event to initialize the checkbox.
			$(document).bind('gform_load_field_settings', function(event, field, form){
				jQuery( '#field_rmgf_allowlist' ).prop( 'checked', Boolean( rgar( field, 'rmgf_email_allowlist' ) ) );
			});
		});
	</script>
		<?php
	}

	/**
	 * Add email allowlist to gforms validation function.
	 *
	 * @resources: https://docs.gravityforms.com/using-gform-validation-hook/
	 *
	 * @param  array $validation_result Contains the validation result and the current.
	 * @return array The field validation results.
	 */
	public function rmgf_validation( $validation_result ) {

		// Collect global settings.
		$default_allowlist = get_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
		if ( is_array( $default_allowlist ) && ! empty( $default_allowlist['emailallowlist'] ) ) {
			$default_allowlist = $default_allowlist['emailallowlist'];
		} else {
			$default_allowlist = '';
		}

		// Collect form results.
		$form = $validation_result['form'];

		// Loop through results.
		foreach ( $form['fields'] as &$field ) {

			// If this is not an email field, skip.
			if ( 'email' !== RGFormsModel::get_input_type( $field ) ) {
				continue;
			}

			// If the field is hidden by GF conditional logic, skip.
			if ( RGFormsModel::is_field_hidden( $form, $field, array() ) ) {
				continue;
			}

			// Collect allowed domains from backend and clean up.
			$allowlist = $default_allowlist;
			if ( ! empty( $field['email_allowlist'] ) ) { // collect per form settings.
				$allowlist = $field['email_allowlist'];
			}

			// Get the domain from user entered email.
			$email  = $this->rmgf_clean( rgpost( "input_{$field['id']}" ) );
			$domain = $this->rmgf_clean( rgar( explode( '@', $email ), 1 ) );
			$tld    = strrchr( $domain, '.' );

			/**
			 * Filter to allow third party plugins short circuit allowlist validation.
			 *
			 * @since 2.5.1
			 * @param bool   false      Default value.
			 * @param array  $field     The Field Object.
			 * @param string $email     The email entered in the input.
			 * @param string $domain    The full domain entered in the input.
			 * @param string $tld       The top level domain entered in the input.
			 * @param array  $allowlist List of the blocked emailed/domains.
			 */
			if ( apply_filters( 'rmgf_validation_short_circuit', false, $field, $email, $domain, $tld, $allowlist ) ) {
				continue;
			}

			// Create array of allowed domains.
			if ( ! is_array( $allowlist ) ) {
				$allowlist = explode( PHP_EOL, $allowlist );
			}
			$allowlist = str_replace( '*', '', $allowlist );
			$allowlist = array_map( array( $this, 'rmgf_clean' ), $allowlist );
			$allowlist = array_filter( $allowlist );

			// No allowlisted email, skip.
			if ( empty( $allowlist ) ) {
				continue;
			}

			// if the email, domain or top-level domain isn't allowlisted, skip.
			if ( in_array( $email, $allowlist, true ) || in_array( $domain, $allowlist, true ) || in_array( $tld, $allowlist, true ) ) {
				continue;
			}

			/**
			 * Filter to allow third party plugins to set the email allowlist validation.
			 *
			 * @since 2.5.1
			 * @param bool   false      Default value.
			 * @param array  $field     The Field Object.
			 * @param string $email     The email entered in the input.
			 * @param string $domain    The full domain entered in the input.
			 * @param string $tld       The top level domain entered in the input.
			 * @param array  $allowlist List of the blocked emailed/domains.
			 */
			$validation_result['is_valid'] = apply_filters( 'rmgf_is_valid', false, $field, $email, $domain, $tld, $allowlist );
			$field['failed_validation']    = true;

			// Set a default validation message.
			$default_message    = __( 'Sorry, the email address entered is not eligible for this form.', 'gf-email-allowlist' );
			$settings_option    = get_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
			$validation_message = $default_message;

			// Check if the settings option exists and contains the specific error message.
			if ( isset( $settings_option['emailallowlist_error_msg'] ) && ! empty( $settings_option['emailallowlist_error_msg'] ) ) {
				$validation_message = $settings_option['emailallowlist_error_msg'];
			}

			/**
			 * Filter to allow third party plugins to set the email allowlist validation.
			 *
			 * @since 2.5.1
			 * @param bool   $validation_message The custom validation method.
			 * @param array  $field              The Field Object.
			 * @param string $email              The email entered in the input.
			 * @param string $domain             The full domain entered in the input.
			 * @param string $tld                The top level domain entered in the input.
			 * @param array  $allowlist          List of the blocked emailed/domains.
			 */
			$field['validation_message'] = apply_filters( 'rmgf_validation_message', $validation_message, $field, $email, $domain, $tld, $allowlist );
		}

		$validation_result['form'] = $form;
		return $validation_result;
	}

	/**
	 * Convert a sting to lowercase and remove extra whitespace. Thanks to @ractoon, @rscoates.
	 *
	 * @param string $string A string to sanitize.
	 * @return string Sanitize string
	 */
	protected function rmgf_clean( $string ) {
		return strtolower( trim( $string ) );
	}
}
