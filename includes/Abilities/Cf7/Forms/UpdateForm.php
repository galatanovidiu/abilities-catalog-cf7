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
 * Write ability: `cf7/update-form`.
 *
 * Wraps `PUT contact-form-7/v1/contact-forms/<id>` via `rest_do_request()` (with
 * `context=save`, which CF7 requires to persist). CF7's `set_properties()` keeps
 * only known property keys and `wpcf7_sanitize_mail()` resets any omitted field
 * within a submitted mail block to its default, so a partial mail object blanks
 * the rest — the description steers the agent to send complete groups.
 *
 * Mail transparency: editing `mail.recipient` or `mail.additional_headers`
 * reroutes or copies where every future submission is emailed, and CF7 does not
 * validate them. Before dispatching, this reads the form's current primary-mail
 * recipient and headers and returns them as `previous_*` alongside the resulting
 * values, so a human can see exactly what the edit changed.
 *
 * Only available when Contact Form 7 is active (it is a {@see ConditionalAbility}).
 *
 * @since 0.3.0
 */
final class UpdateForm implements ConditionalAbility {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'cf7/update-form';
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
		$output_schema = Cf7FormWriteRequest::outputSchema();

		$output_schema['properties']['previous_mail_recipient']          = array(
			'type'        => 'string',
			'description' => __( 'The primary-mail recipient BEFORE this update, so a human can see whether the change rerouted submissions.', 'abilities-catalog-cf7' ),
		);
		$output_schema['properties']['previous_mail_additional_headers'] = array(
			'type'        => 'string',
			'description' => __( 'The primary-mail extra headers BEFORE this update, so a human can spot a newly added Bcc:/Cc: line.', 'abilities-catalog-cf7' ),
		);

		return array(
			'label'               => __( 'Update Form', 'abilities-catalog-cf7' ),
			'description'         => __( 'Updates an existing Contact Form 7 contact form by ID; send only the property groups you want to change. IMPORTANT: send each group COMPLETE — within a submitted mail/mail_2 block CF7 resets any field you omit to its default, so to change one mail field read the current mail object from cf7/get-form, modify it, and send the whole object back. The form body and additional_settings are strings (see their notes). Editing mail.recipient or mail.additional_headers reroutes or copies where every future submission is emailed and CF7 does not validate them; the result returns both the previous and the resulting recipient/headers for review. Discover IDs with cf7/list-forms.', 'abilities-catalog-cf7' ),
			'category'            => 'cf7',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array_merge(
					array(
						'id'    => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'The contact-form ID to update. Discover IDs with cf7/list-forms.', 'abilities-catalog-cf7' ),
						),
						'title' => array(
							'type'        => 'string',
							'description' => __( 'A new form title. Omit to keep the current title.', 'abilities-catalog-cf7' ),
						),
					),
					Cf7FormSchema::writableProperties()
				),
				'additionalProperties' => false,
			),
			'output_schema'       => $output_schema,
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'hasPermission' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
				'show_in_rest' => true,
				'screen'       => 'admin.php?page=wpcf7&post={id}&action=edit',
			),
		);
	}

	/**
	 * Permission check: CF7's edit capability for contact forms.
	 *
	 * Encodes the catalog capability for `cf7/update-form`: the CF7 meta-cap
	 * `wpcf7_edit_contact_form` (mapped to `publish_pages` by default). Coarse and
	 * object-independent; the wrapped route surfaces the specific 404
	 * (`wpcf7_not_found`) for a missing form so it is not masked as a permission
	 * failure.
	 *
	 * @param mixed $input The validated input data.
	 * @return bool True if the current user may edit contact forms.
	 */
	public function hasPermission( $input ): bool {
		return Cf7Plugin::isActive() && current_user_can( 'wpcf7_edit_contact_form' );
	}

	/**
	 * Executes the ability by dispatching the internal CF7 REST update request.
	 *
	 * Reads the current primary-mail recipient/headers first (so the missing-form
	 * 404 also surfaces here), then dispatches the update and returns the shaped
	 * result with the captured `previous_*` values.
	 *
	 * @param mixed $input The validated input data.
	 * @return array<string,mixed>|\WP_Error The updated form, or the REST error.
	 */
	public function execute( $input ) {
		if ( ! Cf7Plugin::isActive() ) {
			return Cf7Plugin::unavailable();
		}

		$input = is_array( $input ) ? $input : array();
		$id    = absint( $input['id'] ?? 0 );

		// Snapshot the mail routing before the edit. A missing form 404s here.
		$before = rest_do_request( new WP_REST_Request( 'GET', '/contact-form-7/v1/contact-forms/' . $id ) );
		if ( $before->is_error() ) {
			return RestError::from( $before );
		}

		$before_data  = rest_get_server()->response_to_data( $before, false );
		$before_props = is_array( $before_data ) && is_array( $before_data['properties'] ?? null )
			? $before_data['properties']
			: array();
		$before_mail  = is_array( $before_props['mail'] ?? null ) ? $before_props['mail'] : array();

		$request = new WP_REST_Request( 'PUT', '/contact-form-7/v1/contact-forms/' . $id );

		// CF7 persists the form only when the request carries context=save.
		$request->set_param( 'context', 'save' );

		// Update forwards present-but-empty values so an explicit "" can blank a field.
		Cf7FormWriteRequest::fill( $request, $input, true );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return RestError::from( $response );
		}

		$data   = rest_get_server()->response_to_data( $response, false );
		$result = Cf7FormWriteRequest::shapeResult( is_array( $data ) ? $data : array() );

		$result['previous_mail_recipient']          = (string) ( $before_mail['recipient'] ?? '' );
		$result['previous_mail_additional_headers'] = (string) ( $before_mail['additional_headers'] ?? '' );

		return $result;
	}
}
