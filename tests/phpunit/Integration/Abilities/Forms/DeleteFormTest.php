<?php
/**
 * Integration tests for the og-cf7/delete-form ability.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use WP_Error;
use WP_Post;

/**
 * Exercises og-cf7/delete-form: permanent (force) deletion, the missing-form 404, and
 * that a denied caller leaves the form intact.
 */
final class DeleteFormTest extends Cf7FormsTestCase {

	public function test_ability_is_registered(): void {
		$ability = wp_get_ability( 'og-cf7/delete-form' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'og-cf7/delete-form', $ability->get_name() );
	}

	public function test_delete_permanently_removes_the_form(): void {
		$this->actingAs( 'administrator' );
		$id = $this->seedForm( array( 'title' => 'Disposable' ) );

		$result = wp_get_ability( 'og-cf7/delete-form' )->execute( array( 'id' => $id ) );

		$this->assertIsArray( $result );
		$this->assertSame( array( 'deleted', 'id', 'title' ), array_keys( $result ) );
		$this->assertTrue( $result['deleted'] );
		$this->assertSame( $id, $result['id'] );
		$this->assertSame( 'Disposable', $result['title'] );

		// Force delete bypasses the Trash: the post is gone, not trashed.
		$this->assertNull( get_post( $id ) );
	}

	public function test_missing_form_returns_404_not_permission_error(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'og-cf7/delete-form' )->execute( array( 'id' => 99999999 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcf7_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_subscriber_is_denied_and_form_survives(): void {
		$id = $this->seedForm();
		$this->actingAs( 'subscriber' );

		$result = wp_get_ability( 'og-cf7/delete-form' )->execute( array( 'id' => $id ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertInstanceOf( WP_Post::class, get_post( $id ) );
	}
}
