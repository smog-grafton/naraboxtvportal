<?php

namespace App\Policies;

use App\Models\Movie;
use App\Models\User;

class MoviePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Movie $movie): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $movie);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCreator();
    }

    public function update(User $user, Movie $movie): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $movie);
    }

    public function delete(User $user, Movie $movie): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $movie);
    }

    public function manageSource(User $user, Movie $movie): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $movie);
    }

    public function publish(User $user, Movie $movie): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $movie);
    }

    private function isOwner(User $user, Movie $movie): bool
    {
        // VJ ownership via vj_id → vjs.user_id
        if ($user->isVJ()) {
            $vj = $user->vjProfile;
            if ($vj && $movie->vj_id === $vj->id) {
                return true;
            }
        }

        // Media Library ownership via media_library_id → media_libraries.user_id
        if ($user->isMediaLibrary()) {
            $library = $user->mediaLibraryProfile;
            if ($library && $movie->media_library_id === $library->id) {
                return true;
            }
        }

        return false;
    }
}
