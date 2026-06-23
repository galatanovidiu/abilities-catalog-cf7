<?php
/**
 * Tests the MCP integration filters.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Mcp;

use GalatanOvidiu\AbilitiesCatalogCf7\Mcp\Integration;
use GalatanOvidiu\AbilitiesCatalogCf7\Mcp\Skills\SetUpContactForm;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Tests\TestCase;

/**
 * The integration registers a `cf7` domain tool and a skill with the catalog's
 * MCP server through public filters. These assert the descriptor shape and — the
 * point of the guard — that every ability the tool lists is actually registered, so
 * the hand-kept name list cannot drift away from the ability classes.
 */
final class IntegrationTest extends TestCase {

	/**
	 * The abilities only register when CF7 is active, so skip otherwise.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! Cf7Plugin::isActive() ) {
			$this->markTestSkipped( 'Contact Form 7 is not active.' );
		}
	}

	/**
	 * contributeDomain registers a described `cf7` tool with its abilities.
	 *
	 * @return void
	 */
	public function test_contribute_domain_registers_a_described_cf7_tool(): void {
		$domains = Integration::contributeDomain( array() );

		$this->assertArrayHasKey( 'cf7', $domains );
		$this->assertIsString( $domains['cf7']['description'] );
		$this->assertNotEmpty( $domains['cf7']['description'] );
		$this->assertNotEmpty( $domains['cf7']['abilities'] );
	}

	/**
	 * Every ability the `cf7` tool lists is a registered ability (no name drift).
	 *
	 * @return void
	 */
	public function test_every_listed_ability_is_registered(): void {
		$domains = Integration::contributeDomain( array() );

		foreach ( $domains['cf7']['abilities'] as $name ) {
			$this->assertTrue(
				wp_has_ability( $name ),
				sprintf( 'The cf7 tool lists "%s", which is not a registered ability.', $name )
			);
		}
	}

	/**
	 * contributeSkill adds the set-up-contact-form recipe.
	 *
	 * @return void
	 */
	public function test_contribute_skill_adds_the_recipe(): void {
		$skills = Integration::contributeSkill( array() );

		$this->assertArrayHasKey( SetUpContactForm::ID, $skills );
		$this->assertSame( SetUpContactForm::title(), $skills[ SetUpContactForm::ID ]['title'] );
	}
}
