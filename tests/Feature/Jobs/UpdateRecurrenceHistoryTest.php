<?php

namespace Tests\Feature\Jobs;

use App\Jobs\UpdateRecurrenceHistory;
use App\Models\Category;
use App\Models\Location;
use App\Models\LocationIncidentHistory;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateRecurrenceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_updates_recurrence_history_for_resolved_ticket(): void
    {
        config(['ai.recurrence.enabled' => true]);

        $resolvedAt = Carbon::parse('2026-05-22 10:00:00');
        $createdAt = Carbon::parse('2026-05-22 09:30:00');

        $ticket = $this->createTicket($createdAt, $resolvedAt);

        $job = new UpdateRecurrenceHistory($ticket, 'corr-rec-001');
        $job->handle();

        $history = LocationIncidentHistory::where('location_id', '=', $ticket->location_id, 'and')
            ->where('category_id', '=', $ticket->category_id, 'and')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame(1, $history->recurrence_count);
        $this->assertSame('00:30:00', $history->avg_resolution_time);
        $lastResolvedAtRaw = $history->last_resolved_at;
        $this->assertNotNull($lastResolvedAtRaw);
        $lastResolvedAt = $lastResolvedAtRaw instanceof \DateTimeInterface
            ? $lastResolvedAtRaw
            : Carbon::parse($lastResolvedAtRaw);
        $this->assertSame(
            $resolvedAt->format('Y-m-d H:i:s'),
            $lastResolvedAt->format('Y-m-d H:i:s')
        );
    }

    private function createTicket(Carbon $createdAt, Carbon $resolvedAt): Ticket
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Aula Rec',
            'building' => 'Edificio R',
            'floor' => '1',
            'room_code' => 'R-101',
            'qr_token' => 'qr-r-101',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Electricidad',
            'icon' => 'bolt',
            'description' => 'Categoria rec',
        ]);

        $ticketId = (string) Str::uuid();

        DB::table('tickets')->insert([
            'id' => $ticketId,
            'title' => 'Ticket resuelto',
            'description' => 'Descripcion',
            'reporter_id' => $user->id,
            'assigned_to' => null,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'resolved',
            'priority' => 'medium',
            'resolved_at' => $resolvedAt->format('Y-m-d H:i:s'),
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);

        return Ticket::query()->findOrFail($ticketId);
    }
}
