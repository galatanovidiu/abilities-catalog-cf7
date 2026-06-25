<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared JSON-Schema fragments for the Contact Form 7 form abilities.
 *
 * Three abilities return the same form `properties` object (`og-cf7/get-form`,
 * `og-cf7/create-form`, `og-cf7/update-form`) and two share the same writable input
 * fields (create and update). Centralizing those fragments here keeps the
 * abilities in sync.
 *
 * Two deliberate schema choices:
 *
 * 1. The `properties` object and its mail/messages sub-objects use
 *    `additionalProperties: true`. Unlike a projection the catalog owns, this is
 *    CF7's own configuration shape, and active CF7 modules add keys to it through
 *    the `wpcf7_contact_form_properties` and `wpcf7_messages` filters. A closed
 *    schema would reject a perfectly valid form on a site running such a module.
 * 2. There is an input/output asymmetry for `form` and `additional_settings`: CF7
 *    returns them as parsed objects (`{content, fields}` / `{content, settings}`)
 *    but accepts them as raw strings on save. The input descriptions point the
 *    agent at the `.content` field of the read output so a round-trip works.
 *
 * This is NOT under `includes/Abilities/`, so the Registry never treats it as an
 * ability.
 *
 * @since 0.3.0
 */
final class Cf7FormSchema {

	/**
	 * The `properties` object as CF7 returns it from get/create/update.
	 *
	 * @return array<string,mixed> A JSON-Schema object fragment.
	 */
	public static function propertiesSchema(): array {
		return array(
			'type'                 => 'object',
			'description'          => __( 'The form configuration as Contact Form 7 stores it. Always carries form, mail, mail_2, messages, and additional_settings; active CF7 modules may add more keys.', 'abilities-catalog-cf7' ),
			'properties'           => array(
				'form'                => array(
					'type'                 => 'object',
					'description'          => __( 'The form template: "content" is the form-tag markup, "fields" lists the parsed form-tags.', 'abilities-catalog-cf7' ),
					'additionalProperties' => true,
				),
				'mail'                => self::mailOutputSchema( __( 'The primary email sent on submission.', 'abilities-catalog-cf7' ) ),
				'mail_2'              => self::mailOutputSchema( __( 'The secondary email, sent only when its "active" flag is true.', 'abilities-catalog-cf7' ) ),
				'messages'            => array(
					'type'                 => 'object',
					'description'          => __( 'Response messages keyed by name (e.g. mail_sent_ok, validation_error). Each value is a string.', 'abilities-catalog-cf7' ),
					'additionalProperties' => true,
				),
				'additional_settings' => array(
					'type'                 => 'object',
					'description'          => __( 'Extra settings: "content" is the raw text, "settings" the parsed name/value pairs. Flags like skip_mail or demo_mode here can suppress the mail send.', 'abilities-catalog-cf7' ),
					'additionalProperties' => true,
				),
			),
			'additionalProperties' => true,
		);
	}

	/**
	 * The writable form-property input fields shared by create-form and update-form.
	 *
	 * Excludes `title` (required on create, optional on update) and `id` (update
	 * only), which each ability declares itself.
	 *
	 * @return array<string,mixed> Property-name => JSON-Schema fragment.
	 */
	public static function writableProperties(): array {
		return array(
			'locale'              => array(
				'type'        => 'string',
				'description' => __( 'The form locale code, e.g. "en_US". An invalid value falls back to en_US.', 'abilities-catalog-cf7' ),
			),
			'form'                => array(
				'type'        => 'string',
				'description' => __( 'The form body as Contact Form 7 form-tag markup, e.g. "[text* your-name]\n[email* your-email]\n[submit \"Send\"]". This is a STRING, not the parsed object og-cf7/get-form returns: to edit an existing form, take its current markup from og-cf7/get-form\'s properties.form.content. Omit to keep CF7\'s default template (on create) or the current markup (on update).', 'abilities-catalog-cf7' ),
			),
			'mail'                => self::mailInputSchema( __( 'The primary email sent on every submission. Always active.', 'abilities-catalog-cf7' ) ),
			'mail_2'              => self::mailInputSchema( __( 'An optional secondary email. Sent only when its "active" field is true (default false).', 'abilities-catalog-cf7' ) ),
			'messages'            => array(
				'type'                 => 'object',
				'description'          => __( 'Response messages keyed by name. Core keys: mail_sent_ok, mail_sent_ng, validation_error, spam, accept_terms, invalid_required, invalid_too_long, invalid_too_short. Each value is a string; unknown keys are ignored by CF7.', 'abilities-catalog-cf7' ),
				'properties'           => array(
					'mail_sent_ok'     => array(
						'type'        => 'string',
						'description' => __( 'Shown when the message is sent successfully.', 'abilities-catalog-cf7' ),
					),
					'mail_sent_ng'     => array(
						'type'        => 'string',
						'description' => __( 'Shown when sending fails.', 'abilities-catalog-cf7' ),
					),
					'validation_error' => array(
						'type'        => 'string',
						'description' => __( 'Shown when one or more fields are invalid.', 'abilities-catalog-cf7' ),
					),
					'spam'             => array(
						'type'        => 'string',
						'description' => __( 'Shown when the submission is flagged as spam.', 'abilities-catalog-cf7' ),
					),
				),
				'additionalProperties' => true,
			),
			'additional_settings' => array(
				'type'        => 'string',
				'description' => __( 'Extra settings as raw text, one "name: value" per line (a STRING, not the parsed object og-cf7/get-form returns; take it from properties.additional_settings.content to edit). Flags such as "skip_mail: on" or "demo_mode: on" suppress the mail send.', 'abilities-catalog-cf7' ),
			),
		);
	}

	/**
	 * The output sub-schema for a mail block (`mail` / `mail_2`).
	 *
	 * @param string $description The block-level description.
	 * @return array<string,mixed> A JSON-Schema object fragment.
	 */
	private static function mailOutputSchema( string $description ): array {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'properties'           => array(
				'active'             => array(
					'type'        => 'boolean',
					'description' => __( 'Whether this email is sent.', 'abilities-catalog-cf7' ),
				),
				'recipient'          => array(
					'type'        => 'string',
					'description' => __( 'The address submissions are emailed to.', 'abilities-catalog-cf7' ),
				),
				'sender'             => array(
					'type'        => 'string',
					'description' => __( 'The From header.', 'abilities-catalog-cf7' ),
				),
				'subject'            => array(
					'type'        => 'string',
					'description' => __( 'The email subject.', 'abilities-catalog-cf7' ),
				),
				'body'               => array(
					'type'        => 'string',
					'description' => __( 'The email body.', 'abilities-catalog-cf7' ),
				),
				'additional_headers' => array(
					'type'        => 'string',
					'description' => __( 'Extra raw email headers, one per line. A Bcc:/Cc: line copies every submission to that address.', 'abilities-catalog-cf7' ),
				),
				'attachments'        => array(
					'type'        => 'string',
					'description' => __( 'Attachment mail-tags or paths, one per line.', 'abilities-catalog-cf7' ),
				),
				'use_html'           => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the email is sent as HTML.', 'abilities-catalog-cf7' ),
				),
				'exclude_blank'      => array(
					'type'        => 'boolean',
					'description' => __( 'Whether empty fields are omitted from the email.', 'abilities-catalog-cf7' ),
				),
			),
			'additionalProperties' => true,
		);
	}

	/**
	 * The input sub-schema for a mail block (`mail` / `mail_2`).
	 *
	 * @param string $description The block-level description.
	 * @return array<string,mixed> A JSON-Schema object fragment.
	 */
	private static function mailInputSchema( string $description ): array {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'properties'           => array(
				'active'             => array(
					'type'        => 'boolean',
					'description' => __( 'Whether this email is sent. The primary mail is forced active on save regardless; this controls mail_2.', 'abilities-catalog-cf7' ),
				),
				'recipient'          => array(
					'type'        => 'string',
					'description' => __( 'The address submissions are emailed to. WARNING: changing this on an existing form reroutes where every future submission is sent. CF7 does not validate it.', 'abilities-catalog-cf7' ),
				),
				'sender'             => array(
					'type'        => 'string',
					'description' => __( 'The From header.', 'abilities-catalog-cf7' ),
				),
				'subject'            => array(
					'type'        => 'string',
					'description' => __( 'The email subject. Mail-tags like [your-subject] are allowed.', 'abilities-catalog-cf7' ),
				),
				'body'               => array(
					'type'        => 'string',
					'description' => __( 'The email body. Mail-tags like [your-name] and [your-message] are allowed.', 'abilities-catalog-cf7' ),
				),
				'additional_headers' => array(
					'type'        => 'string',
					'description' => __( 'Extra raw email headers, one per line (e.g. "Reply-To: [your-email]"). WARNING: a Bcc:/Cc: line silently copies every submission to that address; CF7 does not strip or validate these.', 'abilities-catalog-cf7' ),
				),
				'attachments'        => array(
					'type'        => 'string',
					'description' => __( 'Attachment mail-tags or paths, one per line.', 'abilities-catalog-cf7' ),
				),
				'use_html'           => array(
					'type'        => 'boolean',
					'description' => __( 'Send the email as HTML.', 'abilities-catalog-cf7' ),
				),
				'exclude_blank'      => array(
					'type'        => 'boolean',
					'description' => __( 'Omit empty fields from the email.', 'abilities-catalog-cf7' ),
				),
			),
			'additionalProperties' => false,
		);
	}
}
