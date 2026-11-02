<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

final class DomainAudit
{
    /** @param array<int, Model> $models */
    public function record(string $event, array $models): void
    {
        $model = $models[0] ?? null;
        if (! $model instanceof Model || ! str_starts_with($model::class, 'App\\Modules\\')) {
            return;
        }
        $verb = explode(':', $event)[0];
        $verb = substr($verb, strlen('eloquent.'));
        $hidden = array_unique([...$model->getHidden(), 'password', 'pin_hash', 'token', 'remember_token', 'code_hash', 'secret', 'init_data']);
        $changed = $verb === 'updated' ? $model->getChanges() : $model->getAttributes();
        $attributes = array_diff_key($changed, array_flip($hidden));
        $old = [];
        if ($verb === 'updated') {
            foreach ($attributes as $key => $value) {
                $old[$key] = $model->getRawOriginal($key);
            }
        }
        $logger = activity('domain')->performedOn($model)->event($verb)->withProperties([
            'attributes' => $attributes, 'old' => $old,
            'branch_id' => $model->getAttribute('branch_id'),
            'hidden_fields_changed' => array_values(array_intersect(array_keys($changed), $hidden)),
        ]);
        $actor = Auth::user();
        if ($actor instanceof Model) {
            $logger->causedBy($actor);
        }
        $logger->log(class_basename($model).' '.$verb);
    }
}
