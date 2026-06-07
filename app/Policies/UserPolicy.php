<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Only admins can list all users. */
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    /** Admins can view any user; users can view their own profile. */
    public function view(User $user, User $model): bool
    {
        return $user->is_admin || $user->id === $model->id;
    }

    /** Only admins can create new users. */
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    /** Admins can edit any user; users can edit their own profile. */
    public function update(User $user, User $model): bool
    {
        return $user->is_admin || $user->id === $model->id;
    }

    /** Only admins can delete users. */
    public function delete(User $user, User $model): bool
    {
        return $user->is_admin;
    }

    /** Only admins can restore users. */
    public function restore(User $user, User $model): bool
    {
        return $user->is_admin;
    }

    /** Only admins can force-delete users. */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->is_admin;
    }
}
