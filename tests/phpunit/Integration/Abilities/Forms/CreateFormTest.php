<?php
/**
 * Integration tests for the og-cf7/create-form ability.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use WP_Error;
use WP_Post;

/**
 * Exercises og-cf7/create-form: real persistence (the context=save requirement),
 * default-template fallback, and the mail-transparency output.
 */
final class CreateFormTest extends Cf7FormsTestCase {

	public function test_ability_is_registered(): void {
		$ability = wp_get_ability( 'og-cf7/create-form' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'og-cf7/create-form', $ability->get_name() );
	}

	public function test_create_persists_the_form(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'og-cf7/create-form' )->execute( array( 'title' => 'Contact us' ) );

		$this->assertIsArray( $result );
		$this->assertSame(
			array( 'id', 'slug', 'title', 'locale', 'properties', 'mail_recipient', 'mail_additional_headers', 'config_errors', 'shortcode', 'hash', 'edit_link' ),
			array_keys( $result )
		);

		// The form must be a real, persisted post: proves the context=save dispatch.
		$this->assertGreaterThan( 0, $result['id'] );
		$post = get_post( $result['id'] );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( 'wpcf7_contact_form', $post->post_type );
		$this->assertSame( 'Contact us', $result['title'] );
		$this->assertStringContainsString( '[contact-form-7 id="', $result['shortcode'] );
		$this->assertStringContainsString( '/admin.php?page=wpcf7', $result['edit_link'] );
	}

	public function test_create_falls_back_to_default_template(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'og-cf7/create-form' )->execute( array( 'title' => 'Default form' ) );

		$this->assertIsArray( $result );
		$properties = (array) $result['properties'];
		// CF7's default template has a form body and a populated mail block.
		$this->assertNotEmpty( $properties['form']['content'] );
		$this->assertNotEmpty( $result['mail_recipient'] );
	}

	public function test_create_surfaces_chosen_mail_recipient(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'og-cf7/create-form' )->execute(
			array(
				'title' => 'Routed form',
				'mail'  => array(
					'recipient'          => 'sales@example.org',
					'additional_headers' => 'Bcc: archive@example.org',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'sales@example.org', $result['mail_recipient'] );
		$this->assertStringContainsString( 'Bcc: archive@example.org', $result['mail_additional_headers'] );
	}

	public function test_logged_out_user_is_denied(): void {
		wp_set_current_user( 0 );

		$result = wp_get_ability( 'og-cf7/create-form' )->execute( array( 'title' => 'Nope' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}

	public function test_subscriber_is_denied(): void {
		$this->actingAs( 'subscriber' );

		$result = wp_get_ability( 'og-cf7/create-form' )->execute( array( 'title' => 'Nope' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );

		// Nothing was created.
		$forms = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$this->assertSame( array(), $forms );
	}
}
