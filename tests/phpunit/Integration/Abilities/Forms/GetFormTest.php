<?php
/**
 * Integration tests for the og-cf7/get-form ability.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use WP_Error;

/**
 * Exercises og-cf7/get-form: full configuration, the gap-closing shortcode/hash, and
 * the missing-form 404 that must not collapse to a permission error.
 */
final class GetFormTest extends Cf7FormsTestCase {

	public function test_ability_is_registered(): void {
		$ability = wp_get_ability( 'og-cf7/get-form' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'og-cf7/get-form', $ability->get_name() );
	}

	public function test_admin_reads_form_with_shortcode_and_properties(): void {
		$this->actingAs( 'administrator' );
		$id = $this->seedForm( array( 'title' => 'Read me' ) );

		$result = wp_get_ability( 'og-cf7/get-form' )->execute( array( 'id' => $id ) );

		$this->assertIsArray( $result );
		$this->assertSame(
			array( 'id', 'slug', 'title', 'locale', 'properties', 'shortcode', 'hash' ),
			array_keys( $result )
		);
		$this->assertSame( $id, $result['id'] );
		$this->assertSame( 'Read me', $result['title'] );
		$this->assertStringContainsString( '[contact-form-7 id="', $result['shortcode'] );
		$this->assertStringContainsString( $result['hash'], $result['shortcode'] );

		// properties carries CF7's shaped configuration object.
		$properties = (array) $result['properties'];
		$this->assertArrayHasKey( 'form', $properties );
		$this->assertArrayHasKey( 'mail', $properties );
		$this->assertSame( 'owner@example.org', $properties['mail']['recipient'] );
	}

	public function test_missing_form_returns_404_not_permission_error(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'og-cf7/get-form' )->execute( array( 'id' => 99999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcf7_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
		$this->assertNotSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_logged_out_user_is_denied(): void {
		$id = $this->seedForm();
		wp_set_current_user( 0 );

		$result = wp_get_ability( 'og-cf7/get-form' )->execute( array( 'id' => $id ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_is_denied(): void {
		$id = $this->seedForm();
		$this->actingAs( 'subscriber' );

		$result = wp_get_ability( 'og-cf7/get-form' )->execute( array( 'id' => $id ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}
}
