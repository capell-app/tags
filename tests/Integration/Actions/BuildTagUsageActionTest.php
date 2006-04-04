<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Actions\BuildTagUsageAction;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Capell\Tags\Tests\Fixtures\Models\TagPolicyTestUser;
use Capell\Tags\Tests\Fixtures\Models\UsageRecord;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    test()->actingAsAdmin();
    Schema::create('tag_usage_records', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('site_id')->nullable();
        $table->softDeletes();
    });
});

it('projects usage across types and sites while counting unavailable and soft deleted morphs', function (): void {
    $tag = Tag::factory()->create();
    $sites = Site::factory()->count(2)->create();
    foreach ($sites as $index => $site) {
        $record = UsageRecord::query()->create(['name' => 'Record ' . $index, 'site_id' => $site->id]);
        Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => UsageRecord::class, 'taggable_id' => $record->getKey(), 'workspace_id' => 0]);
    }

    $page = Page::factory()->site($sites[0])->create();
    Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => $page->getMorphClass(), 'taggable_id' => $page->id, 'workspace_id' => 0]);
    $deleted = UsageRecord::query()->create(['name' => 'Deleted secret']);
    $deleted->delete();
    foreach ([[UsageRecord::class, $deleted->getKey()], ['missing-package-morph', 91]] as [$type, $id]) {
        Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => $type, 'taggable_id' => $id, 'workspace_id' => 0]);
    }

    $groups = (new BuildTagUsageAction)->handle([$tag], (auth()->user() ?? throw new RuntimeException('Expected authenticated actor')));
    expect(array_sum(array_column($groups, 'count')))->toBe(5)
        ->and(array_column($groups, 'site'))->toContain((string) $sites[0]->name, (string) $sites[1]->name)
        ->and(collect($groups)->firstWhere('type', __('capell-tags::generic.usage_unavailable'))?->count)->toBe(2)
        ->and(json_encode($groups))->not->toContain('Deleted secret');
});

it('does not expose usage to an actor unable to view the tag', function (): void {
    $tag = Tag::factory()->create();
    test()->actingAsUser();
    expect(fn (): array => (new BuildTagUsageAction)->handle([$tag], (auth()->user() ?? throw new RuntimeException('Expected authenticated actor'))))->toThrow(AuthorizationException::class);
});

it('loads a morph group with bounded queries as its record count grows', function (): void {
    $tag = Tag::factory()->create();
    $measure = function () use ($tag): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        (new BuildTagUsageAction)->handle([$tag], (auth()->user() ?? throw new RuntimeException('Expected authenticated actor')));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };
    $attach = function (int $number) use ($tag): void {
        $record = UsageRecord::query()->create(['name' => 'Record ' . $number]);
        Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => UsageRecord::class, 'taggable_id' => $record->getKey(), 'workspace_id' => 0]);
    };
    $attach(1);
    $baseline = $measure();
    foreach (range(2, 20) as $number) {
        $attach($number);
    }

    expect($measure())->toBeLessThanOrEqual($baseline);
});

it('filters record labels and sites using the supplied actor', function (): void {
    $sites = Site::factory()->count(2)->create();
    $tag = Tag::factory()->create();
    foreach ($sites as $index => $site) {
        $record = UsageRecord::query()->create(['name' => 'Scoped record ' . $index, 'site_id' => $site->id]);
        Taggable::query()->create(['tag_id' => $tag->id, 'taggable_type' => UsageRecord::class, 'taggable_id' => $record->getKey(), 'workspace_id' => 0]);
    }

    $actor = new TagPolicyTestUser(assignedSiteIds: [$sites[0]->id]);
    Gate::before(fn ($user, string $ability): ?bool => $user === $actor && $ability === 'view' ? true : null);
    $groups = (new BuildTagUsageAction)->handle([$tag], $actor);
    expect(array_sum(array_column($groups, 'count')))->toBe(1)
        ->and(json_encode($groups))->toContain('Scoped record 0')->not->toContain('Scoped record 1', (string) $sites[1]->name);
});
