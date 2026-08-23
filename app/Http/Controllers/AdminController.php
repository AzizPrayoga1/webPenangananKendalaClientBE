<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // ─── Helper: log admin action ────────────────────────────────────────────────
    private function logAction(Request $request, string $action, ?int $targetUserId = null, array $details = []): void
    {
        AdminActivityLog::create([
            'admin_id'       => $request->user()->id,
            'action'         => $action,
            'target_user_id' => $targetUserId,
            'details'        => $details,
        ]);
    }

    // ─── GET /admin/users ─────────────────────────────────────────────────────────
    public function getUsers(Request $request)
    {
        $users = User::orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        return response()->json($users);
    }

    // ─── POST /admin/users ────────────────────────────────────────────────────────
    public function createUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'role'     => ['required', Rule::in(['service_desk', 'project_manager', 'programmer', 'owner', 'client'])],
            'password' => 'required|string|min:8',
        ], [
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'password'  => Hash::make($request->password),
            'is_active' => true,
        ]);

        $this->logAction($request, 'create_user', $user->id, [
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]);

        return response()->json([
            'message' => 'Akun pengguna berhasil dibuat.',
            'user'    => $user,
        ], 201);
    }

    // ─── PUT /admin/users/{user} ──────────────────────────────────────────────────
    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['required', Rule::in(['service_desk', 'project_manager', 'programmer', 'owner', 'client'])],
        ], [
            'email.unique' => 'Email sudah digunakan oleh akun lain.',
        ]);

        $oldData = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role];

        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
            'role'  => $request->role,
        ]);

        $this->logAction($request, 'update_user', $user->id, [
            'old' => $oldData,
            'new' => ['name' => $user->name, 'email' => $user->email, 'role' => $user->role],
        ]);

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui.',
            'user'    => $user,
        ]);
    }

    // ─── PATCH /admin/users/{user}/toggle-active ──────────────────────────────────
    public function toggleActive(Request $request, User $user)
    {
        // Prevent admin from deactivating themselves
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.'], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'activate_user' : 'deactivate_user';
        $this->logAction($request, $action, $user->id, [
            'name'      => $user->name,
            'is_active' => $user->is_active,
        ]);

        return response()->json([
            'message'   => $user->is_active ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.',
            'is_active' => $user->is_active,
        ]);
    }

    // ─── POST /admin/users/{user}/reset-password ──────────────────────────────────
    public function resetPassword(Request $request, User $user)
    {
        // Admin reset is only for internal staff (not client — client uses email reset)
        if ($user->role === 'client') {
            return response()->json([
                'message' => 'Gunakan fitur lupa password via email untuk akun Client.',
            ], 422);
        }

        $request->validate([
            'new_password' => 'required|string|min:8',
        ], [
            'new_password.min' => 'Password baru minimal 8 karakter.',
        ]);

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        $this->logAction($request, 'reset_password', $user->id, [
            'name' => $user->name,
            'role' => $user->role,
        ]);

        return response()->json([
            'message' => "Password akun {$user->name} berhasil direset.",
        ]);
    }

    // ─── GET /admin/stats ─────────────────────────────────────────────────────────
    public function getStats()
    {
        $userStats = User::selectRaw('role, COUNT(*) as total, SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active')
            ->where('role', '!=', 'admin')
            ->groupBy('role')
            ->get()
            ->keyBy('role');

        $totalUsers    = User::where('role', '!=', 'admin')->count();
        $inactiveUsers = User::where('role', '!=', 'admin')->where('is_active', false)->count();

        // Ticket stats — check if tickets table exists to avoid errors
        $ticketStats = [];
        try {
            $ticketStats = \DB::table('tickets')
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');
        } catch (\Exception $e) {
            $ticketStats = [];
        }

        return response()->json([
            'users' => [
                'total'          => $totalUsers,
                'inactive'       => $inactiveUsers,
                'by_role'        => $userStats,
            ],
            'tickets' => $ticketStats,
        ]);
    }

    // ─── GET /admin/activity-logs ─────────────────────────────────────────────────
    public function getActivityLogs()
    {
        $logs = AdminActivityLog::with(['admin:id,name', 'targetUser:id,name,role'])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json($logs);
    }
}
