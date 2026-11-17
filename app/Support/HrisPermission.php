<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Role-based permissions (SECURITY.md §3). The role => permission map lives in config('hris.permissions'),
 * so a new role only needs a config entry; code always checks a permission, never a role name.
 */
final class HrisPermission
{
    /** Prepare, save and submit chain mutation proposals. */
    public const MUTASI_PREPARE = 'mutasi.prepare';

    /** Approve or reject proposals, or issue the SK directly when approval is not required. */
    public const MUTASI_APPROVE = 'mutasi.approve';

    /** Draft, review, submit and cancel organisation structure decrees (SK). */
    public const STRUKTUR_PREPARE = 'struktur.prepare';

    /** Approve or reject organisation structure decrees (SK). */
    public const STRUKTUR_APPROVE = 'struktur.approve';

    /** Register every configured permission as a Gate ability. */
    public static function register(): void
    {
        foreach (array_keys((array) config('hris.permissions', [])) as $ability) {
            Gate::define($ability, fn (User $user) => self::roleHas($user->role, $ability));
        }
    }

    public static function roleHas(?string $role, string $ability): bool
    {
        // Read the whole map: ability names contain a dot, which config() would treat as a nested path
        $roles = (array) (((array) config('hris.permissions', []))[$ability] ?? []);

        return $role !== null && in_array($role, $roles, true);
    }
}
