<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Abilities\Cf7\Forms;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7FormSchema;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7FormWriteRequest;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\RestError;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write ability: `og-cf7/create-form`.
 *
 * Wraps `POST contact-form-7/v1/contact-forms` via `rest_do_request()`, creating a
 * new contact form. Omitted property groups fall back to CF7's default template
 * (the standard name/email/subject/message form). The new form's embeddable
 * `shortcode` and `hash` are read from the live object after the save.
 *
 * The dispatch sets `context=save`: the CF7 route only persists the form when the
 * request carries that context, so without it the form would be built in memory
 * and never written.
 *
 * Mail transparency: a form's mail recipient and additional headers decide where
 * every submission is emailed, and CF7 does not validate them. The result echoes
 * the resulting `mail_recipient` and `mail_additional_headers` so a human can
 * confirm where submissions will go.
 *
 * Only available when Contact Form 7 is active (it is a {@see ConditionalAbility}).
 *
 * @since 0.3.0
 */
final class CreateForm implements ConditionalAbility {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'og-cf7/create-form';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isAvailable(): bool {
		return Cf7Plugin::isActive();
	}

	/**
	 * {@inheritDoc}
	 */
	public function args(): array {
		return array(
			'label'               => __( 'Create Form', 'abilities-catalog-cf7' ),
			'description'         => __( 'Creates a new Contact Form 7 contact form and returns its ID, shortcode, and edit_link. Omitted groups fall back to CF7\'s default template (name/email/subject/message). The form body and additional_settings are passed as strings (see their parameter notes). Important: the mail.recipient and mail.additional_headers you set decide where every submission is emailed and CF7 does not validate them, so a Bcc: header would copy submissions elsewhere; the result echoes the resulting recipient and headers for review. After creating, surface edit_link and place the form with its shortcode (e.g. content/create-page).', 'abilities-catalog-cf7' ),
			'category'            => 'og-cf7',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'title' ),
				'properties'           => array_merge(
					array(
						'title' => array(
							'type'        => 'string',
							'description' => __( 'The form title (how it is listed in wp-admin). Required.', 'abilities-catalog-cf7' ),
						),
					),
					Cf7FormSchema::writableProperties()
				),
				'additionalProperties' => false,
			),
			'output_schema'       => Cf7FormWriteRequest::outputSchema(),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'hasPermission' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
				'show_in_rest' => true,
			),
		);
	}

	/**
	 * Permission check: CF7's edit capability for contact forms.
	 *
	 * Encodes the catalog capability for `og-cf7/create-form`: the CF7 meta-cap
	 * `wpcf7_edit_contact_forms` (mapped to `publish_pages` by default, honoring a
	 * redefined `WPCF7_ADMIN_READ_WRITE_CAPABILITY`). The wrapped route re-checks
	 * the same cap underneath.
	 *
	 * @param mixed $input The validated input data.
	 * @return bool True if the current user may create contact forms.
	 */
	public function hasPermission( $input ): bool {
		return Cf7Plugin::isActive() && current_user_can( 'wpcf7_edit_contact_forms' );
	}

	/**
	 * Executes the ability by dispatching the internal CF7 REST create request.
	 *
	 * @param mixed $input The validated input data.
	 * @return array<string,mixed>|\WP_Error The created form, or the REST error.
	 */
	public function execute( $input ) {
		if ( ! Cf7Plugin::isActive() ) {
			return Cf7Plugin::unavailable();
		}

		$input = is_array( $input ) ? $input : array();

		$request = new WP_REST_Request( 'POST', '/contact-form-7/v1/contact-forms' );

		// CF7 persists the form only when the request carries context=save.
		$request->set_param( 'context', 'save' );

		// Create skips empty strings so an omitted group falls back to CF7's template.
		Cf7FormWriteRequest::fill( $request, $input, false );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return RestError::from( $response );
		}

		$data = rest_get_server()->response_to_data( $response, false );

		return Cf7FormWriteRequest::shapeResult( is_array( $data ) ? $data : array() );
	}
}
