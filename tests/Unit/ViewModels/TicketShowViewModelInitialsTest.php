<?php

namespace Tests\Unit\ViewModels;

use App\Models\Ticket;
use App\ViewModels\Tickets\TicketShowViewModel;
use Tests\TestCase;

/**
 * Pure-unit tests for TicketShowViewModel::initials().
 * Uses a minimal Ticket stub so the constructor is satisfied without a DB.
 */
class TicketShowViewModelInitialsTest extends TestCase
{
    private TicketShowViewModel $vm;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Ticket $ticket */
        $ticket = new Ticket;

        $this->vm = new TicketShowViewModel(
            ticket: $ticket,
            availableTransitions: [],
            isMaintenance: false,
            isAvailableForClaim: false,
            canEditOperational: false,
        );
    }

    public function test_two_chars_from_normal_name(): void
    {
        $this->assertSame('RE', $this->vm->initials('Reporter User', 2, 'U'));
    }

    public function test_one_char_from_name(): void
    {
        $this->assertSame('R', $this->vm->initials('Reporter User', 1, 'R'));
    }

    public function test_null_returns_fallback(): void
    {
        $this->assertSame('U', $this->vm->initials(null, 2, 'U'));
    }

    public function test_empty_string_returns_fallback(): void
    {
        $this->assertSame('T', $this->vm->initials('', 2, 'T'));
    }

    public function test_whitespace_only_returns_fallback(): void
    {
        $this->assertSame('A', $this->vm->initials('   ', 2, 'A'));
    }

    public function test_single_char_name(): void
    {
        $this->assertSame('J', $this->vm->initials('J', 2, 'U'));
    }

    public function test_uppercase_is_applied(): void
    {
        $this->assertSame('AN', $this->vm->initials('ana pérez', 2, 'U'));
    }

    public function test_length_respected_for_short_name(): void
    {
        $this->assertSame('AL', $this->vm->initials('Al', 2, 'U'));
    }

    public function test_default_fallback_is_u(): void
    {
        $this->assertSame('U', $this->vm->initials(null));
    }
}
