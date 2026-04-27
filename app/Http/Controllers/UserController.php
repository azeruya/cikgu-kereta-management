<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();

        $users = User::where('branch_id', $admin->branch_id)
            ->select('id', 'name', 'email', 'role', 'branch_id', 'created_at')
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $admin = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'branch_id' => $admin->branch_id,
        ]);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user->only(['id', 'name', 'email', 'role', 'branch_id']),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();

        $user = User::where('branch_id', $admin->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $user->only(['id', 'name', 'email', 'role', 'branch_id']),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();

        $user = User::where('branch_id', $admin->branch_id)
            ->where('id', $id)
            ->firstOrFail();

        if ($user->id === $admin->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.'
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.'
        ]);
    }
}