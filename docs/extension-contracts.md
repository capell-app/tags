# Worked extension examples

These developer-facing recipes are kept beside the package contract. Replace the example values with the site-specific records and data objects used by the calling workflow.

<!-- example: action buildTagCloud -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Tags\Actions\BuildTagCloudAction::class)->handle(...$inputs);
```

<!-- example: action findRelatedTaggables -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Tags\Actions\FindRelatedTaggablesAction::class)->handle(...$inputs);
```

<!-- example: action install -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Tags\Actions\InstallTagsPackageAction::class)->handle(...$inputs);
```

<!-- example: action managePageTags -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Tags\Actions\ManagePageTagsAction::class)->handle(...$inputs);
```

<!-- example: action mergeTags -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Tags\Actions\MergeTagsAction::class)->handle(...$inputs);
```

<!-- example: action resolveTagBySlug -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Tags\Actions\ResolveTagBySlugAction::class)->handle(...$inputs);
```
