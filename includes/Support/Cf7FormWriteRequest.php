<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Support;

use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared request-forwarding and result-shaping for `og-cf7/create-form` and
 * `og-cf7/update-form`.
 *
 * Both abilities forward the same writable fields to the `contact-form-7/v1` save
 * route and project the same result, so that logic lives here once. The only
 * behavioral difference is blank handling: create skips empty strings so an
 * omitted group falls back to CF7's default template, while update forwards a
 * present-but-empty value so an explicit "" can blank a field (the create/update
 * field-forwarding split the catalog uses elsewhere).
 *
 * This is NOT under `includes/Abilities/`, so the Registry never treats it as an
 * ability.
 *
 * @since 0.3.0
 */
final class Cf7FormWriteRequest {

	/**
	 * String fields forwarded as-is to the save route.
	 *
	 * @var string[]
	 */
	private const STRING_FIELDS = array( 'title', 'locale', 'form', 'additional_settings' );

	/**
	 * Object fields (mail templates and messages) forwarded when present.
	 *
	 * @var string[]
	 */
	private const OBJECT_FIELDS = array( 'mail', 'mail_2', 'messages' );

	/**
	 * Forwards the writable form fields from validated input onto the REST request.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request        The save request to populate.
	 * @param array<string,mixed>                  $input          The validated ability input.
	 * @param bool                                 $forward_blanks True to forward present-but-empty strings (update); false to skip them (create).
	 * @return void
	 */
	public static function fill( WP_REST_Request $request, array $input, bool $forward_blanks ): void {
		foreach ( self::STRING_FIELDS as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}

			$value = (string) $input[ $field ];
			if ( ! $forward_blanks && '' === $value ) {
				continue;
			}

			$request->set_param( $field, $value );
		}

		foreach ( self::OBJECT_FIELDS as $field ) {
			if ( ! isset( $input[ $field ] ) || ! is_array( $input[ $field ] ) ) {
				continue;
			}

			$request->set_param( $field, $input[ $field ] );
		}
	}

	/**
	 * Projects a CF7 create/update REST response into the flat ability result.
	 *
	 * @param array<string,mixed> $data The decoded REST response body.
	 * @return array<string,mixed> The flat result, including shortcode, hash, and edit_link.
	 */
	public static function shapeResult( array $data ): array {
		$id         = (int) ( $data['id'] ?? 0 );
		$properties = is_array( $data['properties'] ?? null ) ? $data['properties'] : array();
		$mail       = is_array( $properties['mail'] ?? null ) ? $properties['mail'] : array();

		return array(
			'id'                      => $id,
			'slug'                    => (string) ( $data['slug'] ?? '' ),
			'title'                   => (string) ( $data['title'] ?? '' ),
			'locale'                  => (string) ( $data['locale'] ?? '' ),
			'properties'              => (object) $properties,
			'mail_recipient'          => (string) ( $mail['recipient'] ?? '' ),
			'mail_additional_headers' => (string) ( $mail['additional_headers'] ?? '' ),
			'config_errors'           => (object) ( is_array( $data['config_errors'] ?? null ) ? $data['config_errors'] : array() ),
			'shortcode'               => Cf7Plugin::shortcode( $id ),
			'hash'                    => Cf7Plugin::hash( $id ),
			'edit_link'               => self::editLink( $id ),
		);
	}

	/**
	 * The wp-admin URL to edit a contact form.
	 *
	 * Mirrors CF7's own list-table edit link: `admin.php?page=wpcf7&post={id}&action=edit`.
	 *
	 * @param int $id The contact-form ID.
	 * @return string The edit URL, or an empty string for an invalid ID.
	 */
	public static function editLink( int $id ): string {
		if ( $id < 1 ) {
			return '';
		}

		return add_query_arg(
			array(
				'page'   => 'wpcf7',
				'post'   => $id,
				'action' => 'edit',
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The output schema shared by create-form and update-form.
	 *
	 * @return array<string,mixed> A JSON-Schema object fragment.
	 */
	public static function outputSchema(): array {
		return array(
			'type'                 => 'object',
			'required'             => array( 'id', 'shortcode', 'edit_link' ),
			'properties'           => array(
				'id'                      => array(
					'type'        => 'integer',
					'description' => __( 'The form\'s ID.', 'abilities-catalog-cf7' ),
				),
				'slug'                    => array(
					'type'        => 'string',
					'description' => __( 'The form\'s internal slug (post name).', 'abilities-catalog-cf7' ),
				),
				'title'                   => array(
					'type'        => 'string',
					'description' => __( 'The resulting form title (blank input becomes "Untitled").', 'abilities-catalog-cf7' ),
				),
				'locale'                  => array(
					'type'        => 'string',
					'description' => __( 'The resulting locale code (an invalid input becomes en_US).', 'abilities-catalog-cf7' ),
				),
				'properties'              => Cf7FormSchema::propertiesSchema(),
				'mail_recipient'          => array(
					'type'        => 'string',
					'description' => __( 'The resulting primary-mail recipient: where every submission will be emailed. Surfaced for review.', 'abilities-catalog-cf7' ),
				),
				'mail_additional_headers' => array(
					'type'        => 'string',
					'description' => __( 'The resulting primary-mail extra headers. A Bcc:/Cc: line here copies every submission elsewhere. Surfaced for review.', 'abilities-catalog-cf7' ),
				),
				'config_errors'           => array(
					'type'                 => 'object',
					'description'          => __( 'CF7\'s configuration-validation results, keyed by section (e.g. "mail.recipient"); each value is a list of {message, link}. An empty object means no problems were found.', 'abilities-catalog-cf7' ),
					'additionalProperties' => true,
				),
				'shortcode'               => array(
					'type'        => 'string',
					'description' => __( 'The ready-to-embed shortcode, e.g. [contact-form-7 id="abc1234" title="Contact"].', 'abilities-catalog-cf7' ),
				),
				'hash'                    => array(
					'type'        => 'string',
					'description' => __( 'The 7-character hash that identifies the form in its shortcode.', 'abilities-catalog-cf7' ),
				),
				'edit_link'               => array(
					'type'        => 'string',
					'description' => __( 'The wp-admin URL to edit the form. Surface this so a human can review it.', 'abilities-catalog-cf7' ),
				),
			),
			'additionalProperties' => false,
		);
	}
}
