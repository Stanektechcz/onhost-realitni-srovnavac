<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    public function view(?User $user, Listing $listing): bool
    {
        if ($listing->source === 'imported') {
            return false; // imported data is never shown as a listing page, even to admins
        }

        return $listing->isPublic() || ($user && ($user->id === $listing->user_id || $user->isAdmin()));
    }

    public function create(User $user): bool { return $user->canAdvertise() && ! $user->is_banned; }

    public function update(User $user, Listing $listing): bool
    {
        return $listing->source === 'internal' && ($user->id === $listing->user_id || $user->isAdmin());
    }

    public function delete(User $user, Listing $listing): bool { return $this->update($user, $listing); }

    public function moderate(User $user): bool { return $user->isAdmin(); }
}
