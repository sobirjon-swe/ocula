<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Support\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;

/**
 * `Immutable` trait'ini sinash uchun soxta model.
 *
 * Haqiqiy insert-only jadvallar (`stock_movements`, `payments`, ...)
 * Bosqich 2 da paydo bo'ladi (SCHEMA.md §0) — trait esa hozir yozilgan,
 * shuning uchun uni vaqtinchalik test jadvali ustida tekshiramiz.
 */
class ImmutableRecord extends Model
{
    use Immutable;

    protected $table = 'immutable_records';

    /** @var list<string> */
    protected $fillable = ['note'];
}
