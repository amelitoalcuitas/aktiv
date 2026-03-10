<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\Hub;
use App\Models\User;

class CourtPolicy
{
    public function create(User $user, Hub $hub): bool
    {
        return $user->id === $hub->owner_id;
    }

    public function update(User $user, Court $court): bool
    {
        return $user->id === $court->hub->owner_id;
    }

    public function delete(User $user, Court $court): bool
    {
        return $user->id === $court->hub->owner_id;
    }
}
