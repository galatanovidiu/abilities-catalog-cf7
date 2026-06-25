<?php
/**
 * Integration tests for the og-cf7/duplicate-form ability.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use WP_Error;
use WP_Post;

/**
 * Exercises og-cf7/duplicate-form: an independent persisted copy, and the missing
 * source 404.
 */
final class DuplicateFormTest extends Cf7FormsTestCase {

	public function test_ability_is_registered(): void {
		$ability = wp_get_ability( 'og-cf7/duplicate-form' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'og-cf7/duplicate-form', $ability->get_name() );
	}

	public function test_duplicate_creates_independent_copy(): void {
		$this->actingAs( 'administrator' );
		$source = $this->seedForm( array( 'title' => 'Original' ) );

		$result = wp_get_ability( 'og-cf7/duplicate-form' )->execute( array( 'id' => $source ) );

		$this->assertIsArray( $result );
		$this->assertSame(
			array( 'id', 'slug', 'title', 'shortcode', 'hash', 'edit_link' ),
			array_keys( $result )
		);
		$this->assertNotSame( $source, $result['id'] );
		$this->assertSame( 'Original_copy', $result['title'] );
		$this->assertStringContainsString( '[contact-form-7 id="', $result['shortcode'] );

		// The copy is a real, persisted form, and the source still exists.
		$copy = get_post( $result['id'] );
		$this->assertInstanceOf( WP_Post::class, $copy );
		$this->assertSame( 'wpcf7_contact_form', $copy->post_type );
		$this->assertInstanceOf( WP_Post::class, get_post( $source ) );
	}

	public function test_missing_source_returns_404(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'og-cf7/duplicate-form' )->execute( array( 'id' => 99999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcf7_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_subscriber_is_denied(): void {
		$source = $this->seedForm();
		$this->actingAs( 'subscriber' );

		$result = wp_get_ability( 'og-cf7/duplicate-form' )->execute( array( 'id' => $source ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}
}
