<?php

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Mcp;

use GalatanOvidiu\AbilitiesCatalogCf7\Mcp\Skills\SetUpContactForm;
use GalatanOvidiu\AbilitiesCatalogCf7\Support\Cf7Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugs the CF7 form abilities into the Abilities Catalog MCP server.
 *
 * The catalog exposes one curated MCP tool per domain, not one per ability, and is
 * extensible through public filters. This class is the add-on's whole MCP surface:
 * it contributes a `forms` domain (so the server builds a `forms` tool over the
 * `cf7/*` abilities), a human description for that domain, and the
 * {@see SetUpContactForm} recipe to the cross-cutting `skills` tool.
 *
 * Every contribution is gated on {@see Cf7Plugin::isActive()} at filter-run time
 * (filters fire while the server boots, after plugins load), so when Contact Form 7
 * is inactive the `cf7/*` abilities do not register and no empty `forms` tool or
 * dangling skill appears. The filters are catalog hooks: when the catalog or its
 * MCP server is absent, nothing applies them and the add-on stays inert here.
 *
 * @since 0.1.0
 */
final class Integration {

	/**
	 * The exact `cf7/*` ability names the `forms` domain tool owns, in tool order.
	 *
	 * The catalog's curated prefix rules are not filterable (core's taxonomy), so an
	 * add-on places its abilities by exact name through the
	 * `abilities_catalog_mcp_domain_map` filter.
	 *
	 * @var list<string>
	 */
	private const FORMS_ABILITIES = array(
		'cf7/list-forms',
		'cf7/get-form',
		'cf7/create-form',
		'cf7/update-form',
		'cf7/duplicate-form',
		'cf7/delete-form',
	);

	/**
	 * Registers the MCP filter hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'abilities_catalog_mcp_domain_map', array( self::class, 'contributeDomain' ) );
		add_filter( 'abilities_catalog_mcp_domain_descriptions', array( self::class, 'contributeDescription' ) );
		add_filter( 'abilities_catalog_mcp_skills', array( self::class, 'contributeSkill' ) );
	}

	/**
	 * Places the `cf7/*` abilities into a `forms` domain.
	 *
	 * Preserves the placements already present and adds the `forms` key, so the
	 * server opens a new `forms` tool. Skipped when CF7 is inactive (the abilities
	 * are not registered then, so an empty tool would only confuse an agent).
	 *
	 * @param array<string, list<string>> $map Domain slug => exact ability names placed in that domain.
	 * @return array<string, list<string>> The map including the `forms` placements.
	 */
	public static function contributeDomain( array $map ): array {
		if ( ! Cf7Plugin::isActive() ) {
			return $map;
		}

		$map['forms'] = self::FORMS_ABILITIES;

		return $map;
	}

	/**
	 * Supplies the human capability blurb for the `forms` domain tool.
	 *
	 * Without this, an add-on's domain falls back to the catalog's generic
	 * "another plugin contributed" description. Skipped when CF7 is inactive.
	 *
	 * @param array<string, string> $descriptions Domain slug => capability blurb.
	 * @return array<string, string> The map including the `forms` blurb.
	 */
	public static function contributeDescription( array $descriptions ): array {
		if ( ! Cf7Plugin::isActive() ) {
			return $descriptions;
		}

		$descriptions['forms'] = __( 'Manage Contact Form 7 contact forms — list, read, create, update, duplicate and delete forms, and obtain a form\'s shortcode for embedding.', 'abilities-catalog-cf7' );

		return $descriptions;
	}

	/**
	 * Adds the "set up a contact form" recipe to the `skills` tool.
	 *
	 * The recipe chains the `forms` domain into `content` (find/create a form, then
	 * embed its shortcode on a page). Its body stays a callable so it costs no
	 * context until a `skills` get resolves it. Skipped when CF7 is inactive.
	 *
	 * @param array<string, array{title:string, when_to_use:string, body:string|callable}> $skills Skill id => descriptor.
	 * @return array<string, array{title:string, when_to_use:string, body:string|callable}> The map including the CF7 recipe.
	 */
	public static function contributeSkill( array $skills ): array {
		if ( ! Cf7Plugin::isActive() ) {
			return $skills;
		}

		$skills[ SetUpContactForm::ID ] = array(
			'title'       => SetUpContactForm::title(),
			'when_to_use' => SetUpContactForm::whenToUse(),
			'body'        => array( SetUpContactForm::class, 'body' ),
		);

		return $skills;
	}
}
