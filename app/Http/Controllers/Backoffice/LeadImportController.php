<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\Leads\ImportLeadsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LeadImportController extends Controller
{
    public function store(Request $request, ImportLeadsAction $action): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $result = $action->execute($request->file('file'));

        $message = sprintf(
            'Imported %d lead%s%s.',
            $result->imported,
            $result->imported === 1 ? '' : 's',
            $result->skipped === [] ? '' : sprintf(' (%d skipped)', count($result->skipped)),
        );

        return redirect()
            ->route('backoffice.leads.index')
            ->with('success', $message)
            ->with('importSummary', $result->toArray());
    }
}
