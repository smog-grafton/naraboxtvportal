<?php

namespace App\Services;

use App\Models\CreatorPermission;
use App\Models\User;

class CreatorAccessService
{
    public function workspace(User $user): bool
    {
        return $user->isAdmin() || $user->hasCreatorWorkspace();
    }

    public function permission(User $user): ?CreatorPermission
    {
        return $user->creatorPermission;
    }

    public function canSubmitDrafts(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $permission = $this->permission($user);
        if ($permission) {
            return $permission->allows('draft_submission_enabled');
        }

        return $user->hasCreatorWorkspace();
    }

    public function can(User $user, string $capability): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (bool) $this->permission($user)?->allows($capability);
    }

    public function isVerified(User $user): bool
    {
        return $user->isAdmin() || $user->isVerifiedCreator();
    }

    public function canPublish(User $user): bool
    {
        return $user->isAdmin() || $this->can($user, 'publish_without_review');
    }

    public function canMonetize(User $user): bool
    {
        return $user->isAdmin()
            || ($this->isVerified($user)
                && ($this->can($user, 'set_rental_price')
                    || $this->can($user, 'set_purchase_price')
                    || $this->can($user, 'select_subscription_plans')));
    }

    public function canWithdraw(User $user): bool
    {
        $permission = $this->permission($user);

        return $user->isAdmin() || (
            $this->isVerified($user)
            && $permission?->allows('monetization_enabled')
            && $permission?->allows('request_withdrawal')
            && (! $permission->withdrawal_hold_until || $permission->withdrawal_hold_until->isPast())
        );
    }
}
