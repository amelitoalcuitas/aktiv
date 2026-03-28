<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\Hub;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'total_hubs'   => Hub::query()->count(),
            'active_hubs'  => Hub::query()->where('is_active', true)->count(),
            'total_users'  => User::query()->where('role', '!=', UserRole::SuperAdmin)->count(),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'first_name'     => $validated['first_name'],
            'last_name'      => $validated['last_name'],
            'email'          => $validated['email'],
            'username'       => User::generateUsername($validated['first_name'], $validated['last_name']),
            'password'       => Str::random(32),
            'role'           => UserRole::from($validated['role'] ?? 'user'),
            'contact_number' => $validated['contact_number'] ?? null,
        ]);

        $token = Password::broker('onboarding')->createToken($user);
        $user->notify(new AccountCreatedNotification($token));

        return response()->json($this->formatUser($user->loadCount('hubs')), 201);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', '!=', UserRole::SuperAdmin)
            ->withCount('hubs')
            ->orderBy('created_at', 'desc');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(first_name || \' \' || last_name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(username) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        $users = $query->paginate(20)->through(fn (User $user) => $this->formatUser($user));

        return response()->json($users);
    }

    public function verifyEmail(User $user): JsonResponse
    {
        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json($this->formatUser($user->loadCount('hubs')));
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:user,admin'],
        ]);

        $user->forceFill(['role' => UserRole::from($validated['role'])])->save();

        return response()->json($this->formatUser($user->loadCount('hubs')));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->role === UserRole::SuperAdmin, 403, 'Cannot delete a super admin.');
        abort_if($user->id === $request->user()->id, 403, 'Cannot delete yourself.');

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'             => $user->id,
            'name'           => $user->name,
            'username'       => $user->username,
            'email'          => $user->email,
            'role'           => $user->role->value,
            'email_verified' => $user->email_verified_at !== null,
            'is_disabled'    => $user->is_disabled,
            'hubs_count'     => $user->hubs_count,
            'created_at'     => $user->created_at,
        ];
    }
}
