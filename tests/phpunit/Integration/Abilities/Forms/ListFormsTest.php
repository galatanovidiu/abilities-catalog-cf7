<?php
/**
 * Integration tests for the og-cf7/list-forms ability.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use WP_Error;

/**
 * Exercises og-cf7/list-forms: shaped rows, the embeddable shortcode, and the guard.
 */
final class ListFormsTest extends Cf7FormsTestCase {

	public function test_ability_is_registered(): void {
		$ability = wp_get_ability( 'og-cf7/list-forms' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'og-cf7/list-forms', $ability->get_name() );
	}

	public function test_admin_lists_forms_with_shortcode(): void {
		$this->actingAs( 'administrator' );
		$id = $this->seedForm( array( 'title' => 'Listed form' ) );

		$result = wp_get_ability( 'og-cf7/list-forms' )->execute( array() );

		$this->assertIsArray( $result );
		$this->assertSame( array( 'items', 'total' ), array_keys( $result ) );
		$this->assertGreaterThanOrEqual( 1, $result['total'] );
		$this->assertSame( count( $result['items'] ), $result['total'] );

		$row = null;
		foreach ( $result['items'] as $item ) {
			if ( (int) $item['id'] === $id ) {
				$row = $item;
			}
		}

		$this->assertNotNull( $row, 'The seeded form should appear in the list.' );
		$this->assertSame(
			array( 'id', 'hash', 'slug', 'title', 'locale', 'shortcode' ),
			array_keys( $row )
		);
		$this->assertSame( 'Listed form', $row['title'] );
		$this->assertStringContainsString( '[contact-form-7 id="', $row['shortcode'] );
		$this->assertStringContainsString( $row['hash'], $row['shortcode'] );
	}

	public function test_search_filters_by_title(): void {
		$this->actingAs( 'administrator' );
		$this->seedForm( array( 'title' => 'Newsletter signup' ) );
		$this->seedForm( array( 'title' => 'Support request' ) );

		$result = wp_get_ability( 'og-cf7/list-forms' )->execute( array( 'search' => 'Newsletter' ) );

		$this->assertIsArray( $result );
		$titles = wp_list_pluck( $result['items'], 'title' );
		$this->assertContains( 'Newsletter signup', $titles );
		$this->assertNotContains( 'Support request', $titles );
	}

	public function test_logged_out_user_is_denied(): void {
		$this->seedForm();
		wp_set_current_user( 0 );

		$result = wp_get_ability( 'og-cf7/list-forms' )->execute( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_is_denied(): void {
		$this->seedForm();
		$this->actingAs( 'subscriber' );

		$result = wp_get_ability( 'og-cf7/list-forms' )->execute( array() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}
}
