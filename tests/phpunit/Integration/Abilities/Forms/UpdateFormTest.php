<?php
/**
 * Integration tests for the cf7/update-form ability.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use WP_Error;

/**
 * Exercises cf7/update-form: persistence, the previous/resulting mail transparency,
 * and the missing-form 404.
 */
final class UpdateFormTest extends Cf7FormsTestCase {

	public function test_ability_is_registered(): void {
		$ability = wp_get_ability( 'cf7/update-form' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'cf7/update-form', $ability->get_name() );
	}

	public function test_update_persists_new_title(): void {
		$this->actingAs( 'administrator' );
		$id = $this->seedForm( array( 'title' => 'Before' ) );

		$result = wp_get_ability( 'cf7/update-form' )->execute(
			array(
				'id'    => $id,
				'title' => 'After',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'After', $result['title'] );
		$this->assertSame( 'After', get_post( $id )->post_title );
	}

	public function test_update_reports_previous_and_resulting_mail_recipient(): void {
		$this->actingAs( 'administrator' );
		$id = $this->seedForm(
			array(
				'mail' => array(
					'recipient' => 'owner@example.org',
					'body'      => '[your-message]',
				),
			)
		);

		$result = wp_get_ability( 'cf7/update-form' )->execute(
			array(
				'id'   => $id,
				'mail' => array(
					'recipient' => 'attacker@evil.example',
					'body'      => '[your-message]',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame(
			array( 'id', 'slug', 'title', 'locale', 'properties', 'mail_recipient', 'mail_additional_headers', 'config_errors', 'shortcode', 'hash', 'edit_link', 'previous_mail_recipient', 'previous_mail_additional_headers' ),
			array_keys( $result )
		);
		$this->assertSame( 'owner@example.org', $result['previous_mail_recipient'] );
		$this->assertSame( 'attacker@evil.example', $result['mail_recipient'] );

		// The new routing is actually persisted.
		$mail = wpcf7_contact_form( $id )->prop( 'mail' );
		$this->assertSame( 'attacker@evil.example', $mail['recipient'] );
	}

	public function test_missing_form_returns_404_not_permission_error(): void {
		$this->actingAs( 'administrator' );

		$result = wp_get_ability( 'cf7/update-form' )->execute(
			array(
				'id'    => 99999999,
				'title' => 'Ghost',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wpcf7_not_found', $result->get_error_code() );
		$this->assertSame( 404, $result->get_error_data()['status'] );
	}

	public function test_subscriber_is_denied_and_form_unchanged(): void {
		$id = $this->seedForm( array( 'title' => 'Untouched' ) );
		$this->actingAs( 'subscriber' );

		$result = wp_get_ability( 'cf7/update-form' )->execute(
			array(
				'id'    => $id,
				'title' => 'Hacked',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( 'Untouched', get_post( $id )->post_title );
	}
}
