<?php

declare(strict_types=1);

namespace Capell\Tags\Support;

use Capell\Core\Facades\CapellCore;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TagModelRegistrar
{
    /** @var list<class-string> */
    private const array MODELS = [
        Tag::class,
        Taggable::class,
    ];

    public static function register(): void
    {
        CapellCore::registerModels(self::MODELS);

        /** @var array<string, class-string<Model>> $morphMap */
        $morphMap = collect(self::MODELS)
            ->mapWithKeys(fn (string $modelClass): array => [Str::snake(class_basename($modelClass)) => $modelClass])
            ->all();

        Relation::morphMap($morphMap);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function registerTaggable(
        string $modelClass,
        string $tagRelation = 'tags',
        ?string $inverseRelation = null,
    ): void {
        if (! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException(sprintf('Taggable model [%s] must extend %s.', $modelClass, Model::class));
        }

        CapellCore::registerModelRelations($modelClass, $tagRelation);

        $modelClass::resolveRelationUsing(
            $tagRelation,
            static fn (Model $model): MorphToMany => $model->morphToMany(Tag::class, 'taggable', 'taggables'),
        );

        if ($inverseRelation === null || $inverseRelation === '') {
            return;
        }

        Tag::resolveRelationUsing(
            $inverseRelation,
            static fn (Tag $tag): MorphToMany => $tag->morphedByMany($modelClass, 'taggable', 'taggables'),
        );
    }
}
