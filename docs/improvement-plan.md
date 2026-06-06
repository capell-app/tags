# Tags — Improvement & Growth Plan

> Package: capell-app/tags · Kind: package · Tier: free · Product group: Capell Foundation · Bundle: foundation · Status: Draft

## 1. Snapshot

Tags is a foundation package that provides a shared, site-scoped, translatable taxonomy layer for Capell content. It contributes one Filament admin resource (`TagResource` with list/create/edit pages and a `PagesRelationManager`), a reusable abstract `SpatieTagsInput` subclass (`src/Filament/Components/Forms/TagsInput.php`), a `HasTags` model concern (`src/Models/Concerns/HasTags.php`), `Tag`/`Taggable` models, one install Action + command (`capell:tags-install`), and a single migration that augments Spatie's `tags`/`taggables` tables (`database/migrations/2026_05_10_190872_01_alter_tags_table.php`). It is built on `spatie/laravel-tags` + `filament/spatie-laravel-tags-plugin`; it owns no frontend surface (`surfaces: ["admin","console"]`). Its sole real-world consumer in this monorepo is Blog — Blog subclasses `TagsInput`, renders tag landing pages via `Tag::getUrl()`, builds the tags sitemap, and drives all public taxonomy UX. `capell.json` now describes a shared multilingual, multi-site taxonomy and lists the shipped admin index, create/edit, relation-manager, and TagsInput screenshots.

## Completed Improvement Slices

- **2026-06-03:** Rewrote composer/marketplace copy, reconciled manifest screenshots with shipped assets, and replaced the version-only health check with real diagnostics.
- **2026-06-04:** Bound admin tag type selection to `TagTypeEnum`, updated factories to generate only enum-backed types, declared taxonomy capabilities, and added a `tags` cache tag to the manifest.

## 2. Improvements (existing functionality)

Prioritized.

1. **Tag `type` is now enum-backed.** — `TagForm::typeSelect()` uses an enum-backed `Select`, `TagTypeEnum` implements labels, and the factory now emits only enum case values. Keep the smoke tests as the guard against free-text drift returning. — `src/Filament/Resources/Tags/Schemas/TagForm.php`, `src/Enums/TagTypeEnum.php`, `database/factories/TagFactory.php` — S

2. **Done/Shipped: make `status` gate public visibility.** — `Tag` already exposes `enabled()` through `HasStatus`, and Blog's public `TagLoader` now applies it to page tag chips, tag lists, and tag-page lookup so disabled tags no longer render through Blog public surfaces. — `src/Models/Tag.php`, `packages/blog/src/Support/Loader/TagLoader.php` — M

3. **Done/Shipped: resolve the `workspace_id` ownership boundary.** — Tags owns the taxonomy storage columns on `tags` and `taggables`, exposes `workspace_id` as fillable/cast model state, and documents that Publishing Studio owns workspace lifecycle behavior while Tags owns taxonomy assignment storage. Dropping the columns would be migration-hostile for existing installs, so the package now makes the boundary explicit. — `database/migrations/2026_05_10_190872_01_alter_tags_table.php`, `src/Models/Tag.php`, `src/Models/Taggable.php`, `README.md`, `docs/overview.md` — M

4. **Promote tag landing-page URL logic out of an undeclared contract.** — `Tag::getUrl(Page $tagPage, Language $language)` lives in Tags but is only ever called from Blog (`TagsSitemap`, `BlogTagLinkData`). It assumes a `pageUrl` relation and `*` wildcard substitution that is a Blog convention. Either move it to Blog, or formalise it as a documented public API with its own test in this package (currently untested here). — `src/Models/Tag.php:211` — S

5. **Done/Shipped: justify the `publishing-studio` hard requirement.** — Tags keeps Publishing Studio as a required dependency because taxonomy assignments need workspace-aware storage. The docs now state the split: Tags owns `workspace_id` storage on taxonomy records and Publishing Studio owns workspace lifecycle behavior. — `composer.json`, `capell.json`, `README.md`, `docs/overview.md` — M

6. **Composer description and keywords shipped.** — The composer description and keywords now describe shared multilingual tagging, site scoping, polymorphic taggable relationships, reusable Filament input, and taxonomy positioning. — `composer.json` — S

7. **Manifest screenshots reconciled.** — The manifest now lists the shipped admin index, create/edit, relation-manager, and TagsInput screenshots in light and dark modes. — `capell.json`, `docs/screenshots.json` — S

8. **Health check shipped.** — `TagsHealthCheck` now probes table existence, the configured package tag model, install status, and admin resource registration. — `src/Health/TagsHealthCheck.php` — S

9. **Index the `taggables.taggable_type` + `type` query path.** — Suggestion queries and `findFromStringForSite` filter on `type` + `site_id` + `name->locale`; `TagLoader` filters `type` + counts taggables. The migration indexes `featured`, `status`, `workspace_id`, `site_id` but not `type`. Add a `tags(type, site_id)` composite index to keep the `adminQueryBudget: 40` realistic at scale. — `database/migrations/2026_05_10_190872_01_alter_tags_table.php` — S

10. **Set a real `adminQueryBudget`/cache posture for the index page.** — `TagsTable` eager-loads `site`, `withTranslatedLocales`, and a `taggables_count` per row; with the locale-flags column and badge this is several queries. Validate against the declared budget and add `cacheTags`/invalidation if tag lists are reused on the frontend by consumers. — `src/Filament/Resources/Tags/Tables/TagsTable.php`, `capell.json` `performance` — M

## 3. Missing Features (gaps)

`capell.json` now declares taxonomy, multilingual, site-scoped, polymorphic taggable, and reusable-input capabilities. Remaining gaps below tie back to taxonomy table-stakes vs differentiators.

- **Done/Shipped: polymorphic tagging across content types.** `TagModelRegistrar::registerTaggable()` now gives consumer packages a first-class helper for registering a model's `tags` morph relation plus optional inverse relation on `Tag`, Blog uses it for Article/Page/Section, and the manifest declares `tags-taggable-content`.
- **Tag types/groups as managed vocabulary (table-stakes).** Admin-created tags now use `TagTypeEnum`, but there is still no package-extensible type registry or notion of tag _groups_ (e.g. "Genre", "Mood") beyond the flat `type` string. A `TagGroup` concept or registered type vocabulary is the obvious foundation feature.
- **Merge / rename / dedupe (differentiator).** No merge action. The model already guards against duplicate site tags (`findOrCreateForSite` reuses global `site_id = null` tags), but there is no admin "merge tags" or "rename across taggables" action — a high-value editorial tool for taxonomy hygiene.
- **Tag landing pages as an owned surface (differentiator).** `getUrl()` exists but tag pages are entirely a Blog feature. A foundation "tag archive" page type/route that any taggable content can opt into would make Tags a platform primitive rather than a Blog appendage.
- **Done/Shipped: tag cloud / related-by-tag helpers.** `BuildTagCloudAction` returns weighted `TagCloudItemData` from enabled tags and taggable counts, while `FindRelatedTaggablesAction` returns hydrated related records ranked by shared tags. These are Tags-owned render helpers that consumers can call before public Blade rendering, keeping taxonomy queries out of views. — `src/Actions/BuildTagCloudAction.php`, `src/Actions/FindRelatedTaggablesAction.php`, `src/Data/TagCloudItemData.php`, `src/Data/RelatedTaggableData.php`, `capell.json`
- **Multi-locale tag UX is partial.** Storage is translatable (`name`/`slug` JSON, per-locale fallbacks in `getAttributeValue`), and the admin has a locale switcher — good. But there is no bulk "translate all tags" workflow and no guard that a tag has a slug in every site language.
- **Slug management (table-stakes, mostly present).** Slugs auto-generate on create/replicate via `SlugGenerator`; gap is no uniqueness validation per `(type, site_id, locale)` and no redirect handling when a slug changes (orphans existing tag-page URLs).
- **Featured tags surface (declared-but-inert).** `featured` boolean exists with an admin toggle but no consumer reads it. Either expose a "featured tags" query helper or treat it as host-defined and document it.

## 4. Issues / Risks

- **Typed-taxonomy drift fixed for admin-created tags.** `TagForm` now constrains `type` through `TagTypeEnum`, and factories emit enum case values. Remaining work: decide whether host packages can register additional types through a future type registry. — `src/Filament/Resources/Tags/Schemas/TagForm.php`, `database/factories/TagFactory.php`, `src/Enums/TagTypeEnum.php`
- **Closed: `status` toggle has public effect.** Blog's public tag loader applies `enabled()` to page tag chips, tag archive lists, chunked/static-site tag queries, and tag-page lookup. — `packages/blog/src/Support/Loader/TagLoader.php`
- **Closed: `workspace_id` ownership is explicit.** Tags owns `workspace_id` storage on `tags` and `taggables`, exposes it on both models, and documents Publishing Studio as the lifecycle owner for workspace behavior. — `database/migrations/2026_05_10_190872_01_alter_tags_table.php`, `src/Models/Tag.php`, `src/Models/Taggable.php`, `README.md`, `docs/overview.md`
- **Polymorphic integrity / deletion behaviour unverified.** README and overview both flag: _"Deletion behaviour for taggables should be verified before removing shared tags."_ `Taggable` has `timestamps = false` and no cascade declared in this package (relies on Spatie defaults). Deleting a `Tag` shared across sites/types could strand `taggables` rows or remove tags still in use elsewhere — no test covers cross-consumer deletion. — `src/Models/Taggable.php`, `src/Models/Tag.php`
- **Public output safety is consumer-dependent, untested here.** Tags has no public Blade, but `Tag::getUrl()` and translated `name`/`slug` flow into Blog's public rendering. No test in this package proves anonymous output excludes admin-only data (it's deferred entirely to Blog). For a foundation package whose output reaches the frontend through others, an output-safety contract test is warranted. — `src/Models/Tag.php`
- **Closed: cache safety now declares taxonomy invalidation sources.** `capell.json` exposes `tags` cache tags plus `Tag` and `Taggable` save/delete sources, so consumers can discover the taxonomy invalidation boundary instead of inferring it from Blog internals. — `capell.json` `performance.cacheSafety`
- **`TagsHealthCheck` shipped.** The health check now covers the storage tables, `tags.tag_model`, package install status, and admin resource registration. Remaining opportunity: add a package-specific doctor command if Tags needs direct console diagnostics. — `src/Health/TagsHealthCheck.php`, `src/Providers/TagsServiceProvider.php`
- **Missing `type` index vs declared admin query budget (performance).** `type`-filtered queries (suggestions, loader, find-for-site) are unindexed. — `database/migrations/2026_05_10_190872_01_alter_tags_table.php`, `capell.json` `performance.adminQueryBudget`
- **Test gaps.** 11 test files cover model CRUD, site-scoping, translation fallback, global-tag reuse, Filament list/create/edit/relation-manager, and a boundary arch test. Not covered in-package: `TagPolicy` (zero tests; site-scope authorisation logic is untested here), `Tag::getUrl()` (tested only in Blog), the free-text `type` divergence, `TagsInput` suggestion site-scoping (`accessibleSuggestionSiteIds`), `workspace_id`, and shared-tag deletion integrity. — `tests/`
- **Package-extensible type labels are not solved yet.** Admin-created tag types now use translated `TagTypeEnum` labels, but host packages cannot register their own typed taxonomy labels without changing this package enum. — `src/Enums/TagTypeEnum.php`, `src/Filament/Resources/Tags/Schemas/TagForm.php`

## 5. Marketplace & Positioning

Tags is correctly positioned as **free / foundation / bundled** — it is plumbing that other paid content packages (Blog, Events, Search, Newsletter) build on. That is the right call; do not make it premium. Its commercial value is as a _dependency magnet_: everything taxonomy-shaped should route through it.

**Current `marketplace.summary` (verbatim):** _"Tags adds tag management, taggable relationships, a reusable tags input, and model traits for Capell content."_ — Accurate but feature-listy and inward ("model traits", "taggable relationships" are developer jargon). It sells mechanics, not outcomes.

**Original composer `description`:** _"Tags for Capell."_ — This placeholder-grade copy has been replaced with the improved description below.

**Improved `summary` (outcome-led):** _"One shared, multilingual, multi-site taxonomy for every Capell content type — tag articles, pages, and events from a single managed tag list with reusable tag inputs and per-site scoping."_

**Improved composer `description`:** _"Shared multilingual tagging and taxonomy for Capell: site-scoped tags, polymorphic taggable relationships, and a reusable Filament tags input for Blog, Events, and other content packages."_

**Platform-pitch contribution & cross-sell.** Tags is the connective tissue of the content story: it is what lets Blog tag pages, Events categorisation, and Search faceting share one vocabulary instead of N siloed tag tables (this is exactly the README's "instead of package-specific tag fields" pitch). Lead the bundle narrative with it: _Blog/Events provide content, Tags unifies how it's classified, Search/SEO turn that taxonomy into discoverability._ Concrete cross-sell hooks to build and advertise: tag landing pages (→ SEO Suite sitemaps, already partially wired via `TagsSitemap`), related-by-tag and tag-cloud components (→ Blog/structured-content engagement), tag facets (→ Search). Each is a reason to install an adjacent paid package.

**Screenshot/media gaps.** Manifest advertises 1 image but 8 product screenshots + hero assets exist and are unused in the manifest. Worse, the highest-value visual — `TagsInput` inside a real host form — is marked `required: false` and only capturable with Blog installed, so the marketplace card may never show the package's signature reusable component in context. Action: surface the existing index/form/relation-manager screenshots in the manifest, and add a Blog-mounted `TagsInput` capture to the screenshot runner pipeline.

**8–12 keywords/tags:** `capell`, `tags`, `taxonomy`, `tagging`, `categories`, `multilingual-tags`, `multi-site`, `polymorphic`, `content-classification`, `filament`, `laravel`, `spatie-tags`.

## 6. Prioritized Roadmap

| Item                                                            | Bucket | Effort | Impact | Section ref |
| --------------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Fix composer `description` + expand keywords                    | Done   | S      | Med    | §2.6, §5    |
| Reconcile manifest screenshots with shipped assets              | Done   | S      | Med    | §2.7, §5    |
| Bind tag `type` to enum / remove dead `TagTypeEnum` cases       | Done   | S      | High   | §2.1, §4    |
| Add real `TagsHealthCheck` probes (tables, tag_model, resource) | Done   | S      | High   | §2.8, §4    |
| Add `tags(type, site_id)` composite index                       | Done   | S      | Med    | §2.9, §4    |
| Add `TagPolicy` + `getUrl()` + deletion-integrity tests         | Done   | M      | High   | §4          |
| Done 2026-06-06: resolve `workspace_id` ownership (wired in and documented) | Done   | M      | High   | §2.3, §4    |
| Done 2026-06-06: make `status` gate public visibility (model scope + Blog) | Done   | M      | High   | §2.2, §4    |
| Declare `capabilities[]` in manifest                            | Done   | S      | High   | §3, §4      |
| Done 2026-06-06: declare cache invalidation sources in manifest | Done   | S      | High   | §3, §4      |
| Done 2026-06-06: provide `registerTaggable()` helper for consumers | Done   | M      | High   | §3          |
| Done 2026-06-06: improve marketplace `summary` to outcome-led copy | Done   | S      | Med    | §5          |
| Tag merge / rename / dedupe admin action                        | Later  | L      | High   | §3          |
| Done/Shipped: Tag-cloud + related-by-tag render helpers         | Done   | M      | Med    | §3, §5      |
| First-class tag landing-page surface / page type                | Later  | L      | Med    | §3, §5      |
| Slug uniqueness validation + change redirects                   | Later  | M      | Med    | §3          |
