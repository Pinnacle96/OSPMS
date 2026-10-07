<?php

namespace App\Http\Controllers\Web\Admin;

use App\Domains\Identity\Actions\CreateUserAction;
use App\Domains\Identity\Actions\UpdateUserAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Queries\UserListQuery;
use App\Domains\Identity\Services\UserAccessScopeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UserFilterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(UserFilterRequest $request, UserListQuery $query)
    {
        $users = $query->paginate($request->user(), $request->validated());
        $users->through(fn (User $user) => [...$user->only('public_id', 'name', 'email', 'username', 'status', 'last_login_at', 'created_at'), 'roles' => $user->roles->map->only('id', 'name')]);

        return Inertia::render('Admin/Users/Index', ['users' => $users, 'filters' => $request->validated(), 'roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', ['roles' => $this->roles($request)]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action)
    {
        $user = $action->execute($request->user(), $request->validated());

        return redirect()->route('admin.users.show', $user)->with('success', 'User account created.');
    }

    public function show(User $user, UserAccessScopeService $scopes)
    {
        Gate::authorize('view', $user);

        return Inertia::render('Admin/Users/Show', [
            'user' => [...$user->only('public_id', 'name', 'email', 'username', 'phone', 'status', 'last_login_at', 'created_at'), 'roles' => $user->roles->map->only('id', 'name')],
            'scopes' => $scopes->summary($user),
            'activities' => $user->loginActivities()->latest('occurred_at')->limit(15)->get(['event', 'occurred_at', 'ip_address']),
        ]);
    }

    public function edit(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        return Inertia::render('Admin/Users/Edit', ['user' => [...$user->only('public_id', 'name', 'email', 'username', 'phone', 'status', 'must_change_password'), 'roles' => $user->roles->map->only('id', 'name')], 'roles' => $this->roles($request)]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action)
    {
        $action->execute($request->user(), $user, $request->validated());

        return redirect()->route('admin.users.show', $user)->with('success', 'User account updated.');
    }

    private function roles(Request $request): array
    {
        return $request->user()->can('manage_roles') ? Role::orderBy('name')->pluck('name')->all() : [];
    }
}
