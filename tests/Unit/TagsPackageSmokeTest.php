<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Tags\Actions\BuildTagCloudAction;
use Capell\Tags\Actions\FindRelatedTaggablesAction;
use Capell\Tags\Actions\ManagePageTagsAction;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Filament\Resources\Tags\Schemas\TagForm;
use Capell\Tags\Filament\Resources\Tags\Tables\TagsTable;
use Capell\Tags\Models\Tag;
use Capell\Tags\Providers\TagsServiceProvider;
use Capell\Tags\Tests\Fixtures\Models\CustomTag;
use Capell\Tests\Support\InstalledRuntimeParity;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Spatie\Tags\Tag as SpatieTag;

it('Tag class exists', function (): void {
    expect(class_exists(Tag::class))->toBeTrue();
});

it('TagsServiceProvider class exists', function (): void {
    expect(class_exists(TagsServiceProvider::class))->toBeTrue();
});

it('exposes tag cloud and related content helpers as package actions', function (): void {
    expect(class_exists(BuildTagCloudAction::class))->toBeTrue()
        ->and(class_exists(FindRelatedTaggablesAction::class))->toBeTrue()
        ->and(class_exists(ManagePageTagsAction::class))->toBeTrue()
        ->and(class_exists(MergeTagsAction::class))->toBeTrue();
});

it('repairs stale published tag model config', function (): void {
    InstalledRuntimeParity::assertPackage(
        'tags',
        assertPackage: static function (Application $app): void {
            expect($app->make(Repository::class)->get('tags.tag_model'))->toBe(Tag::class);
        },
        configureBoot: static function (Application $app): void {
            $app->make(Repository::class)->set('tags.tag_model', 'Capell\\Blog\\Models\\Tag');
        },
    );
});

it('repairs the upstream default tag model in installed applications', function (): void {
    InstalledRuntimeParity::assertPackage(
        'tags',
        assertPackage: static function (Application $app): void {
            expect($app->make(Repository::class)->get('tags.tag_model'))->toBe(Tag::class);
        },
        configureBoot: static function (Application $app): void {
            $app->make(Repository::class)->set('tags.tag_model', SpatieTag::class);
        },
    );
});

it('preserves an explicitly configured custom tag model', function (): void {
    InstalledRuntimeParity::assertPackage(
        'tags',
        assertPackage: static function (Application $app): void {
            expect($app->make(Repository::class)->get('tags.tag_model'))->toBe(CustomTag::class);
        },
        configureBoot: static function (Application $app): void {
            $app->make(Repository::class)->set('tags.tag_model', CustomTag::class);
        },
    );
});

it('TagTypeEnum is a backed enum with expected cases', function (): void {
    expect(enum_exists(TagTypeEnum::class))->toBeTrue();

    $cases = TagTypeEnum::cases();
    $caseNames = array_map(fn (TagTypeEnum $case): string => $case->name, $cases);

    expect($caseNames)->toContain('Article')
        ->and($caseNames)->toContain('Content')
        ->and($caseNames)->toContain('Page')
        ->and(TagTypeEnum::Article->getLabel())->toBe('Article')
        ->and(TagTypeEnum::Content->getLabel())->toBe('Content')
        ->and(TagTypeEnum::Page->getLabel())->toBe('Page');
});

it('uses the tag type enum as the admin form source of truth', function (): void {
    $typeSelect = TagForm::typeSelect();

    expect($typeSelect)->toBeInstanceOf(Select::class)
        ->and($typeSelect->getOptions())->toBe(collect(TagTypeEnum::cases())->mapWithKeys(fn (TagTypeEnum $type): array => [$type->value => $type->getLabel()])->all());
});

it('detects duplicate slugs within the same admin tag scope', function (): void {
    $site = Site::factory()->withTranslations()->create();

    Tag::factory()->create([
        'slug' => ['en' => 'duplicate-topic'],
        'site_id' => $site->getKey(),
        'type' => TagTypeEnum::Page->value,
    ]);

    expect(fn (): null => TagForm::assertUniqueSlug([
        'slug' => 'duplicate-topic',
        'site_id' => $site->getKey(),
        'type' => TagTypeEnum::Page->value,
    ], locale: 'en'))->toThrow(ValidationException::class);
});

it('creates factory tags with supported enum-backed types', function (): void {
    $allowedTypes = array_map(
        static fn (TagTypeEnum $type): string => $type->value,
        TagTypeEnum::cases(),
    );

    $tags = Tag::factory()->count(10)->create();

    expect($tags->pluck('type')->unique()->values()->all())
        ->each->toBeIn($allowedTypes);
});

it('declares taxonomy capabilities in the package manifest', function (): void {
    /** @var array{actions: array<string, string>, capabilities: list<string>, performance: array{cacheTags: list<string>}} $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['capabilities'])->toContain(
        'tags-taxonomy',
        'tags-multilingual',
        'tags-site-scoped',
        'tags-polymorphic-taggables',
        'tags-merge-dedupe',
        'tags-page-bulk-management',
        'tags-related-content',
        'tags-tag-cloud',
        'tags-reusable-input',
    );
    expect($manifest['actions'])->toHaveKeys([
        'buildTagCloud',
        'findRelatedTaggables',
        'managePageTags',
        'mergeTags',
    ]);
    expect($manifest['performance']['cacheTags'])->toContain('tags');
});

it('renders tag form and table labels without the optional layout builder dictionary', function (): void {
    $translations = app('translator');
    $translations->getLoader()->addNamespace('capell-layout-builder', __DIR__ . '/missing-translations');
    $translations->setLoaded([]);
    $livewire = new class extends Component implements HasSchemas, HasTable
    {
        use InteractsWithSchemas;
        use InteractsWithTable;
    };
    $schema = TagForm::configure(Schema::make($livewire));
    expect($schema->toHtml())->toContain(__('capell-tags::form.featured'))
        ->not->toContain('capell-layout-builder::');
    $columns = TagsTable::configure(Table::make($livewire))->getColumns();
    foreach ($columns as $column) {
        expect($column->getLabel())->not->toContain('capell-layout-builder::');
    }
});
