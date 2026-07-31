<?php

namespace App\Policies;

use App\Models\Episode;
use App\Models\User;

class EpisodePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Episode $episode): bool
    {
        $show = $episode->season?->tvShow;

        return $show && app(TVShowPolicy::class)->view($user, $show);
    }

    public function update(User $user, Episode $episode): bool
    {
        return $this->view($user, $episode)
            && ! in_array($episode->publication_status, ['suspended', 'archived'], true);
    }

    public function delete(User $user, Episode $episode): bool
    {
        return $this->update($user, $episode);
    }
}
