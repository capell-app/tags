<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;
use Capell\Tags\Policies\TagPolicy;
use Capell\Tags\Tests\Fixtures\Models\TagPolicyTestUser;
use Illuminate\Support\Facades\Gate;

it('registers the tag policy', function (): void {
    expect(Gate::getPolicyFor(Tag::class))->toBeInstanceOf(TagPolicy::class);
});

it('allows global admins to use every tag ability', function (): void {
    $policy = new TagPolicy;
    $tag = Tag::factory()->site(Site::factory()->create())->create();
    $user = new TagPolicyTestUser(global: true);

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->view($user, $tag))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->update($user, $tag))->toBeTrue()
        ->and($policy->delete($user, $tag))->toBeTrue()
        ->and($policy->deleteAny($user))->toBeTrue()
        ->and($policy->restore($user, $tag))->toBeTrue()
        ->and($policy->restoreAny($user))->toBeTrue()
        ->and($policy->forceDelete($user, $tag))->toBeTrue()
        ->and($policy->forceDeleteAny($user))->toBeTrue()
        ->and($policy->replicate($user, $tag))->toBeTrue()
        ->and($policy->reorder($user))->toBeTrue();
});

it('requires shield permissions and assigned site access for scoped users', function (): void {
    $assignedSite = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $globalTag = Tag::factory()->create(['site_id' => null]);
    $assignedTag = Tag::factory()->site($assignedSite)->create();
    $otherTag = Tag::factory()->site($otherSite)->create();
    $policy = new TagPolicy;
    $user = new TagPolicyTestUser(
        assignedSiteIds: [(int) $assignedSite->getKey()],
        permissions: [
            tagPolicyPermission('view_any'),
            tagPolicyPermission('create'),
            tagPolicyPermission('update'),
            tagPolicyPermission('delete'),
            tagPolicyPermission('delete_any'),
            tagPolicyPermission('restore'),
            tagPolicyPermission('restore_any'),
            tagPolicyPermission('force_delete'),
            tagPolicyPermission('force_delete_any'),
            tagPolicyPermission('replicate'),
            tagPolicyPermission('reorder'),
        ],
    );

    expect($policy->viewAny($user))->toBeTrue()
        ->and($policy->create($user))->toBeTrue()
        ->and($policy->deleteAny($user))->toBeTrue()
        ->and($policy->restoreAny($user))->toBeTrue()
        ->and($policy->forceDeleteAny($user))->toBeTrue()
        ->and($policy->reorder($user))->toBeTrue()
        ->and($policy->view($user, $globalTag))->toBeTrue()
        ->and($policy->update($user, $globalTag))->toBeFalse()
        ->and($policy->delete($user, $globalTag))->toBeFalse()
        ->and($policy->restore($user, $globalTag))->toBeFalse()
        ->and($policy->forceDelete($user, $globalTag))->toBeFalse()
        ->and($policy->replicate($user, $globalTag))->toBeFalse()
        ->and($policy->view($user, $assignedTag))->toBeTrue()
        ->and($policy->update($user, $assignedTag))->toBeTrue()
        ->and($policy->delete($user, $assignedTag))->toBeTrue()
        ->and($policy->restore($user, $assignedTag))->toBeTrue()
        ->and($policy->forceDelete($user, $assignedTag))->toBeTrue()
        ->and($policy->replicate($user, $assignedTag))->toBeTrue()
        ->and($policy->view($user, $otherTag))->toBeFalse()
        ->and($policy->update($user, $otherTag))->toBeFalse()
        ->and($policy->delete($user, $otherTag))->toBeFalse()
        ->and($policy->restore($user, $otherTag))->toBeFalse()
        ->and($policy->forceDelete($user, $otherTag))->toBeFalse()
        ->and($policy->replicate($user, $otherTag))->toBeFalse();
});

it('denies scoped users without the matching tag permission', function (): void {
    $site = Site::factory()->create();
    $tag = Tag::factory()->site($site)->create();
    $policy = new TagPolicy;
    $user = new TagPolicyTestUser(assignedSiteIds: [(int) $site->getKey()]);

    expect($policy->viewAny($user))->toBeFalse()
        ->and($policy->create($user))->toBeFalse()
        ->and($policy->view($user, $tag))->toBeFalse()
        ->and($policy->update($user, $tag))->toBeFalse()
        ->and($policy->delete($user, $tag))->toBeFalse();
});

function tagPolicyPermission(string $ability): string
{
    $permissions = Utils::getConfig()->permissions;

    return FilamentShield::defaultPermissionKeyBuilder(
        affix: $ability,
        separator: $permissions->separator,
        subject: 'Tag',
        case: $permissions->case,
    );
}
