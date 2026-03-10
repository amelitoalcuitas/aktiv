<?php

namespace App\Policies;

use App\Models\Hub;
use App\Models\User;

class HubPolicy
{
    public function update(User $user, Hub $hub): bool
    {
        return $user->id === $hub->owner_id;
    }

    public function delete(User $user, Hub $hub): bool
    {
        return $user->id === $hub->owner_id;
    }
}
