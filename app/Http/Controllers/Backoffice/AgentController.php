<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backoffice;

use App\Actions\Agents\ShowAgentDetailAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AgentController extends Controller
{
    public function show(User $agent, ShowAgentDetailAction $action): Response
    {
        if (! $agent->hasRole(UserRole::AGENT->value)) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('backoffice/agents/show', [
            'agent' => $action->execute($agent)->toArray(),
        ]);
    }
}
