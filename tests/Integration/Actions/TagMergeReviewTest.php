<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Tags\Actions\BuildTagUsageAction;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Actions\PreviewTagMergeAction;
use Capell\Tags\Data\TagMergePreviewData;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Dom\HTMLDocument;
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
    expect(fn () => MergeTagsAction::run($tag, [$tag], tagReviewActor(), 'unreviewed'))->toThrow(InvalidArgumentException::class);
});

it('rejects incompatible scopes before review', function (): void {
    $target = Tag::factory()->type(TagTypeEnum::Page)->site(Site::factory()->create())->create();
    $source = Tag::factory()->type(TagTypeEnum::Page)->site(Site::factory()->create())->create();
    expect(fn (): TagMergePreviewData => (new PreviewTagMergeAction)->handle($target, [$source], tagReviewActor()))->toThrow(ValidationException::class);
    expect(fn () => MergeTagsAction::run($target, [$source], tagReviewActor(), 'unreviewed'))->toThrow(InvalidArgumentException::class);
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

it('invalidates reviews when an assignment changes workspace', function (): void {
    $target = Tag::factory()->type(TagTypeEnum::Page)->create(['site_id' => null]);
    $source = Tag::factory()->type(TagTypeEnum::Page)->create(['site_id' => null]);
    Taggable::query()->create(['tag_id' => $source->id, 'taggable_type' => 'unavailable', 'taggable_id' => 17, 'workspace_id' => 1]);

    $preview = (new PreviewTagMergeAction)->handle($target, [$source], tagReviewActor());
    DB::table('taggables')->where('tag_id', $source->id)->where('taggable_id', 17)->update(['workspace_id' => 2]);

    expect(fn () => MergeTagsAction::run($target, [$source], tagReviewActor(), $preview->fingerprint))->toThrow(ValidationException::class)
        ->and($source->fresh())->not->toBeNull();
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
    $fingerprint = (new PreviewTagMergeAction)->handle($target, [$first, $second], tagReviewActor())->fingerprint;
    Tag::setEventDispatcher($isolated);
    try {
        expect(fn () => MergeTagsAction::run($target, [$first, $second], tagReviewActor(), $fingerprint))->toThrow(RuntimeException::class, 'controlled mid-merge failure');
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

it('refuses an unreviewed or forged review fingerprint and changes nothing', function (): void {
    [$target, $source] = Tag::factory()->type(TagTypeEnum::Page)->count(2)->create(['site_id' => null])->all();
    Taggable::query()->create(['tag_id' => $source->id, 'taggable_type' => 'unavailable', 'taggable_id' => 5, 'workspace_id' => 0]);

    expect(fn () => MergeTagsAction::run($target, [$source], tagReviewActor(), str_repeat('0', 64)))->toThrow(ValidationException::class);

    expect($source->fresh())->not->toBeNull()
        ->and(DB::table('taggables')->where('tag_id', $source->id)->count())->toBe(1);
});

it('moves any number of assignments in a fixed number of statements and collapses duplicates across sources', function (): void {
    [$target, $first, $second] = Tag::factory()->type(TagTypeEnum::Page)->count(3)->create(['site_id' => null])->all();
    foreach (range(1, 40) as $id) {
        Taggable::query()->create(['tag_id' => $first->id, 'taggable_type' => 'unavailable', 'taggable_id' => $id, 'workspace_id' => 0]);
    }

    // Record 1 is already on the target; records 1-10 are on both sources.
    Taggable::query()->create(['tag_id' => $target->id, 'taggable_type' => 'unavailable', 'taggable_id' => 1, 'workspace_id' => 0]);
    foreach (range(1, 10) as $id) {
        Taggable::query()->create(['tag_id' => $second->id, 'taggable_type' => 'unavailable', 'taggable_id' => $id, 'workspace_id' => 0]);
    }

    $fingerprint = (new PreviewTagMergeAction)->handle($target, [$first, $second], tagReviewActor())->fingerprint;
    $pivotWrites = 0;
    DB::listen(static function ($query) use (&$pivotWrites): void {
        if (str_contains($query->sql, 'taggables') && preg_match('/^\s*(update|delete)/i', $query->sql) === 1) {
            $pivotWrites++;
        }
    });

    expect(MergeTagsAction::run($target, [$first, $second], tagReviewActor(), $fingerprint))->toBe(2);

    $assigned = DB::table('taggables')->where('tag_id', $target->id)->pluck('taggable_id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->sort()->values()->all();

    expect($assigned)->toBe(range(1, 40))
        ->and(DB::table('taggables')->whereIn('tag_id', [$first->id, $second->id])->count())->toBe(0)
        ->and($pivotWrites)->toBeLessThanOrEqual(3);
});

it('collapses assignments of the same record made in different workspaces, as the unique key requires', function (): void {
    [$target, $first, $second] = Tag::factory()->type(TagTypeEnum::Page)->count(3)->create(['site_id' => null])->all();
    // Record 7 is on the target in workspace 1 and on a source in workspace 2.
    Taggable::query()->create(['tag_id' => $target->id, 'taggable_type' => 'unavailable', 'taggable_id' => 7, 'workspace_id' => 1]);
    Taggable::query()->create(['tag_id' => $first->id, 'taggable_type' => 'unavailable', 'taggable_id' => 7, 'workspace_id' => 2]);
    // Record 8 is on both sources, each in a different workspace.
    Taggable::query()->create(['tag_id' => $first->id, 'taggable_type' => 'unavailable', 'taggable_id' => 8, 'workspace_id' => 3]);
    Taggable::query()->create(['tag_id' => $second->id, 'taggable_type' => 'unavailable', 'taggable_id' => 8, 'workspace_id' => 4]);
    $preview = (new PreviewTagMergeAction)->handle($target, [$first, $second], tagReviewActor());

    expect($preview->affected)->toBe(2)->and($preview->duplicates)->toBe(2);
    expect(MergeTagsAction::run($target, [$first, $second], tagReviewActor(), $preview->fingerprint))->toBe(2);

    $rows = DB::table('taggables')->where('tag_id', $target->id)->orderBy('taggable_id')->get(['taggable_id', 'workspace_id'])
        ->map(static fn (object $row): array => [(int) $row->taggable_id, (int) $row->workspace_id])->all();

    expect($rows)->toBe([[7, 1], [8, 3]])
        ->and(DB::table('taggables')->whereIn('tag_id', [$first->id, $second->id])->count())->toBe(0);
});

it('lists an affected record once in a merge preview across source workspaces', function (): void {
    [$target, $first, $second] = Tag::factory()->type(TagTypeEnum::Page)->count(3)->create(['site_id' => null])->all();
    $record = Site::factory()->create();
    foreach ([[$first, 1], [$second, 2]] as [$tag, $workspace]) {
        Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => Site::class, 'taggable_id' => $record->id, 'workspace_id' => $workspace]);
    }

    $preview = (new PreviewTagMergeAction)->handle($target, [$first, $second], tagReviewActor());
    expect($preview->affected)->toBe(1)
        ->and($preview->usage)->toHaveCount(1)
        ->and($preview->usage[0]->count)->toBe(1)
        ->and($preview->usage[0]->records)->toHaveCount(1);
    $html = view('capell-tags::admin.merge-preview', ['preview' => $preview])->render();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    expect($document->querySelectorAll('section ul li')->length)->toBe(1);

    $standalone = (new BuildTagUsageAction)->handle([$first, $second], tagReviewActor());
    expect($standalone[0]->count)->toBe(2)->and($standalone[0]->records)->toHaveCount(2);
});
