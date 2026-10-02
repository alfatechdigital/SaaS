<?php

namespace App\Http\Controllers;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\TeamPermission;
use App\Enums\TransactionCategory;
use App\Http\Requests\Transaction\SaveTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Project;
use App\Models\Transaction;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    use AuthorizesTeamModule;

    /**
     * Display the finance ledger.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageFinance);

        $transactions = Transaction::query()
            ->forTeam($team)
            ->with(['project:id,name', 'createdBy:id,name'])
            ->orderByDesc('date')
            ->get();

        $projects = Project::query()
            ->forTeam($team)
            ->orderBy('name')
            ->get(['id', 'name', 'client_name', 'project_value']);

        return Inertia::render('admin/finance/Index', [
            'transactions' => TransactionResource::collection($transactions),
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'clientName' => $project->client_name,
                'projectValue' => $project->project_value,
            ])->all(),
            'totals' => [
                'income' => (int) Transaction::query()->forTeam($team)->where('type', 'income')->sum('amount'),
                'expense' => (int) Transaction::query()->forTeam($team)->where('type', 'expense')->sum('amount'),
            ],
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(SaveTransactionRequest $request): RedirectResponse
    {
        $this->persist($request, new Transaction);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Transaksi ditambahkan.'),
        ]);

        return back();
    }

    /**
     * Update the specified transaction.
     */
    public function update(SaveTransactionRequest $request): RedirectResponse
    {
        $model = Transaction::query()
            ->forTeam($request->team())
            ->findOrFail((int) $request->route('transaction'));

        $this->persist($request, $model);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Transaksi diperbarui.'),
        ]);

        return back();
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        $this->authorizeModule($request->user(), $team, TeamPermission::ManageFinance);

        Transaction::query()
            ->forTeam($team)
            ->findOrFail((int) $request->route('transaction'))
            ->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Transaksi dihapus.'),
        ]);

        return back();
    }

    /**
     * Write the validated payload onto the given transaction.
     */
    private function persist(SaveTransactionRequest $request, Transaction $transaction): void
    {
        $data = $request->validated();

        $category = TransactionCategory::from($data['category']);

        $transaction->fill([
            'team_id' => $request->team()->id,
            'type' => $category->type(),
            'category' => $category,
            'project_id' => $data['project_id'] ?? null,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'date' => $data['date'],
        ]);

        if (! $transaction->exists) {
            $transaction->created_by_id = $request->actor()->id;
        }

        $transaction->save();
    }
}
