<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /** Liste des utilisateurs avec compteurs d'activité. */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->withCount(['favorites', 'priceAlerts', 'purchases'])
            ->orderBy('id')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'favorites_count' => $user->favorites_count,
                'alerts_count' => $user->price_alerts_count,
                'purchases_count' => $user->purchases_count,
            ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->values(),
            'assignableRoles' => $this->assignableRoles($request->user()),
            'currentUserId' => $request->user()->id,
        ]);
    }

    /** Crée un compte utilisateur. */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);

        $this->authorizeRoleAssignment($request->user(), $role);

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password']; // hashé via le cast du modèle
        $user->email_verified_at = now(); // comptes créés par un admin = déjà vérifiés
        $user->role = $role;
        $user->save();

        return back()->with('success', "Utilisateur « {$user->name} » créé.");
    }

    /** Met à jour un utilisateur (infos, rôle, mot de passe optionnel). */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);

        $data = $request->validated();
        $newRole = UserRole::from($data['role']);

        $this->authorizeRoleAssignment($request->user(), $newRole);

        // Empêche de rétrograder le dernier super admin.
        if ($user->isSuperAdmin() && $newRole !== UserRole::SuperAdmin) {
            $this->ensureNotLastSuperAdmin();
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $newRole;

        if (! empty($data['password'])) {
            $user->password = $data['password']; // hashé via le cast du modèle
        }

        $user->save();

        return back()->with('success', "Utilisateur « {$user->name} » mis à jour.");
    }

    /** Supprime un compte utilisateur. */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request->user(), $user);

        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte ici.');
        }

        if ($user->isSuperAdmin()) {
            $this->ensureNotLastSuperAdmin();
        }

        $user->delete();

        return back()->with('success', "Utilisateur « {$user->name} » supprimé.");
    }

    /**
     * Rôles que l'acteur peut attribuer.
     *
     * @return list<string>
     */
    private function assignableRoles(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return array_map(fn (UserRole $role) => $role->value, UserRole::cases());
        }

        return [UserRole::User->value];
    }

    /** Un admin simple ne gère que les users ; un super admin gère tout. */
    private function authorizeManage(User $actor, User $target): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($target->role->isAdminTier()) {
            abort(403, 'Seul un super administrateur peut gérer ce compte.');
        }
    }

    private function authorizeRoleAssignment(User $actor, UserRole $role): void
    {
        if (! in_array($role->value, $this->assignableRoles($actor), true)) {
            throw ValidationException::withMessages([
                'role' => "Vous n'avez pas le droit d'attribuer ce rôle.",
            ]);
        }
    }

    private function ensureNotLastSuperAdmin(): void
    {
        $superAdmins = User::where('role', UserRole::SuperAdmin->value)->count();

        if ($superAdmins <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Impossible : il doit rester au moins un super administrateur.',
            ]);
        }
    }
}
