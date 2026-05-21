<?php

declare(strict_types=1);

namespace Capell\Tags\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Admin\Support\SiteScope;
use Capell\Tags\Models\Tag;
use Illuminate\Foundation\Auth\User;
use Throwable;

final class TagPolicy
{
    use ResolvesShieldPermission;

    private const string SUBJECT = 'Tag';

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view']);
    }

    public function view(User $user, Tag $tag): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view'])
            && $this->canUseTagSite($user, $tag);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'create');
    }

    public function update(User $user, Tag $tag): bool
    {
        return $this->hasPermission($user, 'update')
            && $this->canUseTagSite($user, $tag);
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $this->hasPermission($user, 'delete')
            && $this->canUseTagSite($user, $tag);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'delete_any');
    }

    public function restore(User $user, Tag $tag): bool
    {
        return $this->hasPermission($user, 'restore')
            && $this->canUseTagSite($user, $tag);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasPermission($user, 'restore_any');
    }

    public function forceDelete(User $user, Tag $tag): bool
    {
        return $this->hasPermission($user, 'force_delete')
            && $this->canUseTagSite($user, $tag);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'force_delete_any');
    }

    public function replicate(User $user, Tag $tag): bool
    {
        return $this->hasPermission($user, 'replicate')
            && $this->canUseTagSite($user, $tag);
    }

    public function reorder(User $user): bool
    {
        return $this->hasPermission($user, 'reorder');
    }

    /**
     * @param  list<string>  $abilities
     */
    private function hasAnyPermission(User $user, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->hasPermission($user, $ability)) {
                return true;
            }
        }

        return false;
    }

    private function hasPermission(User $user, string $ability): bool
    {
        if (SiteScope::isGlobalActor($user)) {
            return true;
        }

        if (! method_exists($user, 'checkPermissionTo')) {
            return false;
        }

        try {
            return $user->checkPermissionTo(self::permission($ability, self::SUBJECT));
        } catch (Throwable) {
            return false;
        }
    }

    private function canUseTagSite(User $user, Tag $tag): bool
    {
        if ($tag->site_id === null || SiteScope::isGlobalActor($user)) {
            return true;
        }

        if (method_exists($user, 'getAssignedSiteIds')) {
            return $user->getAssignedSiteIds()->contains($tag->site_id);
        }

        return true;
    }
}
