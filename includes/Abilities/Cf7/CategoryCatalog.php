<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Abilities\Cf7;

use GalatanOvidiu\AbilitiesCatalogCf7\Contracts\CategoryProvider;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Category catalog for the Contact Form 7 ability group.
 *
 * The {@see \GalatanOvidiu\AbilitiesCatalogCf7\Registry} discovers this provider
 * alongside the abilities and registers its categories on
 * `wp_abilities_api_categories_init`. Every Cf7 ability references the `og-cf7`
 * category through `args()['category']`.
 *
 * The group's abilities only register when CF7 is active (they are
 * {@see \GalatanOvidiu\AbilitiesCatalogCf7\Contracts\ConditionalAbility}s), so the
 * category gates on the same condition — when CF7 is off there are no abilities to
 * categorize and the catalog leaves no CF7 footprint. The check is safe here
 * because categories register after plugins have loaded, never at file load.
 *
 * @since 0.3.0
 */
final class CategoryCatalog implements CategoryProvider {

	/**
	 * {@inheritDoc}
	 */
	public function categories(): array {
		if ( ! Cf7Plugin::isActive() ) {
			return array();
		}

		return array(
			'og-cf7' => array(
				'slug'        => 'og-cf7',
				'label'       => __( 'Contact Form 7', 'abilities-catalog-cf7' ),
				'description' => __( 'Abilities that read, create, update, duplicate, and delete Contact Form 7 contact forms, and obtain a form\'s shortcode for embedding.', 'abilities-catalog-cf7' ),
			),
		);
	}
}
