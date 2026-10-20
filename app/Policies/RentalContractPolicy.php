<?php

namespace App\Policies;

use App\Models\RentalContract;
use App\Models\User;

/** Only the two parties see the contract; admins see the audit record only. */
class RentalContractPolicy
{
    public function view(User $user, RentalContract $c): bool { return $c->roleOf($user) !== null; }

    public function update(User $user, RentalContract $c): bool { return $c->roleOf($user) === 'landlord' && $c->isEditable(); }

    public function send(User $user, RentalContract $c): bool { return $c->roleOf($user) === 'landlord' && $c->status === 'signed_landlord'; }

    public function cancel(User $user, RentalContract $c): bool { return $c->roleOf($user) !== null && $c->isEditable(); }

    public function audit(User $user, RentalContract $c): bool { return $user->isAdmin() || $this->view($user, $c); }
}
