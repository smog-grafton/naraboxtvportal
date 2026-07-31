<?php

namespace App\Policies;

use App\Models\TVShow;
use App\Models\User;

class TVShowPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasCreatorWorkspace();
    }

    public function view(User $user, TVShow $show): bool
    {
        return $this->owns($user, $show);
    }

    public function create(User $user): bool
    {
        return $user->hasCreatorWorkspace()
            && ($user->creatorPermission?->draft_submission_enabled ?? true);
    }

    public function update(User $user, TVShow $show): bool
    {
        return $this->owns($user, $show)
            && ! in_array($show->publication_status, ['suspended', 'archived'], true);
    }

    public function delete(User $user, TVShow $show): bool
    {
        return $this->update($user, $show);
    }

    private function owns(User $user, TVShow $show): bool
    {
        return $show->submitted_by === $user->id
            || ($user->vjProfile && $show->vj_id === $user->vjProfile->id)
            || ($user->mediaLibraryProfile && $show->media_library_id === $user->mediaLibraryProfile->id);
    }
}
