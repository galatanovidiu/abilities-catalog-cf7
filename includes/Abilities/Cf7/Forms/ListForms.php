<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Abilities\Cf7\Forms;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7FormListShaper;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\RestError;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read ability: `og-cf7/list-forms`.
 *
 * Wraps `GET contact-form-7/v1/contact-forms` via `rest_do_request()` and returns
 * each contact form as a flat summary row. CF7's list row carries `id`, `hash`,
 * `slug`, `title`, and `locale`; {@see Cf7FormListShaper} adds the embeddable
 * `shortcode` read from the live object, so a consumer can place any listed form
 * without a second call.
 *
 * Only available when Contact Form 7 is active (it is a {@see ConditionalAbility}).
 * The CF7 list route returns a bare array with no pagination headers, so `total`
 * is the number of rows returned, not necessarily the full matching count.
 *
 * @since 0.3.0
 */
final class ListForms implements ConditionalAbility {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'og-cf7/list-forms';
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
			'label'               => __( 'List Forms', 'abilities-catalog-cf7' ),
			'description'         => __( 'Returns the site\'s Contact Form 7 contact forms as flat summary rows, each with its id, title, hash, and ready-to-embed shortcode. Use the shortcode to place a form on a page or post (e.g. with content/create-page); use og-cf7/get-form for one form\'s full configuration. Read-only: does not return the form fields, mail settings, or any submissions.', 'abilities-catalog-cf7' ),
			'category'            => 'og-cf7',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'search'   => array(
						'type'        => 'string',
						'description' => __( 'Limit results to forms whose title matches a search term.', 'abilities-catalog-cf7' ),
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'default'     => 100,
						'description' => __( 'Maximum number of forms to return (1-100). Defaults to 100, which covers every form on a typical site.', 'abilities-catalog-cf7' ),
					),
					'offset'   => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'default'     => 0,
						'description' => __( 'Number of forms to skip before returning results, for paging past the first per_page forms.', 'abilities-catalog-cf7' ),
					),
					'order'    => array(
						'type'        => 'string',
						'enum'        => array( 'asc', 'desc' ),
						'default'     => 'asc',
						'description' => __( 'Sort direction by form ID: "asc" (oldest first) or "desc" (newest first).', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'                 => 'object',
				'required'             => array( 'items', 'total' ),
				'properties'           => array(
					'items' => array(
						'type'        => 'array',
						'description' => __( 'The contact forms as flat summary rows. Use og-cf7/get-form for a single form\'s full configuration.', 'abilities-catalog-cf7' ),
						'items'       => Cf7FormListShaper::formItemSchema(),
					),
					'total' => array(
						'type'        => 'integer',
						'description' => __( 'The number of forms returned. CF7\'s list route exposes no total header, so this counts the returned rows, not necessarily every matching form when paging.', 'abilities-catalog-cf7' ),
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
	 * Permission check: CF7's read capability for contact forms.
	 *
	 * Encodes the catalog baseline for `og-cf7/list-forms`: the CF7 meta-cap
	 * `wpcf7_read_contact_forms` (mapped to `edit_posts` by default, honoring a
	 * site that redefines `WPCF7_ADMIN_READ_CAPABILITY`). The meta-cap is unmapped
	 * when CF7 is inactive, so the explicit activity guard keeps the denial clean.
	 *
	 * @param mixed $input The validated input data.
	 * @return bool True if the current user may read contact forms.
	 */
	public function hasPermission( $input ): bool {
		return Cf7Plugin::isActive() && current_user_can( 'wpcf7_read_contact_forms' );
	}

	/**
	 * Executes the ability by dispatching the internal CF7 REST request.
	 *
	 * @param mixed $input The validated input data.
	 * @return array<string,mixed>|\WP_Error The list of forms, or the REST error.
	 */
	public function execute( $input ) {
		if ( ! Cf7Plugin::isActive() ) {
			return Cf7Plugin::unavailable();
		}

		$input = is_array( $input ) ? $input : array();

		$request = new WP_REST_Request( 'GET', '/contact-form-7/v1/contact-forms' );
		if ( ! empty( $input['search'] ) ) {
			$request->set_param( 'search', (string) $input['search'] );
		}
		$request->set_param( 'per_page', max( 1, min( 100, absint( $input['per_page'] ?? 100 ) ) ) );
		$request->set_param( 'offset', absint( $input['offset'] ?? 0 ) );
		$request->set_param( 'order', 'desc' === strtolower( (string) ( $input['order'] ?? 'asc' ) ) ? 'DESC' : 'ASC' );

		$response = rest_do_request( $request );
		if ( $response->is_error() ) {
			return RestError::from( $response );
		}

		$data = rest_get_server()->response_to_data( $response, false );

		$rows = array();
		foreach ( is_array( $data ) ? $data : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$rows[] = Cf7FormListShaper::formSummary( $item );
		}

		return array(
			'items' => $rows,
			'total' => count( $rows ),
		);
	}
}
