<?php

namespace App\Support;

use App\Models\Family;
use App\Models\User;

class FamilyAccess
{
    public static function canAccess(User $user, Family $family): bool
    {
        if ($user->isDeveloper()) {
            return true;
        }

        if ((int) $user->institution_id !== (int) $family->institution_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isDataEntry()) {
            return false;
        }

        return $user->hasPermission('view_all_families')
            || (int) $family->data_entry_id === (int) $user->id;
    }
}

