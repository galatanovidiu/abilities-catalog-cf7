<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Projects raw `contact-form-7/v1` list rows into flat summary rows for the
 * `cf7/list-forms` ability.
 *
 * CF7's list row is already small — `id`, `hash`, `slug`, `title`, `locale` — but
 * it omits the one field a consumer needs to place a form: the shortcode. This
 * shaper copies the row's fixed field set and ADDS the embeddable `shortcode`
 * (read from the live object via {@see Cf7Plugin::shortcode()}), then pins the
 * schema closed so the runtime row and the declared schema cannot drift.
 *
 * This is NOT under `includes/Abilities/`, so the Registry never treats it as an
 * ability.
 *
 * @since 0.3.0
 */
final class Cf7FormListShaper {

	/**
	 * Flat summary row for a CF7 list item, with the embeddable shortcode added.
	 *
	 * @param array<string,mixed> $item A single row from a `GET contact-form-7/v1/contact-forms` response.
	 * @return array<string,mixed> The summary row including `shortcode`.
	 */
	public static function formSummary( array $item ): array {
		$id = (int) ( $item['id'] ?? 0 );

		return array(
			'id'        => $id,
			'hash'      => (string) ( $item['hash'] ?? '' ),
			'slug'      => (string) ( $item['slug'] ?? '' ),
			'title'     => (string) ( $item['title'] ?? '' ),
			'locale'    => (string) ( $item['locale'] ?? '' ),
			'shortcode' => Cf7Plugin::shortcode( $id ),
		);
	}

	/**
	 * The `output_schema` item definition matching {@see self::formSummary()}.
	 *
	 * @return array<string,mixed> A JSON-Schema object fragment.
	 */
	public static function formItemSchema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'id', 'shortcode' ),
			'properties'           => array(
				'id'        => array(
					'type'        => 'integer',
					'description' => __( 'The contact-form ID. Read the full form with cf7/get-form.', 'abilities-catalog-cf7' ),
				),
				'hash'      => array(
					'type'        => 'string',
					'description' => __( 'The 7-character hash that identifies the form in its shortcode.', 'abilities-catalog-cf7' ),
				),
				'slug'      => array(
					'type'        => 'string',
					'description' => __( 'The form\'s internal slug (post name).', 'abilities-catalog-cf7' ),
				),
				'title'     => array(
					'type'        => 'string',
					'description' => __( 'The form title.', 'abilities-catalog-cf7' ),
				),
				'locale'    => array(
					'type'        => 'string',
					'description' => __( 'The form locale code (e.g. en_US), or an empty string when none is set.', 'abilities-catalog-cf7' ),
				),
				'shortcode' => array(
					'type'        => 'string',
					'description' => __( 'The ready-to-embed shortcode, e.g. [contact-form-7 id="abc1234" title="Contact"]. Put this in a page or post to render the form.', 'abilities-catalog-cf7' ),
				),
			),
			'additionalProperties' => false,
		);
	}
}
