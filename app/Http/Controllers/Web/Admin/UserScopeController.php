<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domains\Geography\Models\Lga;
use App\Domains\Identity\Actions\AssignUserScopeAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Domains\Operators\Models\Operator;
use App\Domains\Parks\Models\Park;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignScopeRequest;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UserScopeController extends Controller
{
    public function edit(User $user, UserAccessScopeService $scopes)
    {
        Gate::authorize('assignScopes', $user);

        return Inertia::render('Admin/Users/Scopes', ['user' => $user->only('public_id', 'name'), 'scopes' => $scopes->summary($user), 'options' => [
            'lgas' => Lga::orderBy('name')->get(['id', 'name']), 'parks' => Park::orderBy('name')->get(['id', 'name']), 'operators' => Operator::orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function update(AssignScopeRequest $request, User $user, AssignUserScopeAction $action)
    {
        $action->execute($request->user(), $user, $request->validated());

        return back()->with('success', 'Access scopes updated.');
    }
}
