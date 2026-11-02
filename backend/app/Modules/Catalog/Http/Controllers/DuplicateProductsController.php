<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Services\DuplicateProducts;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DuplicateProductsController extends ApiController
{
    public function __invoke(Request $request, DuplicateProducts $duplicates): JsonResponse
    {
        $user = $this->currentUser($request);
        abort_unless($user->can('catalog.product.approve') || $user->can('catalog.product.merge'), 403);

        return ApiResponse::data($duplicates->pairs());
    }
}
