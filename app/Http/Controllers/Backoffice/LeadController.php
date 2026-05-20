<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\Leads\BulkAssignLeadsAction;
use App\Actions\Leads\DeleteLeadAction;
use App\Actions\Leads\ListLeadsAction;
use App\Actions\Leads\StoreLeadAction;
use App\Actions\Leads\UpdateLeadAction;
use App\Data\Leads\BulkAssignData;
use App\Data\Leads\LeadFilters;
use App\Data\Leads\StoreLeadData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Backoffice\BulkAssignRequest;
use App\Http\Requests\Backoffice\LeadRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request, ListLeadsAction $action): Response
    {
        $filters = LeadFilters::fromRequest($request);
        $sort = $request->string('sort')->toString() ?: null;
        $direction = $request->string('dir')->toString() ?: 'desc';

        $leads = $action->execute(
            filters: $filters,
            sort: $sort,
            direction: $direction,
        );

        return Inertia::render('backoffice/leads/index', [
            'leads' => $leads,
            'filters' => $filters->toArray(),
            'sort' => ['column' => $sort, 'direction' => $direction],
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('backoffice/leads/create', [
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    public function store(LeadRequest $request, StoreLeadAction $action): RedirectResponse
    {
        $action->execute(StoreLeadData::fromRequest($request));

        return redirect()
            ->route('backoffice.leads.index')
            ->with('success', 'Lead created.');
    }

    public function show(Lead $lead): Response
    {
        $lead->load([
            'assignedTo:id,name',
            'callLogs' => fn ($query) => $query->with('user:id,name')->orderByDesc('called_at'),
            'reminders' => fn ($query) => $query->with('user:id,name')->orderByDesc('remind_at'),
        ]);

        return Inertia::render('backoffice/leads/show', [
            'lead' => $lead,
        ]);
    }

    public function edit(Lead $lead): Response
    {
        return Inertia::render('backoffice/leads/edit', [
            'lead' => $lead,
            'assignableUsers' => $this->assignableUsers(),
        ]);
    }

    public function update(LeadRequest $request, Lead $lead, UpdateLeadAction $action): RedirectResponse
    {
        $action->execute($lead, StoreLeadData::fromRequest($request));

        return redirect()
            ->route('backoffice.leads.index')
            ->with('success', 'Lead updated.');
    }

    public function destroy(Lead $lead, DeleteLeadAction $action): RedirectResponse
    {
        $action->execute($lead);

        return redirect()
            ->route('backoffice.leads.index')
            ->with('success', 'Lead deleted.');
    }

    public function bulkAssign(BulkAssignRequest $request, BulkAssignLeadsAction $action): RedirectResponse
    {
        $count = $action->execute(BulkAssignData::fromRequest($request));

        return redirect()
            ->route('backoffice.leads.index')
            ->with('success', "Assigned {$count} leads.");
    }

    /** @return Collection<int, User> */
    private function assignableUsers()
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }
}
