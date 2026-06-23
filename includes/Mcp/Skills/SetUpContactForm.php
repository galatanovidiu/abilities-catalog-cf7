<?php
/**
 * The set-up-contact-form skill: place a Contact Form 7 form on a page.
 *
 * @package AbilitiesCatalogCf7
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7\Mcp\Skills;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A cross-cutting recipe that chains the Contact Form 7 group into the content
 * group to do the task CF7 users ask for most: "put a contact form on a page".
 *
 * The recipe is static procedural text that references the read abilities for live
 * data — it never embeds form IDs or shortcodes, which differ per site.
 * {@see \GalatanOvidiu\AbilitiesCatalogCf7\Mcp\SkillsRegistry} registers it as a
 * callable so the body is built only when a `get` asks for it, and only when CF7
 * is active.
 *
 * @since 0.3.0
 */
final class SetUpContactForm {

	/**
	 * The stable skill id, used by the skills tool's `get` action.
	 */
	public const ID = 'set-up-contact-form';

	/**
	 * The short human title shown by `list`.
	 *
	 * @return string The skill title.
	 */
	public static function title(): string {
		return __( 'Put a contact form on a page', 'abilities-catalog-cf7' );
	}

	/**
	 * The one-line routing hint: when an agent should reach for this skill.
	 *
	 * @return string The when-to-use hint.
	 */
	public static function whenToUse(): string {
		return __( 'Before adding a Contact Form 7 contact form to a page or post — to find or create the form and embed its shortcode.', 'abilities-catalog-cf7' );
	}

	/**
	 * The full recipe body, built only when `get` asks for it.
	 *
	 * @return string The recipe body.
	 */
	public static function body(): string {
		return __(
			'Recipe: put a Contact Form 7 contact form on a page.

Goal: render a CF7 contact form on a page or post by embedding its shortcode. CF7 forms are NOT regular content: they live behind the "forms" tool, not the "content" tool, and a form is placed by dropping its shortcode into page/post content.

STEP 1 - FIND OR CREATE THE FORM (through the "forms" tool)
- forms execute cf7/list-forms: lists existing forms, each with its "shortcode" field. If a suitable form already exists, take its shortcode and skip to Step 2.
- If none fits, forms execute cf7/create-form with a "title" (and optional form/mail settings) to make one. The result includes the new form\'s "shortcode". IMPORTANT: the mail.recipient you set is where every submission is emailed and CF7 does not validate it; confirm it is correct.

STEP 2 - PLACE THE SHORTCODE (through the "content" tool)
The shortcode looks like [contact-form-7 id="abc1234" title="Contact"]. Put it in the content of a page or post:
- New page: content execute content/create-page with content set to the shortcode (optionally wrapped in a paragraph block, e.g. <!-- wp:paragraph --><p>[contact-form-7 id="abc1234" title="Contact"]</p><!-- /wp:paragraph -->).
- Existing page/post: content execute content/update-page (or content/update-post) to add the shortcode to the body.

STEP 3 - CONFIRM
Surface the page\'s edit_link (from the content ability) and the form\'s edit_link (from the forms ability) so a human can review both. The shortcode renders the live form on the front end; editing the form later (forms execute cf7/update-form) updates it everywhere it is embedded.',
			'abilities-catalog-cf7'
		);
	}
}
