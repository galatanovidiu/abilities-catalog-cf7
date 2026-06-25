<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Abilities\Cf7\Forms;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7FormSchema;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\RestError;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read ability: `og-cf7/get-form`.
 *
 * Wraps `GET contact-form-7/v1/contact-forms/<id>` via `rest_do_request()` and
 * returns the form's full configuration (`properties`) plus the embeddable
 * `shortcode` and `hash`, which the REST body does not carry — they are read from
 * the live object via {@see Cf7Plugin}. This closes the original gap: an agent can
 * read a form's shortcode and place it on a page.
 *
 * Only available when Contact Form 7 is active (it is a {@see ConditionalAbility}).
 *
 * @since 0.3.0
 */
final class GetForm implements ConditionalAbility {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'og-cf7/get-form';
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
			'label'               => __( 'Get Form', 'abilities-catalog-cf7' ),
			'description'         => __( 'Returns one Contact Form 7 contact form by ID: its title, locale, full configuration (form fields, mail and mail_2 settings, response messages, additional settings), and the ready-to-embed shortcode and hash. Use the shortcode to place the form on a page or post. Discover IDs with og-cf7/list-forms.', 'abilities-catalog-cf7' ),
			'category'            => 'og-cf7',
			'input_schema'        => array(
				'type'                 => 'object',
				'required'             => array( 'id' ),
				'properties'           => array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'The contact-form ID. Discover IDs with og-cf7/list-forms.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'id', 'shortcode' ),
				'properties'           => array(
					'id'         => array(
						'type'        => 'integer',
						'description' => __( 'The contact-form ID.', 'abilities-catalog-cf7' ),
					),
					'slug'       => array(
						'type'        => 'string',
						'description' => __( 'The form\'s internal slug (post name).', 'abilities-catalog-cf7' ),
					),
					'title'      => array(
						'type'        => 'string',
						'description' => __( 'The form title.', 'abilities-catalog-cf7' ),
					),
					'locale'     => array(
						'type'        => 'string',
						'description' => __( 'The form locale code (e.g. en_US), or an empty string when none is set.', 'abilities-catalog-cf7' ),
					),
					'properties' => Cf7FormSchema::propertiesSchema(),
					'shortcode'  => array(
						'type'        => 'string',
						'description' => __( 'The ready-to-embed shortcode, e.g. [contact-form-7 id="abc1234" title="Contact"]. Put this in a page or post to render the form.', 'abilities-catalog-cf7' ),
					),
					'hash'       => array(
						'type'        => 'string',
						'description' => __( 'The 7-character hash that identifies the form in its shortcode.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
			'execute_callback'    => array( $this, 'execute' ),
			'permission_callback' => array( $this, 'hasPermission' ),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		);
	}

	/**
	 * Permission check: CF7's edit capability for contact forms.
	 *
	 * CF7 gates GET-one on the EDIT cap, not the read cap (asymmetric with
	 * `og-cf7/list-forms`, which uses the read cap), so this matches it with the CF7
	 * meta-cap `wpcf7_edit_contact_form` (mapped to `publish_pages` by default,
	 * honoring a redefined `WPCF7_ADMIN_READ_WRITE_CAPABILITY`) — never widening
	 * visibility past what CF7's own screen allows. This is a coarse type-level
	 * guard; the wrapped route surfaces the specific 404 (`wpcf7_not_found`) for a
	 * missing form.
	 *
	 * @param mixed $input The validated input data.
	 * @return bool True if the current user may read this contact form.
	 */
	public function hasPermission( $input ): bool {
		return Cf7Plugin::isActive() && current_user_can( 'wpcf7_edit_contact_form' );
	}

	/**
	 * Executes the ability by dispatching the internal CF7 REST request.
	 *
	 * @param mixed $input The validated input data.
	 * @return array<string,mixed>|\WP_Error The form configuration, or the REST error.
	 */
	public function execute( $input ) {
		if ( ! Cf7Plugin::isActive() ) {
			return Cf7Plugin::unavailable();
		}

		$input = is_array( $input ) ? $input : array();
		$id    = absint( $input['id'] ?? 0 );

		$request  = new WP_REST_Request( 'GET', '/contact-form-7/v1/contact-forms/' . $id );
		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return RestError::from( $response );
		}

		$data = rest_get_server()->response_to_data( $response, false );

		return array(
			'id'         => (int) ( $data['id'] ?? $id ),
			'slug'       => (string) ( $data['slug'] ?? '' ),
			'title'      => (string) ( $data['title'] ?? '' ),
			'locale'     => (string) ( $data['locale'] ?? '' ),
			'properties' => (object) ( $data['properties'] ?? array() ),
			'shortcode'  => Cf7Plugin::shortcode( $id ),
			'hash'       => Cf7Plugin::hash( $id ),
		);
	}
}
