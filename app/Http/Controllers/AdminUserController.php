<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::with('roles', 'factory')
            ->orderBy('is_approved')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => ['super_admin', 'admin', 'inspector', 'user'],
        ]);
    }

    public function approve(User $user, Request $request)
    {
        $request->validate(['role' => 'required|in:super_admin,admin,inspector,user']);

        $user->syncRoles([$request->role]);
        $user->update([
            'is_approved' => true,
            'requested_role' => $request->role,
        ]);

        return back()->with('flash', ['success' => "{$user->name} diaktifkan sebagai {$request->role}."]);
    }

    public function updateRole(User $user, Request $request)
    {
        $request->validate(['role' => 'required|in:super_admin,admin,inspector,user']);

        $user->syncRoles([$request->role]);

        return back()->with('flash', ['success' => "Role {$user->name} diubah menjadi {$request->role}."]);
    }

    public function deactivate(User $user)
    {
        $user->update(['is_approved' => false]);

        return back()->with('flash', ['success' => "{$user->name} dinonaktifkan."]);
    }
}
