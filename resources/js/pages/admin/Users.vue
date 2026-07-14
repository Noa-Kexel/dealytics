<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    Users,
    Shield,
    ShieldCheck,
    Crown,
    Plus,
    Pencil,
    Trash2,
    Search,
    Check,
    TriangleAlert,
    X,
    Heart,
    Bell,
    ShoppingBag,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getInitials } from '@/composables/useInitials';
import { vReveal } from '@/directives/reveal';
import type { UserRole } from '@/types';

interface AdminUser {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    email_verified_at: string | null;
    created_at: string;
    favorites_count: number;
    alerts_count: number;
    purchases_count: number;
}

interface RoleOption {
    value: UserRole;
    label: string;
}

const props = defineProps<{
    users: AdminUser[];
    roles: RoleOption[];
    assignableRoles: UserRole[];
    currentUserId: number;
}>();

const page = usePage();
const isSuperAdmin = computed(
    () => (page.props.auth.user?.role as UserRole | undefined) === 'superadmin',
);

const ROLE_META: Record<UserRole, { label: string; badge: string; icon: typeof Shield }> = {
    superadmin: {
        label: 'Super admin',
        badge: 'border-dealytics-pink/30 bg-dealytics-pink/15 text-dealytics-pink',
        icon: Crown,
    },
    admin: {
        label: 'Admin',
        badge: 'border-dealytics-purple/30 bg-dealytics-purple/15 text-dealytics-purple',
        icon: ShieldCheck,
    },
    user: {
        label: 'Utilisateur',
        badge: 'border-border/50 bg-secondary text-muted-foreground',
        icon: Shield,
    },
};

function roleLabel(role: UserRole): string {
    return ROLE_META[role]?.label ?? role;
}

// Which rows the current admin may act on.
function canManage(user: AdminUser): boolean {
    if (isSuperAdmin.value) {
        return true;
    }

    return user.role === 'user';
}

function canDelete(user: AdminUser): boolean {
    return canManage(user) && user.id !== props.currentUserId;
}

// Filtering
const search = ref('');
const roleFilter = ref<'all' | UserRole>('all');

const filteredUsers = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.users.filter((u) => {
        const matchesSearch =
            !q || u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q);
        const matchesRole = roleFilter.value === 'all' || u.role === roleFilter.value;

        return matchesSearch && matchesRole;
    });
});

const counts = computed(() => ({
    total: props.users.length,
    superadmin: props.users.filter((u) => u.role === 'superadmin').length,
    admin: props.users.filter((u) => u.role === 'admin').length,
    user: props.users.filter((u) => u.role === 'user').length,
}));

const roleOptions = computed(() =>
    props.roles.filter((r) => props.assignableRoles.includes(r.value)),
);

// Create / edit dialog
const dialogOpen = ref(false);
const editing = ref<AdminUser | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'user' as UserRole,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.role = props.assignableRoles[0] ?? 'user';
    dialogOpen.value = true;
}

function openEdit(user: AdminUser) {
    editing.value = user;
    form.reset();
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`/admin/users/${editing.value.id}`, options);
    } else {
        form.post('/admin/users', options);
    }
}

// Delete dialog
const deleteTarget = ref<AdminUser | null>(null);
const deleteForm = useForm({});

function performDelete() {
    if (!deleteTarget.value) {
        return;
    }

    deleteForm.delete(`/admin/users/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
}

// Flash notice
const notice = ref<{ type: 'success' | 'error'; text: string } | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash as { success?: string; error?: string } | undefined,
    (flash) => {
        if (flash?.success) {
            notice.value = { type: 'success', text: flash.success };
        } else if (flash?.error) {
            notice.value = { type: 'error', text: flash.error };
        } else {
            return;
        }

        clearTimeout(noticeTimer);
        noticeTimer = setTimeout(() => (notice.value = null), 4500);
    },
    { deep: true, immediate: true },
);

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Gestion des utilisateurs" />

    <div class="animate-page-in mx-auto max-w-7xl px-4 py-6 lg:px-6">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-dealytics-purple/20">
                    <Users class="size-5 text-dealytics-purple" />
                </div>
                <div>
                    <h1 class="font-heading text-2xl font-bold text-foreground md:text-3xl">
                        Utilisateurs
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        Gérez les comptes et attribuez les rôles
                    </p>
                </div>
            </div>

            <Button
                class="gap-2 bg-dealytics-purple text-white hover:bg-dealytics-deep-purple"
                @click="openCreate"
            >
                <Plus class="size-4" />
                Ajouter un utilisateur
            </Button>
        </div>

        <!-- Flash notice -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div
                v-if="notice"
                class="mb-4 flex items-center gap-2 rounded-xl border p-3 text-sm"
                :class="notice.type === 'success'
                    ? 'border-dealytics-cyan/30 bg-dealytics-cyan/10 text-dealytics-cyan'
                    : 'border-red-500/30 bg-red-500/10 text-red-400'"
            >
                <Check v-if="notice.type === 'success'" class="size-4 shrink-0" />
                <TriangleAlert v-else class="size-4 shrink-0" />
                <span class="min-w-0 flex-1">{{ notice.text }}</span>
                <button class="shrink-0 text-current/60 hover:text-current" @click="notice = null">
                    <X class="size-4" />
                </button>
            </div>
        </Transition>

        <!-- Stats -->
        <div v-reveal="{ y: 16 }" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <Users class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-foreground">{{ counts.total }}</div>
                <div class="text-[10px] text-muted-foreground">Comptes au total</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-pink/20">
                    <Crown class="size-4 text-dealytics-pink" />
                </div>
                <div class="text-2xl font-bold text-dealytics-pink">{{ counts.superadmin }}</div>
                <div class="text-[10px] text-muted-foreground">Super admin</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-purple/20">
                    <ShieldCheck class="size-4 text-dealytics-purple" />
                </div>
                <div class="text-2xl font-bold text-dealytics-purple">{{ counts.admin }}</div>
                <div class="text-[10px] text-muted-foreground">Administrateurs</div>
            </div>
            <div class="border-gradient rounded-xl p-4">
                <div class="mb-2 flex size-8 items-center justify-center rounded-lg bg-dealytics-cyan/20">
                    <Shield class="size-4 text-dealytics-cyan" />
                </div>
                <div class="text-2xl font-bold text-dealytics-cyan">{{ counts.user }}</div>
                <div class="text-[10px] text-muted-foreground">Utilisateurs</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative w-full sm:max-w-xs">
                <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="search"
                    type="text"
                    placeholder="Rechercher un nom ou email…"
                    class="h-9 pl-9 text-sm"
                />
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="opt in [{ value: 'all', label: 'Tous' }, ...roles]"
                    :key="opt.value"
                    type="button"
                    class="rounded-lg px-3 py-1 text-xs font-medium transition-colors"
                    :class="roleFilter === opt.value
                        ? 'bg-dealytics-purple/15 text-dealytics-purple'
                        : 'bg-secondary/60 text-muted-foreground hover:text-foreground'"
                    @click="roleFilter = opt.value as 'all' | UserRole"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- User list -->
        <div class="border-gradient overflow-hidden rounded-xl">
            <div
                v-for="user in filteredUsers"
                :key="user.id"
                class="flex items-center gap-3 border-b border-border/40 p-3 last:border-b-0 hover:bg-secondary/30"
            >
                <!-- Avatar -->
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-dealytics-purple/20 text-xs font-semibold text-dealytics-purple">
                    {{ getInitials(user.name) }}
                </div>

                <!-- Identity -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-medium text-foreground">{{ user.name }}</span>
                        <span
                            v-if="user.id === currentUserId"
                            class="shrink-0 whitespace-nowrap rounded bg-secondary px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground"
                        >
                            vous
                        </span>
                    </div>
                    <div class="truncate text-xs text-muted-foreground">{{ user.email }}</div>
                </div>

                <!-- Activity (hidden on small screens) -->
                <div class="hidden shrink-0 items-center gap-4 text-[11px] text-muted-foreground lg:flex">
                    <span class="flex items-center gap-1" title="Favoris">
                        <Heart class="size-3" />{{ user.favorites_count }}
                    </span>
                    <span class="flex items-center gap-1" title="Alertes">
                        <Bell class="size-3" />{{ user.alerts_count }}
                    </span>
                    <span class="flex items-center gap-1" title="Achats">
                        <ShoppingBag class="size-3" />{{ user.purchases_count }}
                    </span>
                    <span class="w-24 text-right">{{ formatDate(user.created_at) }}</span>
                </div>

                <!-- Role badge -->
                <span
                    class="flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full border px-2 py-0.5 text-[10px] font-medium"
                    :class="ROLE_META[user.role].badge"
                >
                    <component :is="ROLE_META[user.role].icon" class="size-2.5" />
                    {{ roleLabel(user.role) }}
                </span>

                <!-- Actions -->
                <div class="flex shrink-0 items-center gap-1">
                    <button
                        v-if="canManage(user)"
                        class="flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-secondary hover:text-dealytics-purple"
                        title="Modifier"
                        @click="openEdit(user)"
                    >
                        <Pencil class="size-4" />
                    </button>
                    <button
                        v-if="canDelete(user)"
                        class="flex size-8 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-red-500/10 hover:text-red-400"
                        title="Supprimer"
                        @click="deleteTarget = user"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </div>

            <div v-if="filteredUsers.length === 0" class="py-12 text-center text-sm text-muted-foreground">
                Aucun utilisateur ne correspond à la recherche.
            </div>
        </div>
    </div>

    <!-- Create / Edit dialog -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent class="border-border/50 bg-card sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ editing ? 'Modifier l’utilisateur' : 'Nouvel utilisateur' }}
                </DialogTitle>
                <DialogDescription>
                    {{ editing
                        ? 'Mettez à jour les informations et le rôle du compte.'
                        : 'Créez un compte et attribuez-lui un rôle.' }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="name">Nom</Label>
                    <Input id="name" v-model="form.name" required autocomplete="off" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" required autocomplete="off" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="role">Rôle</Label>
                    <Select v-model="form.role">
                        <SelectTrigger id="role" class="w-full">
                            <SelectValue placeholder="Choisir un rôle" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in roleOptions" :key="r.value" :value="r.value">
                                {{ r.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">
                        {{ editing ? 'Nouveau mot de passe' : 'Mot de passe' }}
                        <span v-if="editing" class="text-xs font-normal text-muted-foreground">
                            (laisser vide pour ne pas changer)
                        </span>
                    </Label>
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        :required="!editing"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirmer le mot de passe</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        :required="!editing && !!form.password"
                        autocomplete="new-password"
                    />
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" @click="dialogOpen = false">
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        class="bg-dealytics-purple text-white hover:bg-dealytics-deep-purple"
                        :disabled="form.processing"
                    >
                        {{ editing ? 'Enregistrer' : 'Créer' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Delete confirmation -->
    <Dialog :open="deleteTarget !== null" @update:open="(v) => { if (!v) deleteTarget = null }">
        <DialogContent class="border-border/50 bg-card sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Supprimer l’utilisateur</DialogTitle>
                <DialogDescription>
                    Cette action est définitive. Le compte
                    <span class="font-medium text-foreground">{{ deleteTarget?.name }}</span>
                    et ses données seront supprimés.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button type="button" variant="outline" @click="deleteTarget = null">
                    Annuler
                </Button>
                <Button
                    type="button"
                    class="gap-2 bg-red-500 text-white hover:bg-red-600"
                    :disabled="deleteForm.processing"
                    @click="performDelete"
                >
                    <Trash2 class="size-4" />
                    Supprimer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
