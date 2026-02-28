<?php

namespace APIRouter\Interfaces;

interface PermissionsInterface
{
    /**
     * Check if the authenticated user has the required permission.
     *
     * @param mixed $user The authenticated user context
     * @param string $permission The permission string to check
     * @return bool
     */
    public function hasPermission($user, string $permission): bool;
}
