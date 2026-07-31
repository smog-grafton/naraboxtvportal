<?php

namespace App\Policies;

use App\Models\CreatorVerificationEvidence;
use App\Models\User;

class CreatorVerificationEvidencePolicy
{
    public function view(User $user, CreatorVerificationEvidence $evidence): bool
    {
        return $user->isAdmin() || $evidence->user_id === $user->id;
    }

    public function delete(User $user, CreatorVerificationEvidence $evidence): bool
    {
        return $evidence->user_id === $user->id
            && $evidence->status === 'submitted'
            && ! $evidence->reviewed_at;
    }
}
