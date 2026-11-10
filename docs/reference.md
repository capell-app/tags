# Tags reference

<!-- prettier-ignore-start -->

Developer reference for `capell-app/tags`. Start with the [README](../README.md) for what the extension does and how to install it.

## Package Details

Tags is an **Available**, **Schema-owning** Capell package in the **Capell Essentials** product group. It ships as `capell-app/tags` and extends these surfaces: admin, console.

- Status: Available
- Tier: free
- Bundle: foundation
- Composer package: `capell-app/tags`
- Namespace: `Capell\Tags`
- Theme key: not applicable

## Technical Shape

### Service providers

- `Capell\Tags\Providers\ConsoleServiceProvider`
- `Capell\Tags\Providers\TagsServiceProvider`
- `Capell\Tags\Providers\AdminServiceProvider`

### Migrations

- `packages/tags/database/migrations/2026_05_10_190872_01_alter_tags_table.php`
- `packages/tags/database/migrations/2026_06_04_000001_add_type_site_id_index_to_tags_table.php`
- `packages/tags/database/migrations/2026_07_10_000001_add_merged_slug_aliases_to_tags_table.php`
- `packages/tags/database/migrations/2026_08_15_000001_add_status_to_tags_table.php`

### Models

- `HasTags`
- `Tag`
- `Taggable`

### Filament classes

- `ManagePageTagsBulkAction`
- `TagsInput`
- `PageTagsPageTableExtender`
- `CreateTag`
- `EditTag`
- `ListTags`
- `PagesRelationManager`
- `TagForm`
- `TagsTable`
- `TagResource`

### Policies

- `TagPolicy`

### Actions

- `BuildMergedTagAliasesAction`
- `BuildTagCloudAction`
- `BuildTagUsageAction`
- `FindRelatedTaggablesAction`
- `InstallTagsPackageAction`
- `ManagePageTagsAction`
- `MergeTagsAction`
- `PreviewTagMergeAction`
- `ResolveTagBySlugAction`

### Data objects

- `RelatedTaggableData`
- `ResolvedTagSlugData`
- `TagCloudItemData`
- `TagMergePreviewData`
- `TagUsageGroupData`

### Command signatures

- `capell:tags-install`

### Manifest action API

- `buildTagCloud: Capell\Tags\Actions\BuildTagCloudAction`
- `findRelatedTaggables: Capell\Tags\Actions\FindRelatedTaggablesAction`
- `install: Capell\Tags\Actions\InstallTagsPackageAction`
- `managePageTags: Capell\Tags\Actions\ManagePageTagsAction`
- `mergeTags: Capell\Tags\Actions\MergeTagsAction`
- `resolveTagBySlug: Capell\Tags\Actions\ResolveTagBySlugAction`

### Console command classes

- `InstallCommand`

### Manifest contributions

- `admin-resource: Capell\Tags\Manifest\TagResourceContribution`
- `console-command: Capell\Tags\Manifest\TagsConsoleCommandsContribution`
- `health-check: Capell\Tags\Health\TagsHealthCheck`
- `migration: Capell\Tags\Manifest\TagsMigrationsContribution`
- `model: Capell\Tags\Manifest\TagsModelsContribution`

### Health checks

- `Capell\Tags\Health\TagsHealthCheck`

### Blade views

- `packages/tags/resources/views/admin/merge-preview.blade.php`
- `packages/tags/resources/views/admin/usage.blade.php`

### Cache tags

- `tags`


## Data Model

- Required tables: `tags`, `taggables`.
- Models: `HasTags`, `Tag`, `Taggable`.
- Core record references in migrations: `sites via site_id`.
- Migration files: `2026_05_10_190872_01_alter_tags_table.php`, `2026_06_04_000001_add_type_site_id_index_to_tags_table.php`, `2026_07_10_000001_add_merged_slug_aliases_to_tags_table.php`, `2026_08_15_000001_add_status_to_tags_table.php`.
- Migration impact: run host migrations through the package install flow before opening package surfaces.
- Deletion/retention behaviour: migrations declare null-on-delete relationships; no timed pruning or retention schedule is declared in `capell.json`.

<!-- prettier-ignore-end -->
