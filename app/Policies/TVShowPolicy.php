<?php

namespace App\Policies;

use App\Models\TVShow;
use App\Models\User;

class TVShowPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TVShow $tvShow): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $tvShow);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCreator();
    }

    public function update(User $user, TVShow $tvShow): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $tvShow);
    }

    public function delete(User $user, TVShow $tvShow): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $tvShow);
    }

    public function manageSource(User $user, TVShow $tvShow): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $tvShow);
    }

    public function publish(User $user, TVShow $tvShow): bool
    {
        return $user->isAdmin() || $this->isOwner($user, $tvShow);
    }

    private function isOwner(User $user, TVShow $tvShow): bool
    {
        if ($user->isVJ()) {
            $vj = $user->vjProfile;
            if ($vj && $tvShow->vj_id === $vj->id) {
                return true;
            }
        }

        if ($user->isMediaLibrary()) {
            $library = $user->mediaLibraryProfile;
            if ($library && $tvShow->media_library_id === $library->id) {
                return true;
            }
        }

        return false;
    }
}
