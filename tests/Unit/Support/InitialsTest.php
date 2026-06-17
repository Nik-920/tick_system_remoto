<?php

namespace Tests\Unit\Support;

use App\Support\Initials;
use Tests\TestCase;

class InitialsTest extends TestCase
{
    public function test_null_returns_fallback(): void
    {
        $this->assertSame('?', Initials::from(null));
    }

    public function test_empty_string_returns_fallback(): void
    {
        $this->assertSame('?', Initials::from(''));
    }

    public function test_whitespace_only_returns_fallback(): void
    {
        $this->assertSame('?', Initials::from('   '));
    }

    public function test_single_word_returns_first_letter(): void
    {
        $this->assertSame('J', Initials::from('Juan'));
    }

    public function test_two_words_limit_two(): void
    {
        $this->assertSame('JP', Initials::from('Juan Pérez'));
    }

    public function test_three_words_limit_two(): void
    {
        $this->assertSame('JP', Initials::from('Juan Pablo García', 2));
    }

    public function test_limit_one(): void
    {
        $this->assertSame('J', Initials::from('Juan Pérez', 1));
    }

    public function test_custom_fallback(): void
    {
        $this->assertSame('U', Initials::from(null, 2, 'U'));
    }

    public function test_fallback_is_uppercased(): void
    {
        $this->assertSame('U', Initials::from('', 2, 'u'));
    }

    public function test_result_is_uppercased(): void
    {
        $this->assertSame('JP', Initials::from('juan pérez'));
    }

    public function test_multibyte_name(): void
    {
        $this->assertSame('AÑ', Initials::from('Ana Ñoño'));
    }

    public function test_single_char_name(): void
    {
        $this->assertSame('J', Initials::from('J'));
    }

    public function test_extra_spaces_between_words(): void
    {
        $this->assertSame('JP', Initials::from('Juan   Pérez'));
    }
}
