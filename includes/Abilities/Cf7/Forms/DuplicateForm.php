<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Abilities\Cf7\Forms;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7FormWriteRequest;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\RestError;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Write ability: `cf7/duplicate-form`.
 *
 * CF7 exposes no REST route for copying a form, so this wraps the object method
 * directly through {@see Cf7Plugin::duplicate()} (`copy()` then `save()`, since the
 * clone CF7 returns is unsaved). It first dispatches a read of the source through
 * the `contact-form-7/v1` route so a missing source returns the route's specific
 * 404 rather than a generic failure.
 *
 * Only available when Contact Form 7 is active (it is a {@see ConditionalAbility}).
 *
 * @since 0.3.0
 */
final class DuplicateForm implements ConditionalAbility {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'cf7/duplicate-form';
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
			'label'               => __( 'Duplicate Form', 'abilities-catalog-cf7' ),
			'description'         => __( 'Duplicates a Contact Form 7 contact form. Creates an independent copy titled "{original title}_copy" carrying the source form\'s fields, mail settings, and messages, and returns the copy\'s new ID, shortcode, and edit_link. Edit the copy afterward with cf7/update-form. Discover source IDs with cf7/list-forms.', 'abilities-catalog-cf7' ),
			'category'            => 'cf7',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The contact-form ID to copy. Discover IDs with cf7/list-forms.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'id', 'shortcode', 'edit_link' ),
				'properties'           => array(
					'id'        => array(
						'type'        => 'integer',
						'description' => __( 'The new (copied) form\'s ID.', 'abilities-catalog-cf7' ),
					),
					'slug'      => array(
						'type'        => 'string',
						'description' => __( 'The copy\'s internal slug (post name).', 'abilities-catalog-cf7' ),
					),
					'title'     => array(
						'type'        => 'string',
						'description' => __( 'The copy\'s title, "{original title}_copy".', 'abilities-catalog-cf7' ),
					),
					'shortcode' => array(
						'type'        => 'string',
						'description' => __( 'The copy\'s ready-to-embed shortcode.', 'abilities-catalog-cf7' ),
					),
					'hash'      => array(
						'type'        => 'string',
						'description' => __( 'The copy\'s 7-character hash.', 'abilities-catalog-cf7' ),
					),
					'edit_link' => array(
						'type'        => 'string',
						'description' => __( 'The wp-admin URL to edit the copy. Surface this so a human can review it.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
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
	 * A copy is a new form, so this requires `wpcf7_edit_contact_forms` (mapped to
	 * `publish_pages` by default) — the same cap `cf7/create-form` uses.
	 *
	 * @param mixed $input The validated input data.
	 * @return bool True if the current user may create contact forms.
	 */
	public function hasPermission( $input ): bool {
		return Cf7Plugin::isActive() && current_user_can( 'wpcf7_edit_contact_forms' );
	}

	/**
	 * Executes the ability by copying and saving the source form.
	 *
	 * @param mixed $input The validated input data.
	 * @return array<string,mixed>|\WP_Error The new form, or a `WP_Error`.
	 */
	public function execute( $input ) {
		if ( ! Cf7Plugin::isActive() ) {
			return Cf7Plugin::unavailable();
		}

		$input = is_array( $input ) ? $input : array();
		$id    = absint( $input['id'] ?? 0 );

		// Confirm the source through the REST route so a missing form returns the
		// route's specific 404 (wpcf7_not_found) instead of a generic failure.
		$source = rest_do_request( new WP_REST_Request( 'GET', '/contact-form-7/v1/contact-forms/' . $id ) );
		if ( $source->is_error() ) {
			return RestError::from( $source );
		}

		$new_id = Cf7Plugin::duplicate( $id );
		if ( $new_id < 1 ) {
			return new WP_Error(
				'cf7_cannot_duplicate',
				__( 'The contact form could not be duplicated.', 'abilities-catalog-cf7' ),
				array( 'status' => 500 )
			);
		}

		$copy = get_post( $new_id );

		return array(
			'id'        => $new_id,
			'slug'      => $copy instanceof \WP_Post ? (string) $copy->post_name : '',
			'title'     => $copy instanceof \WP_Post ? (string) $copy->post_title : '',
			'shortcode' => Cf7Plugin::shortcode( $new_id ),
			'hash'      => Cf7Plugin::hash( $new_id ),
			'edit_link' => Cf7FormWriteRequest::editLink( $new_id ),
		);
	}
}
