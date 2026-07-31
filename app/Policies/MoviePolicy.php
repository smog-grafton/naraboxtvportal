<?php

namespace App\Policies;

use App\Models\Movie;
use App\Models\User;

class MoviePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasCreatorWorkspace();
    }

    public function view(User $user, Movie $movie): bool
    {
        return $this->owns($user, $movie);
    }

    public function create(User $user): bool
    {
        return $user->hasCreatorWorkspace()
            && ($user->creatorPermission?->draft_submission_enabled ?? true);
    }

    public function update(User $user, Movie $movie): bool
    {
        return $this->owns($user, $movie)
            && ! in_array($movie->publication_status, ['suspended', 'archived'], true);
    }

    public function delete(User $user, Movie $movie): bool
    {
        return $this->update($user, $movie);
    }

    private function owns(User $user, Movie $movie): bool
    {
        return $movie->submitted_by === $user->id
            || ($user->vjProfile && $movie->vj_id === $user->vjProfile->id)
            || ($user->mediaLibraryProfile && $movie->media_library_id === $user->mediaLibraryProfile->id);
    }
}
