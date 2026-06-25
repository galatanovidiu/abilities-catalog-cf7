<?php
/**
 * Tests the MCP integration filters.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Integration\Mcp;

use GalatanOvidiu\AbilitiesCatalog\Mcp\Knowledge\KnowledgeBundle;
use GalatanOvidiu\AbilitiesCatalogCf7\Mcp\Integration;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;
use GalatanOvidiu\AbilitiesCatalogCf7\Tests\TestCase;

/**
 * The integration registers a `cf7` domain tool and contributes a knowledge bundle to
 * the catalog's MCP server through public filters. These assert the descriptor shape
 * and — the point of the guard — that every ability the tool lists is actually
 * registered, so the hand-kept name list cannot drift away from the ability classes.
 *
 * The knowledge filter is the cross-repo contract: it now carries scanned
 * {@see KnowledgeBundle} objects, a catalog class, so these tests only pass with the
 * renamed catalog loaded (the test bootstrap requires it). That is the real guard for
 * the re-contracted filter — a direct method call alone would pass even on a mismatch.
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
	 * contributeKnowledge appends a scanned `cf7` bundle carrying the recipe.
	 *
	 * @return void
	 */
	public function test_contribute_knowledge_adds_the_bundle(): void {
		$bundles = Integration::contributeKnowledge( array() );

		$this->assertCount( 1, $bundles );
		$bundle = $bundles[0];
		$this->assertInstanceOf( KnowledgeBundle::class, $bundle );
		$this->assertSame( 'cf7', $bundle->slug() );

		$concept = $bundle->concept( 'set-up-contact-form' );
		$this->assertNotNull( $concept, 'The cf7 bundle must carry the set-up-contact-form concept.' );
		$this->assertSame( 'cf7/set-up-contact-form', $concept->uri() );
		$this->assertSame( 'Skill', $concept->type() );
		$this->assertNotEmpty( $concept->body() );
	}
}
