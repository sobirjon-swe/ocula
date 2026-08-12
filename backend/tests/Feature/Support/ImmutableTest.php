<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\Exceptions\ImmutableRecordException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\ImmutableRecord;
use Tests\TestCase;

/**
 * Insert-only yozuvlar — PROJECT.md 7.21, SCHEMA.md §0.
 *
 * Kafolat: `UPDATE` va `DELETE` model darajasida to'siladi, tuzatish
 * faqat storno orqali bo'ladi.
 */
final class ImmutableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('immutable_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('note');
            $table->timestamp('created_at')->nullable();
        });
    }

    #[Test]
    public function it_allows_inserting_a_record(): void
    {
        $record = ImmutableRecord::create(['note' => 'kirim']);

        $this->assertTrue($record->exists);
        $this->assertDatabaseHas('immutable_records', ['note' => 'kirim']);
    }

    #[Test]
    public function it_blocks_updating_a_record(): void
    {
        $record = ImmutableRecord::create(['note' => 'kirim']);

        $this->expectException(ImmutableRecordException::class);
        $this->expectExceptionMessage('ImmutableRecord yozuvi tahrirlanmaydi');

        $record->update(['note' => 'tahrir']);
    }

    #[Test]
    public function it_blocks_deleting_a_record(): void
    {
        $record = ImmutableRecord::create(['note' => 'kirim']);

        $this->expectException(ImmutableRecordException::class);
        $this->expectExceptionMessage("ImmutableRecord yozuvi o'chirilmaydi");

        $record->delete();
    }

    #[Test]
    public function it_has_no_updated_at_column(): void
    {
        $record = new ImmutableRecord;

        $this->assertNull($record->getUpdatedAtColumn());
    }
}
