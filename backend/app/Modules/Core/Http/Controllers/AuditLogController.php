<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Controllers;

use App\Support\Http\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

final class AuditLogController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $actor = $this->currentUser($request);
        abort_unless($actor->can('core.audit.view'), 403);
        $query = Activity::query()->where('log_name', 'domain')
            ->when(! $actor->canAccessAllBranches(), fn ($q) => $q->whereIn('properties->branch_id', $actor->accessibleBranchIds()))
            ->when($request->filled('event'), fn ($q) => $q->where('event', $request->string('event')->toString()));

        return response()->json($query->latest('id')->paginate($this->perPage($request)));
    }
}
