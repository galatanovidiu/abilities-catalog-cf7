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
 * it registers a `cf7` domain tool — its description and the `cf7/*` abilities it
 * owns, in one place — and adds the {@see SetUpContactForm} recipe to the
 * cross-cutting `skills` tool.
 *
 * Every contribution is gated on {@see Cf7Plugin::isActive()} at filter-run time
 * (filters fire while the server boots, after plugins load), so when Contact Form 7
 * is inactive the `cf7/*` abilities do not register and no empty `cf7` tool or
 * dangling skill appears. The filters are catalog hooks: when the catalog or its
 * MCP server is absent, nothing applies them and the add-on stays inert here.
 *
 * @since 0.1.0
 */
final class Integration {

	/**
	 * The exact `cf7/*` ability names the `cf7` domain tool owns, in tool order.
	 *
	 * @var list<string>
	 */
	private const CF7_ABILITIES = array(
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
		add_filter( 'abilities_catalog_mcp_domains', array( self::class, 'contributeDomain' ) );
		add_filter( 'abilities_catalog_mcp_skills', array( self::class, 'contributeSkill' ) );
	}

	/**
	 * Registers the `cf7` domain tool — its description and the abilities it owns.
	 *
	 * One call defines the whole tool: the server builds a `cf7` tool, routes the
	 * `cf7/*` abilities to it, and uses the description as the tool's routing blurb.
	 * Skipped when CF7 is inactive (the abilities are not registered then, so an empty
	 * tool would only confuse an agent).
	 *
	 * @param array<string, array{description: string, abilities: list<string>}> $domains Add-on domain slug => its tool descriptor.
	 * @return array<string, array{description: string, abilities: list<string>}> The map including the `cf7` tool.
	 */
	public static function contributeDomain( array $domains ): array {
		if ( ! Cf7Plugin::isActive() ) {
			return $domains;
		}

		$domains['cf7'] = array(
			'description' => __( 'Manage Contact Form 7 contact forms — list, read, create, update, duplicate and delete forms, and obtain a form\'s shortcode for embedding.', 'abilities-catalog-cf7' ),
			'abilities'   => self::CF7_ABILITIES,
		);

		return $domains;
	}

	/**
	 * Adds the "set up a contact form" recipe to the `skills` tool.
	 *
	 * The recipe chains the `cf7` domain into `content` (find/create a form, then
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
