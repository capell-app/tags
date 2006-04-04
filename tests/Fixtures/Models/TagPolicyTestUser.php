<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;

final class TagPolicyTestUser extends User
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /**
     * @param  list<int>  $assignedSiteIds
     * @param  list<string>  $permissions
     */
    public function __construct(
        private readonly bool $global = false,
        private readonly array $assignedSiteIds = [],
        private readonly array $permissions = [],
    ) {
        parent::__construct();
    }

    public function hasRole(string $role): bool
    {
        return false;
    }

    public function isGlobalAdmin(): bool
    {
        return $this->global;
    }

    /**
     * @return Collection<int, int>
     */
    public function getAssignedSiteIds(): Collection
    {
        return collect($this->assignedSiteIds);
    }

    public function checkPermissionTo(mixed $permission, mixed $guardName = null): bool
    {
        unset($guardName);

        return in_array((string) $permission, $this->permissions, true);
    }
}
