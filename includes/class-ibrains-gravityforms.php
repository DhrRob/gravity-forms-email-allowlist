<?php
/**
 * Gravity Forms Email Allowlist Handler.
 *
 * @class   IBGFAddOn
 * @package IBGFAddOn
 */

defined( 'ABSPATH' ) || exit;

GFForms::include_addon_framework();

class IBGFAddOn extends GFAddOn {

	protected $_version = IBGF_VERSION;
	protected $_min_gravityforms_version = '2.8';
	protected $_slug = 'ibrains-gf';
	protected $_path = 'ibrains-gf/ibrains-gf.php';
	protected $_full_path = __FILE__;
	protected $_title = 'iBrains Gravity Forms Add-On';
	protected $_short_title = 'iBrains GF Add-On';

    private static $_instance = null;

	/**
	 * Get an instance of this class.
	 *
	 * @return IBGFAddOn
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new IBGFAddOn();
		}

		return self::$_instance;
	}

    /**
	 * Add tasks or filters here that you want to perform only in admin.
	 */
	public function init_admin() {
		parent::init_admin();
		add_action( 'gform_editor_js', array( $this, 'ibgf_gform_editor_js' ) );
		add_action( 'gform_field_standard_settings', array( $this, 'ibgf_field_settings' ), 10, 2 );
		add_filter( 'gform_tooltips', array( $this, 'ibgf_field_tooltips' ) );
	}

	/**
	 * Add tasks or filters here that you want to perform only in the front end.
	 */
	public function init_frontend() {
		parent::init_frontend();
		add_filter( 'gform_validation', array( $this, 'ibgf_validation' ) );
	}

    /**
	 * Add the additional Email Allowlist
	 *
	 * @return array Additional plugin setting fields in the Gravity Forms Settings API.
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'       => __( 'Email Allowlist Global Settings', 'ib-gravity-forms' ),
				'description' => __( 'Use Email Allowlist to secure your forms. If a allowlisted email is used in any email field, the form will error on submission. You can also globally define a list of allowlisted emails and/or domains and a custom validation message if a allowlisted email is submitted. These settings can be overridden on individual email fields in the advanced settings.', 'ib-gravity-forms' ),
				'fields'      => array(
					array(
						'label'   => __( 'Global Allowlisted Emails', 'ib-gravity-forms' ),
						'type'    => 'textarea',
						'name'    => 'default_emailallowlist',
						'tooltip' => __( 'Please enter a comma separated list of allowlisted domains (ex. hotmail.com), email addresses (ex. user@aol.com), and/or include the wildcard notation to block top-level domains (ex. *.com). This setting can be overridden on individual email fields in the advanced settings.', 'ib-gravity-forms' ),
						'class'   => 'medium',
					),
					array(
						'label'   => __( 'Global Validation Message', 'ib-gravity-forms' ),
						'type'    => 'text',
						'name'    => 'default_emailallowlist_error_msg',
                        'default' => __( 'Sorry, the email address entered is not eligible for this form', 'ib-gravity-forms' ),
						'tooltip' => __( 'Please enter a default error message if a allowlisted email is submitted.', 'ib-gravity-forms' ),
						'class'   => 'medium',
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
	public function ibgf_field_settings( $position, $form_id = null ) {

		// Create settings on position 1375 (right after Field Enable Email Confirmation).
		if ( 1375 === $position ) {
			?>

        <li class="ibgf field_setting">
            <input type="checkbox" id="field_ibgf_allowlist" onclick="SetFieldProperty('ibgf_email_allowlist', this.checked);" onkeypress="SetFieldProperty('ibgf_email_allowlist', this.checked);" />
            <label for="field_ibgf_allowlist" class="inline">
                <?php esc_html_e( 'Enable Email Allowlist', 'ib-gravity-forms' ); ?>
				<?php gform_tooltip( 'form_field_ibgf_enable_allowlist' ); ?>
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
	public function ibgf_field_tooltips( $tooltips ) {
        $admin_url = get_admin_url() . 'admin.php?page=gf_settings&subview=ibrains-gf';
		$tooltips['form_field_ibgf_enable_allowlist']      = __( "<strong>Enable Email Allowlist</strong>Enable the global allowlist for this field as defined in the <a href=\"$admin_url\" target=\"_blank\">form settings</a>.", 'ib-gravity-forms' );

        return $tooltips;
	}

    /**
	 * Inject Javascript into the form editor page for the email allowlist fields.
	 */
	public function ibgf_gform_editor_js() {
		?>
	<script type='text/javascript'>
		jQuery(document).ready(function($) {
			// Alter the setting offered for the email input type.
			fieldSettings["email"] = fieldSettings["email"] + ", .ibgf.field_setting"; // this will show all fields that Paragraph Text field shows plus my custom setting

			// Binding to the load field settings event to initialize the checkbox.
            $(document).bind('gform_load_field_settings', function(event, field, form){
                jQuery( '#field_ibgf_allowlist' ).prop( 'checked', Boolean( rgar( field, 'ibgf_email_allowlist' ) ) );
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
	public function ibgf_validation( $validation_result ) {

        // Collect global settings.
		$default_allowlist = get_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
		if ( is_array( $default_allowlist ) && ! empty( $default_allowlist['default_emailallowlist'] ) ) {
			$default_allowlist = $default_allowlist['default_emailallowlist'];
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

			// Collect banned domains from backend and clean up.
			$allowlist = $default_allowlist;
			if ( ! empty( $field['email_allowlist'] ) ) { // collect per form settings.
				$allowlist = $field['email_allowlist'];
			}

			// Get the domain from user entered email.
			$email  = $this->ibgf_clean( rgpost( "input_{$field['id']}" ) );
			$domain = $this->ibgf_clean( rgar( explode( '@', $email ), 1 ) );
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
			if ( apply_filters( 'ibgf_validation_short_circuit', false, $field, $email, $domain, $tld, $allowlist ) ) {
				continue;
			}

			// Create array of banned domains.
			if ( ! is_array( $allowlist ) ) {
				$allowlist = explode( ',', $allowlist );
			}
			$allowlist = str_replace( '*', '', $allowlist );
			$allowlist = array_map( array( $this, 'ibgf_clean' ), $allowlist );
			$allowlist = array_filter( $allowlist );

			// No allowlisted email, skip.
			if ( empty( $allowlist ) ) {
				continue;
			}

			// if the email, domain or top-level domain isn't allowlisted, skip.
			if ( ! in_array( $email, $allowlist, true ) && ! in_array( $domain, $allowlist, true ) && ! in_array( $tld, $allowlist, true ) ) {
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
			$validation_result['is_valid'] = apply_filters( 'ibgf_is_valid', false, $field, $email, $domain, $tld, $allowlist );
			$field['failed_validation']    = true;

			// Set the validation message or use the default.
			if ( ! empty( $field['email_allowlist_validation'] ) ) {
				$validation_message = $field['email_allowlist_validation'];
			} elseif ( get_option( 'gravityformsaddon_' . $this->_slug . '_settings' ) ) {
				$validation_message = get_option( 'gravityformsaddon_' . $this->_slug . '_settings' );
				$validation_message = $validation_message['default_emailallowlist_error_msg'];
			} else {
				$validation_message = __( 'Sorry, the email address entered is not eligible for this form.', 'ib-gravity-forms' );
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
			$field['validation_message'] = apply_filters( 'ibgf_validation_message', $validation_message, $field, $email, $domain, $tld, $allowlist );
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
	protected function ibgf_clean( $string ) {
		return strtolower( trim( $string ) );
	}

}
