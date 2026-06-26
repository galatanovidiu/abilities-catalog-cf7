=== Abilities Catalog — Contact Form 7 ===
Contributors: ovidiu-galatan
Tags: abilities-api, contact-form-7, ai, mcp, agents
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/license/mit

Registers six Contact Form 7 operations on the WordPress Abilities API, so any Abilities API consumer can list, read, create, update, duplicate, and delete forms.

== Description ==

This add-on registers six Contact Form 7 (CF7) operations on the WordPress
Abilities API, so any Abilities API consumer can list, read, create, update,
duplicate, and delete CF7 forms, each gated by CF7's own capabilities. When the
[Abilities Catalog](https://github.com/galatanovidiu/abilities-catalog) MCP
server is active, agents reach these abilities through the same search-based
surface as core abilities — no separate server per plugin.

It works standalone on the core Abilities API. Abilities Catalog is optional. When
the catalog is present and its MCP server is on, the CF7 abilities become
discoverable through the catalog's search server, and the add-on also contributes
one curated `contact-form-7` domain tool and a `set-up-contact-form` knowledge
concept. It edits no catalog files: it plugs in through the catalog's public
filters.

Contact Form 7 is a hard runtime dependency. While CF7 is inactive the `og-cf7/*`
abilities do not register at all — they are absent from the Abilities API, not
registered-and-denying — and neither the domain tool nor the knowledge concept
appears.

**What it registers**

Six abilities under the `og-cf7` category: `og-cf7/list-forms` and
`og-cf7/get-form` (read), `og-cf7/create-form`, `og-cf7/update-form`, and
`og-cf7/duplicate-form` (write), and `og-cf7/delete-form` (a destructive,
permanent delete). Each ability wraps Contact Form 7's own REST route — or, where
CF7 exposes no route (duplicate, and a form's shortcode/hash), the live form
object — declares an input and output schema, points at a category, enforces a
server-side `permission_callback`, and carries risk annotations. Of the six
abilities, two are read-only and one is destructive; the remaining three are
non-destructive writes.

**How agents reach these abilities**

When the catalog's MCP server is enabled, the CF7 abilities are indexed alongside
core abilities. An agent searches by task, describes one ability, and executes it
through one search endpoint. Discovery cost tracks the result set, not the total
catalog size. This is the recommended surface for new clients.

The add-on also contributes one curated `contact-form-7` domain tool on the
catalog's domain server through the `abilities_catalog_mcp_domains` filter (list,
read, create, update, duplicate, and delete forms, and obtain a form's shortcode),
and a `set-up-contact-form` OKF knowledge concept through the
`abilities_catalog_mcp_knowledge` filter (a recipe that chains finding or creating
a form into placing its shortcode on a page).

**Safety**

Two layers gate every ability, the same as core. The capability is the hard guard:
every ability's `permission_callback` calls `current_user_can()` with the matching
CF7 capability on every execution. The MCP exposure gate adds a second layer: when
the catalog's MCP server is on, every ability starts disabled for MCP execution
until an administrator enables it at Settings → MCP Server.

An MCP client acts as the authenticated WordPress user. Enabling write or
destructive CF7 abilities lets the client create, modify, duplicate, or
permanently delete real forms. A form's mail settings also control where
submissions are sent. Back up before enabling high-risk abilities, and enable only
what the agent needs.

**Where this is going**

These abilities are not meant to replace Contact Form 7's own. They are a working
bridge until CF7 ships official abilities on the Abilities API. As official
abilities appear, the duplicated ones in this add-on will be removed to make room
for the CF7-owned definitions.

== Installation ==

1. Install and activate Contact Form 7.
2. Install and activate this plugin. The `og-cf7/*` abilities register automatically — no build step.
3. Optional: install Abilities Catalog and enable its MCP server. The CF7
   abilities then appear through the catalog's search server, and as a curated
   `contact-form-7` domain tool.

== Changelog ==

= 0.1.0 =
* Initial release: six Contact Form 7 form abilities (list, read, create, update,
  duplicate, delete) on the WordPress Abilities API, with the add-on infrastructure
  (contracts, registry, the CF7 dependency facade, the category catalog) and the
  optional MCP integration (search server indexing plus a curated `contact-form-7`
  domain tool and the `set-up-contact-form` OKF knowledge concept).
