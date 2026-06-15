# Tags

<!-- prettier-ignore-start -->

## What This Plugin Adds

Tags is an **Available**, **Schema-owning** Capell package in the **Capell Foundation** product group. It ships as `capell-app/tags` and extends these surfaces: admin, console.

Shared multilingual tagging and taxonomy for Capell: site-scoped tags, polymorphic taggable relationships, and a reusable Filament tags input for Blog, Events, and other content packages.

After install, admins get package-owned management or reporting surfaces inside Capell.

Status details:

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/tags`
- Namespace: `Capell\Tags`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, models, and Filament classes instead of pushing this behaviour into core or application code.

**For teams:** One shared, multilingual, multi-site taxonomy for every Capell content type - tag articles, pages, and events from a single managed tag list with reusable tag inputs and per-site scoping.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Tags admin index (admin, required).
- Create/edit tag form (admin, required).
- Tag relation manager showing tagged pages (admin, required).
- Article or page form using TagsInput (admin, optional).

## Technical Shape

- Service providers: `Capell\Tags\Providers\ConsoleServiceProvider`, `Capell\Tags\Providers\TagsServiceProvider`, `Capell\Tags\Providers\AdminServiceProvider`.
- Migrations: `packages/tags/database/migrations/2026_05_10_190872_01_alter_tags_table.php`, `packages/tags/database/migrations/2026_06_04_000001_add_type_site_id_index_to_tags_table.php`.
- Models: `HasTags`, `Tag`, `Taggable`.
- Filament classes: `TagsInput`, `CreateTag`, `EditTag`, `ListTags`, `PagesRelationManager`, `TagForm`, `TagsTable`, `TagResource`.
- Policies: `TagPolicy`.
- Actions: `BuildTagCloudAction`, `FindRelatedTaggablesAction`, `InstallTagsPackageAction`, `MergeTagsAction`.
- Data objects: `RelatedTaggableData`, `TagCloudItemData`.
- Command signatures: `capell:tags-install`.
- Console command classes: `InstallCommand`.
- Manifest contributions: `admin-resource: Capell\Tags\Manifest\TagResourceContribution`, `console-command: Capell\Tags\Manifest\TagsConsoleCommandsContribution`, `health-check: Capell\Tags\Health\TagsHealthCheck`, `migration: Capell\Tags\Manifest\TagsMigrationsContribution`, `model: Capell\Tags\Manifest\TagsModelsContribution`.
- Health checks: `Capell\Tags\Health\TagsHealthCheck`.
- Cache tags: `tags`.

## Data Model

- Required tables: `tags`, `taggables`.
- Models: `HasTags`, `Tag`, `Taggable`.
- Migration files: `2026_05_10_190872_01_alter_tags_table.php`, `2026_06_04_000001_add_type_site_id_index_to_tags_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: Docs gap unless the package has an explicit pruning command, retention setting, or tested cascade path.

## Install Impact

- Admin navigation: adds package-owned Filament classes when registered.
- Permissions: none declared in `capell.json`.
- Public routes: none detected in package route files.
- Database changes: package migrations are declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: `tags`.
- Commands: `capell:tags-install`.

## Common Pitfalls

- Run migrations before opening package resources or public routes.
- Run package commands from the host app; in this repository use `vendor/bin/pest` for package tests.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Admin screen or command fails on missing table | Package migrations have not run | Check the tables listed in `Data Model` | Run host migrations and rerun the focused package test |
| Background work does not run | Queue worker or scheduled command is not active | Check package jobs, commands, and host scheduler configuration | Start the queue or scheduler, then run the focused command or package test |

## Quick Start

1. Install the package: `composer require capell-app/tags`.
2. Run the package install command from the installed Capell app.
3. Open the related Capell admin surface and verify Tags appears.

## Next Steps

- [Package docs index](README.md)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Navigation](../../navigation/README.md), [Publishing Studio](../../publishing-studio/README.md).
- Focused tests: `vendor/bin/pest packages/tags/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
