<?php

declare(strict_types=1);

namespace App\Modules\Core\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class AssignBranchesRequest extends FormRequest
{
    /** @return array<string,array<int,mixed>> */
    public function rules(): array
    {
        return ['branch_ids' => ['present', 'array'], 'branch_ids.*' => ['required', 'integer', 'distinct', 'exists:branches,id']];
    }
}
