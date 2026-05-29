<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketAssignmentFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_fields_default_to_null_or_false(): void
    {
        $ticket = $this->createTicket();

        $this->assertNull($ticket->assigned_by);
        $this->assertNull($ticket->assigned_at);
        $this->assertNull($ticket->assignment_source);
        $this->assertFalse($ticket->assignment_locked);
        $this->assertIsBool($ticket->assignment_locked);
    }

    public function test_assignment_fields_are_persisted_and_casted(): void
    {
        $ticket = $this->createTicket();
        $actor = User::factory()->create();

        $ticket->fill([
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'assignment_locked' => true,
            'assignment_source' => Ticket::ASSIGNMENT_SOURCE_SELF,
        ])->save();

        $fresh = Ticket::query()->findOrFail($ticket->id);

        $this->assertSame($actor->id, $fresh->assigned_by);
        $this->assertNotNull($fresh->assigned_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->assigned_at);
        $this->assertTrue($fresh->assignment_locked);
        $this->assertSame(Ticket::ASSIGNMENT_SOURCE_SELF, $fresh->assignment_source);

        $fresh->assignment_source = Ticket::ASSIGNMENT_SOURCE_ADMIN;
        $fresh->save();

        $reloaded = Ticket::query()->findOrFail($ticket->id);
        $this->assertSame(Ticket::ASSIGNMENT_SOURCE_ADMIN, $reloaded->assignment_source);
    }

    public function test_assignment_locked_normalizes_postgres_boolean_strings(): void
    {
        $ticket = $this->createTicket();

        DB::table('tickets')
            ->where('id', $ticket->id)
            ->update(['assignment_locked' => 't']);

        $fresh = Ticket::query()->findOrFail($ticket->id);
        $this->assertTrue($fresh->assignment_locked);

        DB::table('tickets')
            ->where('id', $ticket->id)
            ->update(['assignment_locked' => 'f']);

        $fresh = Ticket::query()->findOrFail($ticket->id);
        $this->assertFalse($fresh->assignment_locked);
    }

    private function createTicket(): Ticket
    {
        $user = User::factory()->create();
        $location = Location::create([
            'name' => 'Aula Asignacion',
            'building' => 'Edificio A',
            'floor' => '1',
            'room_code' => 'A-'.Str::upper(Str::random(6)),
            'qr_token' => 'qr-'.Str::uuid()->toString(),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Asignacion '.Str::lower(Str::random(6)),
            'icon' => 'check',
            'description' => 'Categoria asignacion',
        ]);

        return Ticket::create([
            'title' => 'Ticket asignacion',
            'description' => 'Descripcion de prueba para asignaciones.',
            'reporter_id' => $user->id,
            'location_id' => $location->id,
            'category_id' => $category->id,
            'state' => 'open',
            'priority' => 'medium',
        ]);
    }
}
