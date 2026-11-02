<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Core\Models\Location;
use App\Modules\Warehouse\Actions\Inventory\ManageInventory;
use App\Modules\Warehouse\Http\Requests\InventoryRequest;
use App\Modules\Warehouse\Models\Inventory;
use App\Support\Http\ApiController;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryController extends ApiController
{
    private function permit(Request $request, string $ability): void
    {
        abort_unless($this->currentUser($request)->can('warehouse.inventory.'.$ability), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $this->currentUser($request);
        abort_unless($actor->can('warehouse.inventory.view_any') || $actor->can('warehouse.inventory.count'), 403);
        $page = Inventory::query()->latest('id')->paginate($this->perPage($request));

        return response()->json($page);
    }

    public function show(Request $request, Inventory $inventory): JsonResponse
    {
        $actor = $this->currentUser($request);
        abort_unless($actor->can('warehouse.inventory.view_any') || $actor->can('warehouse.inventory.count'), 403);

        return ApiResponse::data($this->payload($inventory, $request));
    }

    public function store(InventoryRequest $request, ManageInventory $action): JsonResponse
    {
        $this->permit($request, 'start');
        $location = Location::findOrFail($request->integer('location_id'));

        return ApiResponse::created($this->payload($action->start($this->currentUser($request), $location), $request));
    }

    public function counts(InventoryRequest $request, Inventory $inventory, ManageInventory $action): JsonResponse
    {
        $this->permit($request, 'count');

        return ApiResponse::data($this->payload($action->count($this->currentUser($request), $inventory, $request->validated('items')), $request));
    }

    public function review(Request $request, Inventory $inventory, ManageInventory $action): JsonResponse
    {
        $this->permit($request, 'review');

        return ApiResponse::data($this->payload($action->transition($this->currentUser($request), $inventory, 'review'), $request));
    }

    public function complete(Request $request, Inventory $inventory, ManageInventory $action): JsonResponse
    {
        $this->permit($request, 'complete');

        return ApiResponse::data($this->payload($action->transition($this->currentUser($request), $inventory, 'completed'), $request));
    }

    public function cancel(Request $request, Inventory $inventory, ManageInventory $action): JsonResponse
    {
        $this->permit($request, 'start');

        return ApiResponse::data($action->transition($this->currentUser($request), $inventory, 'cancelled')->toArray());
    }

    /** @return array<string,mixed> */
    private function payload(Inventory $inventory, Request $request): array
    {
        $data = $inventory->load('items.variant')->toArray();
        if (! $this->currentUser($request)->can('catalog.cost.view')) {
            $data['items'] = $inventory->items->map(function ($item): array {
                $row = $item->toArray();
                unset($row['unit_cost']);

                return $row;
            })->all();
        }

        return $data;
    }
}
