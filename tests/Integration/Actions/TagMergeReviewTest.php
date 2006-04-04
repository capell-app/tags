<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Actions\PreviewTagMergeAction;
use Capell\Tags\Data\TagMergePreviewData;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    test()->actingAsAdmin();
});

it('previews and collapses duplicate pivots without losing the shared record', function (): void {
    $site = Site::factory()->create();
    $target = Tag::factory()->type(TagTypeEnum::Page)->site($site)->create();
    $source = Tag::factory()->type(TagTypeEnum::Page)->site($site)->create();
    foreach ([$target, $source] as $tag) {
        Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => 'missing-type', 'taggable_id' => 31, 'workspace_id' => 0]);
    }

    $preview = (new PreviewTagMergeAction)->handle($target, [$source], tagReviewActor());
    expect($preview->affected)->toBe(1)->and($preview->duplicates)->toBe(1);
    expect(MergeTagsAction::run($target, [$source], tagReviewActor(), $preview->fingerprint))->toBe(1);
    expect(Taggable::query()->where('tag_id', $target->id)->where('taggable_id', 31)->count())->toBe(1)
        ->and(Tag::query()->find($source->id))->toBeNull();
});

it('rejects a tag merged into itself', function (): void {
    $tag = Tag::factory()->type(TagTypeEnum::Page)->create();
    expect(fn () => MergeTagsAction::run($tag, [$tag], tagReviewActor()))->toThrow(InvalidArgumentException::class);
});

it('rejects incompatible scopes before review', function (): void {
    $target = Tag::factory()->type(TagTypeEnum::Page)->site(Site::factory()->create())->create();
    $source = Tag::factory()->type(TagTypeEnum::Page)->site(Site::factory()->create())->create();
    expect(fn (): TagMergePreviewData => (new PreviewTagMergeAction)->handle($target, [$source], tagReviewActor()))->toThrow(ValidationException::class);
    expect(fn () => MergeTagsAction::run($target, [$source], tagReviewActor()))->toThrow(InvalidArgumentException::class);
    expect($source->fresh())->not->toBeNull();
});

it('invalidates reviews when assignments change', function (): void {
    $target = Tag::factory()->type(TagTypeEnum::Page)->create(['site_id' => null]);
    $source = Tag::factory()->type(TagTypeEnum::Page)->create(['site_id' => null]);
    $preview = (new PreviewTagMergeAction)->handle($target, [$source], tagReviewActor());
    Taggable::query()->create(['tag_id' => $source->id, 'taggable_type' => 'unavailable', 'taggable_id' => 17, 'workspace_id' => 0]);
    expect(fn () => MergeTagsAction::run($target, [$source], tagReviewActor(), $preview->fingerprint))->toThrow(ValidationException::class);
    expect($source->fresh())->not->toBeNull();
});

it('invalidates reviews when the target or source selection changes', function (): void {
    $tags = Tag::factory()->type(TagTypeEnum::Page)->count(3)->create(['site_id' => null]);
    [$target, $source, $other] = $tags->all();
    $preview = (new PreviewTagMergeAction)->handle($target, [$source], tagReviewActor());
    expect(fn () => MergeTagsAction::run($other, [$source], tagReviewActor(), $preview->fingerprint))->toThrow(ValidationException::class);
    expect(fn () => MergeTagsAction::run($target, [$other], tagReviewActor(), $preview->fingerprint))->toThrow(ValidationException::class);
});

it('rolls back aliases moved pivots and deleted sources after a mid merge failure', function (): void {
    [$target, $first, $second] = Tag::factory()->type(TagTypeEnum::Page)->count(3)->create(['site_id' => null])->all();
    Taggable::query()->create(['tag_id' => $first->id, 'taggable_type' => 'unavailable', 'taggable_id' => 17, 'workspace_id' => 0]);
    $aliases = $target->merged_slug_aliases;
    $event = 'eloquent.deleting: ' . Tag::class;
    $dispatcher = Tag::getEventDispatcher() ?? throw new RuntimeException('Expected event dispatcher');
    $isolated = clone $dispatcher;
    $isolated->listen($event, function (Tag $tag) use ($second): void {
        if ($tag->is($second)) {
            throw new RuntimeException('controlled mid-merge failure');
        }
    });
    Tag::setEventDispatcher($isolated);
    try {
        expect(fn () => MergeTagsAction::run($target, [$first, $second], tagReviewActor()))->toThrow(RuntimeException::class, 'controlled mid-merge failure');
    } finally {
        Tag::setEventDispatcher($dispatcher);
    }

    expect($target->fresh()->merged_slug_aliases)->toBe($aliases)
        ->and($first->fresh())->not->toBeNull()
        ->and($second->fresh())->not->toBeNull()
        ->and(DB::table('taggables')->where('tag_id', $first->id)->count())->toBe(1)
        ->and(DB::table('taggables')->where('tag_id', $target->id)->count())->toBe(0);
});

it('rejects a reviewed merge after source metadata changes', function (): void {
    [$target, $source] = Tag::factory()->type(TagTypeEnum::Page)->count(2)->create()->all();
    $actor = auth()->user() ?? throw new RuntimeException('Expected actor');
    $preview = (new PreviewTagMergeAction)->handle($target, [$source], $actor);
    $source->forceFill(['featured' => ! $source->featured])->save();
    expect(fn () => MergeTagsAction::run($target, [$source], $actor, $preview->fingerprint))->toThrow(ValidationException::class);
    expect($source->fresh())->not->toBeNull();
});

it('reauthorizes a reviewed merge before applying any writes', function (): void {
    [$target, $source] = Tag::factory()->type(TagTypeEnum::Page)->count(2)->create()->all();
    $actor = auth()->user() ?? throw new RuntimeException('Expected actor');
    $preview = (new PreviewTagMergeAction)->handle($target, [$source], $actor);
    test()->actingAsUser();
    expect(fn () => MergeTagsAction::run($target, [$source], auth()->user(), $preview->fingerprint))->toThrow(AuthorizationException::class);
    expect($source->fresh())->not->toBeNull();
});

function tagReviewActor(): Authenticatable
{
    return auth()->user() ?? throw new RuntimeException('Expected authenticated actor');
}
