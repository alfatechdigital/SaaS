<?php

namespace Tests\Feature\Domain;

use App\Models\Project;
use App\Models\Transaction;

class TransactionTest extends DomainTestCase
{
    public function test_owner_can_create_an_income_transaction(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('transactions.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'team_id' => $this->team->id,
            'category' => 'project_income',
            'type' => 'income',
            'amount' => 1_500_000,
            'created_by_id' => $this->owner->id,
        ]);
    }

    public function test_type_is_derived_from_the_category(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('transactions.store'), $this->payload(['category' => 'company_expense']))
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'category' => 'company_expense',
            'type' => 'expense',
        ]);
    }

    public function test_owner_can_update_a_transaction(): void
    {
        $transaction = Transaction::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('transactions.update', ['transaction' => $transaction->id]), $this->payload(['amount' => 2_000_000]))
            ->assertRedirect();

        $this->assertSame(2_000_000, $transaction->fresh()?->amount);
    }

    public function test_owner_can_delete_a_transaction(): void
    {
        $transaction = Transaction::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('transactions.destroy', ['transaction' => $transaction->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    public function test_transaction_can_be_linked_to_a_project_of_the_same_team(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('transactions.store'), $this->payload(['project_id' => $project->id]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('transactions', ['project_id' => $project->id]);
    }

    public function test_amount_must_be_positive(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('transactions.store'), $this->payload(['amount' => 0]))
            ->assertSessionHasErrors('amount');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'category' => 'project_income',
            'project_id' => null,
            'description' => 'Pembayaran termin 1',
            'amount' => 1_500_000,
            'date' => '2025-01-15',
            ...$overrides,
        ];
    }
}
