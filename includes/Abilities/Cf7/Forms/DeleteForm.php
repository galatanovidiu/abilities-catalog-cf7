<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Abilities\Cf7\Forms;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\RestError;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Destructive write ability: `og-cf7/delete-form`.
 *
 * Wraps `DELETE contact-form-7/v1/contact-forms/<id>` via `rest_do_request()`.
 * CF7's delete is `wp_delete_post( $id, true )` — a force delete that bypasses the
 * Trash, so it is permanent and irreversible. Before deleting, this reads the
 * form's title so the result can confirm what was removed (and so a missing form
 * returns the route's 404 here).
 *
 * Only available when Contact Form 7 is active (it is a {@see ConditionalAbility}).
 *
 * @since 0.3.0
 */
final class DeleteForm implements ConditionalAbility {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'og-cf7/delete-form';
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
			'label'               => __( 'Delete Form', 'abilities-catalog-cf7' ),
			'description'         => __( 'Permanently deletes a Contact Form 7 contact form by ID. This cannot be undone: CF7 force-deletes the form, bypassing the Trash, so there is no restore. Any page or post still embedding the form\'s shortcode will then show a "contact form not found" message. Returns the deleted form\'s title for confirmation. Discover IDs with og-cf7/list-forms.', 'abilities-catalog-cf7' ),
			'category'            => 'og-cf7',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The contact-form ID to permanently delete. Discover IDs with og-cf7/list-forms.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'deleted', 'id' ),
				'properties'           => array(
					'deleted' => array(
						'type'        => 'boolean',
						'description' => __( 'Whether the form was permanently deleted.', 'abilities-catalog-cf7' ),
					),
					'id'      => array(
						'type'        => 'integer',
						'description' => __( 'The deleted form\'s ID.', 'abilities-catalog-cf7' ),
					),
					'title'   => array(
						'type'        => 'string',
						'description' => __( 'The title of the deleted form, so a human can confirm what was removed. No edit_link is returned because the form no longer exists.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'hasPermission' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
				'show_in_rest' => true,
				'screen'       => 'admin.php?page=wpcf7',
			),
		);
	}

	/**
	 * Permission check: CF7's delete capability for contact forms.
	 *
	 * Encodes the catalog capability for `og-cf7/delete-form`: the CF7 meta-cap
	 * `wpcf7_delete_contact_form` (mapped to `publish_pages` by default). Coarse
	 * and object-independent; the wrapped route surfaces the specific 404
	 * (`wpcf7_not_found`) for a missing form.
	 *
	 * @param mixed $input The validated input data.
	 * @return bool True if the current user may delete contact forms.
	 */
	public function hasPermission( $input ): bool {
		return Cf7Plugin::isActive() && current_user_can( 'wpcf7_delete_contact_form' );
	}

	/**
	 * Executes the ability by dispatching the internal CF7 REST delete request.
	 *
	 * @param mixed $input The validated input data.
	 * @return array<string,mixed>|\WP_Error The deleted flag, id, and title, or the REST error.
	 */
	public function execute( $input ) {
		if ( ! Cf7Plugin::isActive() ) {
			return Cf7Plugin::unavailable();
		}

		$input = is_array( $input ) ? $input : array();
		$id    = absint( $input['id'] ?? 0 );

		// Capture the title before the row is gone; a missing form 404s here.
		$before = rest_do_request( new WP_REST_Request( 'GET', '/contact-form-7/v1/contact-forms/' . $id ) );
		if ( $before->is_error() ) {
			return RestError::from( $before );
		}

		$before_data = rest_get_server()->response_to_data( $before, false );
		$title       = is_array( $before_data ) ? (string) ( $before_data['title'] ?? '' ) : '';

		$response = rest_do_request( new WP_REST_Request( 'DELETE', '/contact-form-7/v1/contact-forms/' . $id ) );
		if ( $response->is_error() ) {
			return RestError::from( $response );
		}

		$data = rest_get_server()->response_to_data( $response, false );

		return array(
			'deleted' => (bool) ( $data['deleted'] ?? false ),
			'id'      => $id,
			'title'   => $title,
		);
	}
}
