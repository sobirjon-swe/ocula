<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;

final class DuplicateProducts
{
    /** @return list<array{left_id:int, left_name:string, right_id:int, right_name:string, type:string, similarity:string}> */
    public function pairs(): array
    {
        $pairs = DB::table('products as a')->join('products as b', function ($join): void {
            $join->on('a.id', '<', 'b.id')->on('a.type', '=', 'b.type');
        })
            ->whereNull('a.deleted_at')->whereNull('b.deleted_at')
            ->whereNull('a.merged_into_id')->whereNull('b.merged_into_id')
            ->where('a.is_active', true)->where('b.is_active', true)
            ->where('a.status', '!=', 'rejected')->where('b.status', '!=', 'rejected')
            ->where('a.search_key', '!=', '')
            ->whereRaw('a.search_key % b.search_key AND similarity(a.search_key, b.search_key) >= 0.45')
            ->selectRaw('a.id as left_id, a.name as left_name, b.id as right_id, b.name as right_name, a.type, similarity(a.search_key, b.search_key)::text as similarity')
            ->orderByRaw('similarity(a.search_key, b.search_key) DESC')->orderBy('a.id')->orderBy('b.id')->limit(100)
            ->get()->map(fn (object $row): array => [
                'left_id' => (int) $row->left_id, 'left_name' => (string) $row->left_name,
                'right_id' => (int) $row->right_id, 'right_name' => (string) $row->right_name,
                'type' => (string) $row->type, 'similarity' => (string) $row->similarity,
            ])->values()->all();

        return array_values($pairs);
    }
}
