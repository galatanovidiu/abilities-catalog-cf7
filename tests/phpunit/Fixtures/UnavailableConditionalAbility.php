<?php
/**
 * Test fixture: a ConditionalAbility whose dependency is absent.
 *
 * @package AbilitiesCatalogCf7\Tests
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Tests\Fixtures;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility;

/**
 * A read ability that reports its runtime dependency as unavailable. The Registry
 * must NOT register it (nor contribute it to the dangerous/screen-link filters).
 * Lives under tests/, so the Registry's disk scan never discovers it; it is
 * injected directly in the guard test.
 */
final class UnavailableConditionalAbility implements ConditionalAbility {

	public function name(): string {
		return 'catalog-test/unavailable-conditional';
	}

	public function isAvailable(): bool {
		return false;
	}

	public function args(): array {
		return array(
			'label'               => 'Unavailable Conditional',
			'description'         => 'A conditional ability whose dependency is absent.',
			'category'            => 'settings',
			'input_schema'        => array( 'type' => 'object' ),
			'output_schema'       => array( 'type' => 'object' ),
			'execute_callback'    => static fn() => array(),
			'permission_callback' => static fn() => true,
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);
	}
}
