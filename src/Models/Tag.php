<?php

declare(strict_types=1);

namespace Capell\Tags\Models;

use ArrayAccess;
use Capell\Core\Models\Concerns\HasStatus;
use Capell\Core\Models\Contracts\Statusable;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Database\Factories\TagFactory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Override;
use Traversable;

/**
 * @property int $id
 * @property array<string, string> $name
 * @property array<string, string> $slug
 * @property array<string, list<string>>|null $merged_slug_aliases
 * @property string|null $type
 * @property int|null $order_column
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property bool $featured
 * @property bool $status
 * @property int|null $site_id
 * @property int $workspace_id
 * @property-read SupportCollection<int, Page> $pages
 * @property-read SupportCollection<int, Taggable> $taggables
 * @property-read int|null $pages_count
 * @property-read int|null $taggables_count
 * @property-read Site|null $site
 * @property-read mixed $translations
 *
 * @method static Builder<static>|Tag containing(string $name, $locale = null)
 * @method static Builder<static>|Tag disabled()
 * @method static Builder<static>|Tag enabled()
 * @method static TagFactory factory($count = null, $state = [])
 * @method static Builder<static>|Tag newModelQuery()
 * @method static Builder<static>|Tag newQuery()
 * @method static Builder<static>|Tag ordered(string $direction = 'asc', $locale = null)
 * @method static Builder<static>|Tag query()
 * @method static Builder<static>|Tag whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Tag whereJsonContainsLocales(string $column, array<int, string> $locales, ?mixed $value, string $operand = '=')
 * @method static Builder<static>|Tag whereLocale(string $column, string $locale)
 * @method static Builder<static>|Tag whereLocales(string $column, array<int, string> $locales)
 * @method static Builder<static>|Tag withTranslatedLocales(string $key)
 * @method static Builder<static>|Tag withType(?string $type = null)
 * @method static Builder<static>|Tag status(bool $enabled)
 *
 * @mixin Model
 */
class Tag extends \Spatie\Tags\Tag implements Statusable
{
    /** @use HasStatus<self> */
    use HasStatus;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'featured',
        'meta',
        'merged_slug_aliases',
        'name',
        'order_column',
        'site_id',
        'slug',
        'status',
        'type',
        'workspace_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'featured' => 'bool',
        'status' => 'bool',
        'workspace_id' => 'int',
    ];

    protected static string $factory = TagFactory::class;

    /**
     * @param  string|array<array-key, string|self>|ArrayAccess<array-key, string|self>  $values
     * @return SupportCollection<int, self>|self
     */
    #[Override]
    public static function findOrCreate(string|array|ArrayAccess $values, ?string $type = null, ?string $locale = null): SupportCollection|self
    {
        if (is_string($values)) {
            return static::findOrCreateFromString($values, $type, $locale);
        }

        $normalisedValues = match (true) {
            is_array($values) => $values,
            $values instanceof Traversable => iterator_to_array($values),
            default => [],
        };

        $tags = SupportCollection::make($normalisedValues)
            ->map(function (string|self $value) use ($type, $locale): self {
                if ($value instanceof self) {
                    return $value;
                }

                return static::findOrCreateFromString($value, $type, $locale);
            });

        return $tags->values();
    }

    /**
     * @param  string|array<array-key, string|self>|ArrayAccess<array-key, string|self>  $values
     * @return SupportCollection<int, self>|self
     */
    public static function findOrCreateForSite(string|array|ArrayAccess $values, ?string $type = null, ?string $locale = null, ?int $siteId = null): SupportCollection|self
    {
        if ($siteId === null) {
            return static::findOrCreate($values, $type, $locale);
        }

        if (is_string($values)) {
            return static::findOrCreateFromStringForSite($values, $type, $locale, $siteId);
        }

        $normalisedValues = match (true) {
            is_array($values) => $values,
            $values instanceof Traversable => iterator_to_array($values),
            default => [],
        };

        return SupportCollection::make($normalisedValues)
            ->map(function (string|self $value) use ($type, $locale, $siteId): self {
                if ($value instanceof self) {
                    return $value;
                }

                return static::findOrCreateFromStringForSite($value, $type, $locale, $siteId);
            })
            ->values();
    }

    #[Override]
    public static function findOrCreateFromString(string $name, ?string $type = null, ?string $locale = null): self
    {
        $locale ??= static::getLocale();

        $tag = static::findFromString($name, $type, $locale);

        if (! $tag) {
            $defaultLocale = static::getLocale();

            if ($locale !== $defaultLocale) {
                $tag = static::findFromString($name, $type, $defaultLocale);
            }

            if (! $tag) {
                $tag = static::query()->create([
                    'name' => [$locale => $name],
                    'slug' => [$locale => str($name)->slug()],
                    'type' => $type,
                ]);
            } elseif (! $tag->hasTranslation('name', $locale)) {
                $tag->setTranslation('name', $locale, $name);
                $tag->setTranslation('slug', $locale, str($name)->slug()->toString());
                $tag->save();
            }
        }

        return $tag;
    }

    public static function findOrCreateFromStringForSite(string $name, ?string $type = null, ?string $locale = null, int $siteId = 0): self
    {
        $locale ??= static::getLocale();

        $tag = self::findFromStringForSite($name, $type, $locale, $siteId)
            ?? self::findFromStringForSite($name, $type, $locale, null);

        if (! $tag instanceof Tag) {
            $defaultLocale = static::getLocale();

            if ($locale !== $defaultLocale) {
                $tag = self::findFromStringForSite($name, $type, $defaultLocale, $siteId)
                    ?? self::findFromStringForSite($name, $type, $defaultLocale, null);
            }

            if (! $tag instanceof Tag) {
                $tag = static::query()->create([
                    'name' => [$locale => $name],
                    'slug' => [$locale => str($name)->slug()],
                    'site_id' => $siteId,
                    'type' => $type,
                ]);
            } elseif (! $tag->hasTranslation('name', $locale)) {
                $tag->setTranslation('name', $locale, $name);
                $tag->setTranslation('slug', $locale, str($name)->slug()->toString());
                $tag->save();
            }
        }

        return $tag;
    }

    /** @return array<int, string> */
    #[Override]
    public function getTranslatedLocales(string $key): array
    {
        return Language::getLanguageLocales();
    }

    public function getUrl(Page $tagPage, Language $language): string
    {
        $slug = (string) ($this->getTranslations('slug')[$language->code] ?? '');
        $pageUrl = $tagPage->relationLoaded('pageUrl') ? $tagPage->pageUrl : null;

        if ($pageUrl === null) {
            return '/' . $slug;
        }

        if (str_contains($pageUrl->full_url, '*')) {
            return str_replace('*', $slug, $pageUrl->full_url);
        }

        return $pageUrl->full_url . '/' . $slug;
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return MorphToMany<Page, $this>
     */
    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'taggable');
    }

    /**
     * Access the raw taggable pivot records for this tag.
     *
     * This returns the pivot rows from the `taggables` table so callers can
     * inspect which models (type + id) are associated with this tag. For
     * convenience use the morph-specific relations like `pages()` when you
     * need the hydrated models. Consumer packages (blog, layout-builder) register
     * their own morph relations via Tag::resolveRelationUsing().
     */
    /** @return HasMany<Taggable, $this> */
    public function taggables(): HasMany
    {
        return $this->hasMany(Taggable::class, 'tag_id', 'id');
    }

    #[Override]
    public function scopeOrdered(Builder $query, string $direction = 'asc', ?string $locale = null): Builder
    {
        $locale ??= static::getLocale();

        $query->orderBy($this->determineOrderColumnName(), $direction);

        return $query->orderByRaw($this->getQuery()->getGrammar()->wrap('name->' . $locale) . ' ' . $direction);
    }

    #[Override]
    public function shouldSortWhenCreating(): bool
    {
        return false;
    }

    public function getFirstTranslationLocale(string $key): ?string
    {
        $locales = $this->getTranslatedLocales($key);

        foreach ($locales as $locale) {
            if ($this->hasTranslation($key, $locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * @param  string  $key
     */
    #[Override]
    public function getAttributeValue($key): mixed
    {
        if (! $this->isTranslatableAttribute($key)) {
            return parent::getAttributeValue($key);
        }

        $value = $this->getTranslation($key, $this->getLocale(), $this->useFallbackLocale());

        if (blank($value)) {
            $locale = $this->getFirstTranslationLocale($key);

            if (! in_array($locale, [null, '', '0'], true)) {
                $value = $this->getTranslation($key, $locale, false);
            }
        }

        return $value;
    }

    protected static function booted(): void
    {
        static::saving(function (self $tag): void {
            $tag->assertScopedSlugIsUnique();
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeWithTranslatedLocales(Builder $query, string $key): Builder
    {
        return $query->addSelect(
            DB::raw(
                $this->getConnection()->getDriverName() === 'sqlite'
                    ? 'NULL as translated_locales'
                    : 'JSON_KEYS(' . $this->getQuery()->getGrammar()->wrap($key) . ') as translated_locales',
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'meta' => 'json',
            'merged_slug_aliases' => 'array',
            'featured' => 'boolean',
            'status' => 'boolean',
        ];
    }

    private static function findFromStringForSite(string $name, ?string $type, string $locale, ?int $siteId): ?self
    {
        $tag = static::query()
            ->where('type', $type)
            ->where('site_id', $siteId)
            ->where(function (Builder $query) use ($name, $locale): void {
                $query->where('name->' . $locale, $name)
                    ->orWhere('slug->' . $locale, $name);
            })
            ->first();

        return $tag instanceof self ? $tag : null;
    }

    private function assertScopedSlugIsUnique(): void
    {
        $slugTranslations = $this->getTranslations('slug');
        $locales = array_keys($slugTranslations);

        foreach ($locales as $locale) {
            $slug = $slugTranslations[$locale] ?? null;

            if (! is_string($slug) || trim($slug) === '') {
                continue;
            }

            $duplicateExists = self::query()
                ->where('type', $this->type)
                ->where('site_id', $this->site_id)
                ->when($this->exists && $this->getKey() !== null, fn (Builder $query): Builder => $query->whereKeyNot($this->getKey()))
                ->get()
                ->contains(static fn (self $tag): bool => $tag->getTranslation('slug', (string) $locale, false) === trim($slug));

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'slug' => __('capell-tags::form.slug_unique'),
                ]);
            }
        }
    }
}
