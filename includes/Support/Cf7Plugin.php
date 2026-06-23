<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Support;

use WPCF7_ContactForm;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The catalog's only gateway to Contact Form 7 symbols.
 *
 * Contact Form 7 is an optional third-party dependency that is usually inactive.
 * Every CF7 symbol the catalog touches passes through this facade, so the rest of
 * the code never references a `wpcf7_*` function or `WPCF7_*` class directly. That
 * keeps two concerns in one place:
 *
 * 1. The availability guard. {@see isActive()} is the single source of truth for
 *    "CF7 is installed and enabled". The CF7 abilities are
 *    {@see \GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility}s gated on
 *    it, so they do not register when CF7 is off; the abilities and the shaper also
 *    call the helpers below only after confirming it.
 * 2. The REST routes (`contact-form-7/v1`) carry neither the embeddable shortcode
 *    nor a copy endpoint, so those reads come straight off the live object. The
 *    helpers here perform exactly those reads and return plain scalars, so an
 *    ability never holds a `WPCF7_ContactForm` instance.
 *
 * This is NOT under `includes/Abilities/`, so the Registry never treats it as an
 * ability.
 *
 * @since 0.3.0
 */
final class Cf7Plugin {

	/**
	 * Whether Contact Form 7 is installed and active.
	 *
	 * Detects CF7 by its API symbols, which load with the plugin, so this is
	 * safe to call whether or not CF7 is active. It is the gate every CF7 ability
	 * and helper checks before touching a CF7 symbol.
	 *
	 * @return bool True when CF7's contact-form API is loaded.
	 */
	public static function isActive(): bool {
		return function_exists( 'wpcf7_contact_form' ) && class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * The typed error an ability returns if asked to run while CF7 is inactive.
	 *
	 * The CF7 abilities do not register when CF7 is off, so the Abilities API
	 * never routes a call here. This covers the defensive path of an ability
	 * instantiated and executed directly: it returns a clear, stable error rather
	 * than touching an undefined CF7 symbol.
	 *
	 * @return \WP_Error A `cf7_inactive` error with HTTP status 409.
	 */
	public static function unavailable(): WP_Error {
		return new WP_Error(
			'cf7_inactive',
			__( 'Contact Form 7 is not active, so contact-form abilities are unavailable.', 'abilities-catalog-cf7' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * The embeddable shortcode for a form, e.g. `[contact-form-7 id="abc1234" title="Contact"]`.
	 *
	 * Read from the live object because the REST list/get bodies do not carry it.
	 * CF7's new-format shortcode is hash-based, which is the exact string a page or
	 * block needs to render the form.
	 *
	 * @param int $id The contact-form post ID.
	 * @return string The shortcode, or an empty string when CF7 is inactive or the form is missing.
	 */
	public static function shortcode( int $id ): string {
		$form = self::form( $id );

		return null === $form ? '' : (string) $form->shortcode();
	}

	/**
	 * The short hash that identifies a form in its shortcode.
	 *
	 * @param int $id The contact-form post ID.
	 * @return string The 7-character hash, or an empty string when CF7 is inactive or the form is missing.
	 */
	public static function hash( int $id ): string {
		$form = self::form( $id );

		return null === $form ? '' : (string) $form->hash();
	}

	/**
	 * Duplicates a form and persists the copy, returning the new form's ID.
	 *
	 * CF7 exposes no REST route for copy, so this wraps the object method directly.
	 * `copy()` returns an UNSAVED clone (title `"{source title}_copy"`), so this
	 * calls `save()` to persist it — the new row would otherwise never exist.
	 *
	 * @param int $source_id The contact-form post ID to copy.
	 * @return int The new form's ID, or 0 when CF7 is inactive, the source is missing, or the save failed.
	 */
	public static function duplicate( int $source_id ): int {
		$form = self::form( $source_id );

		if ( null === $form ) {
			return 0;
		}

		return (int) $form->copy()->save();
	}

	/**
	 * Resolves a contact-form post ID to its live object, or null.
	 *
	 * The one place a `WPCF7_ContactForm` is obtained; guards CF7 activity and a
	 * positive ID so callers never have to.
	 *
	 * @param int $id The contact-form post ID.
	 * @return \WPCF7_ContactForm|null The form object, or null when CF7 is inactive, the ID is invalid, or no such form exists.
	 */
	private static function form( int $id ): ?WPCF7_ContactForm {
		if ( ! self::isActive() || $id < 1 ) {
			return null;
		}

		$form = wpcf7_contact_form( $id );

		return $form instanceof WPCF7_ContactForm ? $form : null;
	}
}
