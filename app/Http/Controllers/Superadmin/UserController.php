<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return view('superadmin.users', [
            'users' => User::with('roles')->latest()->paginate(25),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = ['roles' => $user->roles->pluck('code'), 'is_active' => $user->is_active];
        $user->roles()->sync($data['roles']);
        $user->update(['is_active' => $request->boolean('is_active')]);
        $audit->log('user.access_updated', $user, $old, ['roles' => $user->fresh('roles')->roles->pluck('code'), 'is_active' => $user->is_active]);

        return back()->with('success', 'Role dan status akun diperbarui.');
    }
}
