<?php

namespace APIRouter\Interfaces;

use APIRouter\Http\Request;

interface AuthenticatorInterface
{
    /**
     * Authenticate the request.
     *
     * @param Request $request
     * @return mixed|null Returns the authenticated user object/array/id, or null if authentication fails.
     */
    public function authenticate(Request $request);
}
