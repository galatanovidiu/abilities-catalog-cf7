<?php
/**
 * Base test case for the Contact Form 7 ability group.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Abilities\Forms;

use GalatanOvidiu\AbilitiesCatalogCf7\Tests\TestCase;

/**
 * Skips the whole CF7 suite when Contact Form 7 is not active (its abilities then
 * do not register), and seeds real contact forms through CF7's own save path so
 * the abilities can be exercised end-to-end.
 */
abstract class Cf7FormsTestCase extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wpcf7_contact_form' ) ) {
			$this->markTestSkipped( 'Contact Form 7 is not active.' );
		}
	}

	/**
	 * Creates and persists a contact form through CF7's own save path.
	 *
	 * @param array<string,mixed> $args Property overrides merged over a sane default.
	 * @return int The new form's post ID.
	 */
	protected function seedForm( array $args = array() ): int {
		$defaults = array(
			'title' => 'Seeded form',
			'mail'  => array(
				'recipient'          => 'owner@example.org',
				'subject'            => 'New submission',
				'sender'             => 'Site <site@example.org>',
				'body'               => '[your-message]',
				'additional_headers' => 'Reply-To: [your-email]',
			),
		);

		$form = wpcf7_save_contact_form( array_merge( $defaults, $args ), 'save' );

		return $form ? (int) $form->id() : 0;
	}
}
