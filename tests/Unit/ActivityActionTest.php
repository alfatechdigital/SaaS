<?php

namespace Tests\Unit;

use App\Enums\ActivityAction;
use PHPUnit\Framework\TestCase;

class ActivityActionTest extends TestCase
{
    public function test_it_recovers_the_action_from_a_stored_sentence(): void
    {
        $this->assertSame(ActivityAction::Created, ActivityAction::fromActionString('Menambahkan proyek'));
        $this->assertSame(ActivityAction::Updated, ActivityAction::fromActionString('Memperbarui lead'));
        $this->assertSame(ActivityAction::Deleted, ActivityAction::fromActionString('Menghapus transaksi'));
    }

    public function test_an_unrecognised_sentence_is_not_classified(): void
    {
        $this->assertNull(ActivityAction::fromActionString('Konversi prospek jadi proyek'));
        $this->assertNull(ActivityAction::fromActionString(''));
    }

    public function test_verbs_and_states_are_distinct(): void
    {
        $verbs = array_map(fn (ActivityAction $action) => $action->verb(), ActivityAction::cases());
        $states = array_map(fn (ActivityAction $action) => $action->state(), ActivityAction::cases());

        $this->assertSame($verbs, array_unique($verbs));
        $this->assertSame($states, array_unique($states));
    }
}
