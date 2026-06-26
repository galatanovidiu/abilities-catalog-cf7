# Abilities Catalog — Contact Form 7

**This add-on registers six Contact Form 7 operations on the WordPress
Abilities API, so any Abilities API consumer can list, read, create, update,
duplicate, and delete CF7 forms. When the
[Abilities Catalog](https://github.com/galatanovidiu/abilities-catalog) MCP
server is active, agents reach these abilities through the same search-based
surface as core abilities — no separate server per plugin.**

It works standalone on the core Abilities API. Abilities Catalog is optional.
When the catalog is present and its MCP server is on, the CF7 abilities become
discoverable through the catalog's search server, and the add-on also
contributes one curated `contact-form-7` domain tool and a `set-up-contact-form`
knowledge concept. It edits no catalog files: it plugs in through the catalog's
public filters.

Contact Form 7 is a hard runtime dependency. While CF7 is inactive the
`og-cf7/*` abilities do not register at all — they are absent from the Abilities
API, not registered-and-denying — and neither the domain tool nor the knowledge
concept appears.

## Requirements

- WordPress 6.9 or later, with the Abilities API in core.
- PHP 8.1 or later.
- Contact Form 7, active.
- Optional: Abilities Catalog, for the MCP surface.

## Installation

1. Install and activate Contact Form 7.
2. Install and activate this plugin. [Download the latest plugin
   ZIP](https://github.com/galatanovidiu/abilities-catalog-cf7/releases/latest/download/abilities-catalog-cf7.zip)
   and install it via **Plugins → Add New → Upload Plugin**. The `og-cf7/*`
   abilities register automatically — no build step.
3. Optional: install Abilities Catalog and enable its MCP server. The CF7
   abilities then appear through the catalog's search server, and as a curated
   domain tool.

## What it registers

Six abilities, all under the `og-cf7` category:

| Ability | Type | What it does |
|---|---|---|
| `og-cf7/list-forms` | read | list and search forms |
| `og-cf7/get-form` | read | read one form's full configuration and shortcode |
| `og-cf7/create-form` | write | create a form |
| `og-cf7/update-form` | write | update a form |
| `og-cf7/duplicate-form` | write | copy a form |
| `og-cf7/delete-form` | destructive | permanently delete a form |

Each ability wraps Contact Form 7's own REST route — or, where CF7 exposes no
route (duplicate, and a form's shortcode/hash), the live form object — declares
an input and output schema, points at the `og-cf7` category, enforces a
server-side `permission_callback`, and carries risk annotations. Of the six
abilities, two are read-only and one is destructive (a permanent delete); the
remaining three are non-destructive writes.

## How agents reach these abilities

The add-on registers on the same Abilities API as the core catalog, so it rides
the catalog's MCP surfaces. There is no separate server per plugin.

### Search server (primary)

When the catalog's MCP server is enabled, the CF7 abilities are indexed
alongside core abilities. An agent searches by task, describes one ability, and
executes it through the one search endpoint:

```text
/wp-json/abilities-catalog/v1/mcp-search
```

Discovery cost tracks the result set, not the total catalog size. This is the
recommended surface for new clients.

### Curated domain tool

On the catalog's curated domain server, the add-on also contributes one CF7
domain tool through the `abilities_catalog_mcp_domains` filter:

- `contact-form-7` — list, read, create, update, duplicate, and delete forms,
  and obtain a form's shortcode for embedding.

The tool supports `list`, `describe`, and `execute`. The add-on additionally
contributes a `set-up-contact-form` OKF knowledge concept through the
`abilities_catalog_mcp_knowledge` filter — a recipe that chains finding or
creating a form into placing its shortcode on a page.

## Safety

Two layers gate every ability, the same as core:

- **Capability is the hard guard.** Every ability's `permission_callback` calls
  `current_user_can()` with the matching CF7 capability — `wpcf7_read_contact_forms`
  to list, `wpcf7_edit_contact_form(s)` to read/create/update/duplicate, and
  `wpcf7_delete_contact_form` to delete. This runs on every execution,
  independent of any MCP client.
- **MCP exposure gate.** When the catalog's MCP server is on, every ability —
  including these — starts disabled for MCP execution. Discovery can show it;
  execution is refused until an administrator enables it at
  **Settings → MCP Server**.

> [!WARNING]
> An MCP client acts as the authenticated WordPress user. Enabling write or
> destructive CF7 abilities lets the client create, modify, duplicate, or
> **permanently delete** real forms. A form's mail settings also control where
> submissions are sent — `og-cf7/create-form` and `og-cf7/update-form` echo the
> resulting `mail_recipient` and `mail_additional_headers` because CF7 does not
> validate them (a `Bcc:` header silently copies submissions). Review those
> results, back up before enabling high-risk abilities, and enable only what the
> agent needs.

## Standalone and decoupled

This is a separate plugin, not part of the core catalog. It works on the bare
Abilities API with no catalog present: the `og-cf7/*` abilities still register
and run for any consumer. The MCP integration is filter-based and inert when the
catalog is absent — no catalog file is edited.

See
[Building an Abilities Catalog add-on](https://github.com/galatanovidiu/abilities-catalog/blob/main/docs/building-add-ons.md)
for the extension pattern.

## Where this is going

> [!NOTE]
> These abilities are not meant to replace Contact Form 7's own. They are a
> working bridge until CF7 ships official abilities on the Abilities API. As
> official abilities appear, the duplicated ones in this add-on will be removed
> to make room for the CF7-owned definitions.

## Development

Static checks run on the host (need `composer install`):

```bash
composer lint      # phpcs (VIP + Slevomat, .phpcs.xml.dist)
composer format    # phpcbf — auto-fix
composer phpstan   # phpstan analyse
```

Tests run inside wp-env (Docker WordPress with CF7 installed), not on the host:

```bash
npm run wp-env start       # bring up the container
npm run test:php:setup     # composer install inside the container (run once)
npm run test:php           # full PHPUnit suite
npm run test:php -- --filter CreateFormTest   # single test
```

See [AGENTS.md](AGENTS.md) for architecture, conventions, and how to add an
ability.

## License

MIT — see [LICENSE](LICENSE).
