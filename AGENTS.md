# Jyavani People Repository Contract

Jyavani People is a general-purpose directory and professional profile plugin
for Jyavani CMS. The product must remain useful to universities, companies,
communities, agencies, and independent portfolio sites without assuming any
specific organization.

## Confidential Case Study

Before planning or implementing work, read `guide.md` when it exists. It is an
ignored local case study for a confidential office deployment and provides
real requirements that should exercise the general architecture.

Treat `guide.md` as confidential input, never as product content:

- Never commit, stage, upload, quote, or summarize identifying details from it.
- Never copy its organization name, domains, people, roles, taxonomies, assets,
  credentials, or deployment paths into tracked files or GitHub metadata.
- Convert each case-specific request into a generic capability, configuration,
  taxonomy, template override, hook, or documented extension point.
- Keep downstream data, importers, theme overrides, and acceptance tooling in
  the consuming project.
- Before every commit, verify that `guide.md` is ignored and that tracked files
  contain no confidential downstream branding.

If `guide.md` conflicts with this file, this general product contract wins.

## Product Boundary

The plugin owns:

- People directory administration and publishing workflows.
- Public list and single-profile routes.
- Structured professional profile fields and validated external links.
- Groups, roles, locations, expertise, and other reusable taxonomies.
- Typed profile entries such as teaching, research, publications, service,
  certifications, industry experience, and achievements.
- Year-aware filtering, ordering, pagination, and search.
- Neutral default templates, styles, metadata, structured data, and sitemaps.
- Generic hooks, permissions, import/export contracts, and optional translation
  adapters.

The plugin does not own:

- Organization-specific people, titles, categories, wording, or seed data.
- A consuming site's visual identity, proprietary assets, or theme code.
- CMS Core routing, authorization, updater, or schema behavior.
- Content Translation internals or hard dependencies on another plugin.
- Deployment credentials, environment files, production data, or backups.

## Architecture

Use native PHP and existing Jyavani conventions. Do not introduce a framework,
Composer dependency, or build system unless a concrete product requirement
cannot be met with the platform contract.

### Routes

- Register public routes through Core's deterministic frontend route API.
- Use configurable, normalized base paths with a neutral default such as
  `people`.
- Provide exact list and single-profile route definitions without capturing
  unrelated Core or plugin routes.
- Respect request methods, route collision checks, canonical URLs, draft
  visibility, and cosmetic 404 behavior.

### Storage

Use plugin-owned append-only migrations with stable positive four-digit
sequences. Prefer structured tables over an unrestricted EAV model:

- `people_profiles`: stable identity, slug, source locale, publication state,
  image reference, display order, and non-translatable identifiers.
- `people_profile_texts`: locale-aware name presentation, headline, position,
  summary, and biography.
- `people_terms` and `people_profile_terms`: reusable typed taxonomies.
- `people_links`: validated typed or custom professional links.
- `people_entries`: typed, dated or year-aware professional records.
- `people_entry_texts`: locale-aware entry titles, summaries, and descriptions.

Actual table names may use a stable plugin prefix. Foreign keys, indexes,
uniqueness, cleanup behavior, and migration idempotence must be explicit.
Applied migration files are immutable.

### Administration And Authorization

- Register all dashboard pages through `plugin.json`.
- Use dynamic plugin permissions rather than direct legacy-role checks.
- Separate view, create, edit, publish, taxonomy, settings, import, and delete
  capabilities where their risk differs.
- Reauthorize mutations under transactions and row locks.
- Require POST, CSRF protection, bounded scalar input, optimistic state for
  concurrent edits, and safe return targets.
- Preserve drafts and fail closed on incomplete schema or authorization state.

### Presentation

- Ship accessible, responsive, brand-neutral list and single templates.
- Cards may expose photo, display name, professional title, selected links, and
  configurable badges.
- Single profiles may expose quick links and typed tabs with renderer-specific
  layouts, including year filters for chronological entries.
- Avoid copying third-party markup, CSS, JavaScript, icons, text, or assets.
  Reference sites provide requirements only.
- Allow active-theme template overrides and filters without requiring Core or
  plugin source patches.
- Escape text by default, allow HTML only in narrowly documented sanitized
  fields, and validate all URLs and media references.

### Translation

- The plugin must work correctly in one source locale without Content
  Translation installed.
- Keep identity, credentials, IDs, media, dates, and professional URLs separate
  from translatable presentation fields.
- Translation support must be optional and fail safely when unavailable.
- Integrate through a generic resource adapter contract. Content Translation
  must not hardcode this plugin, and this plugin must not modify Content
  Translation internals.
- Locale routes, hreflang, canonical URLs, sitemaps, fallback policy, and
  publication state must remain deterministic.
- A source-only release may precede multilingual UI, but schema and APIs must
  not require destructive migration when translations are added.

### Extensibility

Provide bounded filters/actions for:

- Profile field and quick-link type registration.
- Taxonomy registration and list-filter configuration.
- Entry/tab type registration and rendering.
- List queries, card data, and template selection.
- Single-profile data, metadata, structured data, and sitemap entries.
- Import/export mapping and generic translation resources.

Validate extension output and fail closed for malformed security-sensitive
state. Do not let extensions weaken authorization or route ownership.

## Privacy And Security

- Publish only consented professional information.
- Support per-field or per-link visibility where needed.
- Never store credentials or private contact data in tracked fixtures.
- Use fictional neutral people in tests and demos.
- Prevent slug ambiguity, unsafe protocols, stored XSS, unrestricted HTML,
  path traversal, unbounded queries, and unauthorized enumeration of drafts.
- Emit Schema.org `Person` data only from public validated fields.
- Treat email exposure, external identifiers, analytics, and sharing features
  as explicit configuration decisions.

## Repository Neutrality

Tracked source, documentation, tests, fixtures, commit messages, branches,
releases, issues, and pull requests must not identify a private client,
employer, office project, or downstream domain. Use terms such as `example
organization`, `sample university`, and `downstream consumer`.

The repository may be private during development, but all tracked history must
already be suitable for a future public release.

## Verification

Every feature must include focused contract tests. Before declaring work ready:

1. Lint every PHP file.
2. Run every plugin test.
3. Verify install, enable, disable, update, keep-data uninstall, and complete
   uninstall behavior when relevant.
4. Verify routes, permissions, CSRF, transactions, escaping, responsive output,
   translation-disabled behavior, and malformed extension output.
5. Verify tracked files and Git metadata contain no confidential case-study
   identity.
6. Test against the supported Jyavani Core contract before downstream rollout.

Do not commit or push unless the requested implementation and verification are
complete.
