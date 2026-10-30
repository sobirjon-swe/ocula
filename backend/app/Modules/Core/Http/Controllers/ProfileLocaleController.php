<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Http\Requests\Auth\UpdateLocaleRequest;
use App\Modules\Core\Http\Resources\UserResource;
use App\Support\Http\ApiController;

final class ProfileLocaleController extends ApiController
{
    public function __invoke(UpdateLocaleRequest $request): UserResource
    {
        $user = $this->currentUser($request);
        $user->update($request->safe()->only('locale'));

        return new UserResource($user);
    }
}
