<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('Admin/Roles/Index', ['roles' => Role::with('permissions:id,name')->withCount('users')->orderBy('name')->get()]);
    }

    public function edit(Role $role)
    {
        Gate::authorize('update', $role);

        return Inertia::render('Admin/Roles/Edit', ['role' => $role->load('permissions:id,name'), 'permissions' => Permission::orderBy('name')->get(['id', 'name'])]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        DB::transaction(function () use ($request, $role) {
            $role->syncPermissions($request->validated('permissions'));
            activity()->causedBy($request->user())->performedOn($role)->withProperties(['permissions' => $request->validated('permissions')])->log('role_permissions_changed');
        });

        return back()->with('success', 'Role permissions updated.');
    }

    public function matrix()
    {
        Gate::authorize('viewAny', Role::class);

        return Inertia::render('Admin/Permissions/Matrix', ['roles' => Role::with('permissions:id,name')->orderBy('name')->get(), 'permissions' => Permission::orderBy('name')->get(['id', 'name'])]);
    }
}
