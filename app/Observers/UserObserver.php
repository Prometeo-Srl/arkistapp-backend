<?php

namespace App\Observers;

use App\Models\AccessGrant;
use App\Models\User;

class UserObserver
{
    /**
     * Documents shared with the address before it had an account are handed over the
     * moment the account exists — sharing never asks the recipient to accept anything.
     */
    public function created(User $user): void
    {
        AccessGrant::claimFor($user);
    }
}
