<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function canViewDashboard(): bool
    {
        return auth()->user()?->can('view-dashboard') ?? false;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        if (! $this->canViewDashboard) {
            return [];
        }

        return [
            'users' => User::query()->count(),
            'verified_users' => User::query()->whereNotNull('email_verified_at')->count(),
            'roles' => Role::query()->count(),
            'permissions' => Permission::query()->count(),
        ];
    }

    #[Computed]
    public function recentUsers(): Collection
    {
        if (! $this->canViewDashboard) {
            return collect();
        }

        return User::query()
            ->with('roles')
            ->latest('id')
            ->limit(8)
            ->get();
    }
}; ?>

<section class="space-y-6">
        <div class="rounded-2xl border border-zinc-200 bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-700 p-6 text-white dark:border-zinc-700">
            <flux:badge color="lime">{{ __('Admin Suite') }}</flux:badge>
            <flux:heading size="xl" class="mt-3 text-white">{{ __('Operations Dashboard') }}</flux:heading>
            <flux:text class="mt-2 max-w-2xl text-zinc-200">
                {{ __('Run user administration, role governance, and API-backed workflows from one reusable control panel.') }}
            </flux:text>

            <div class="mt-5 flex flex-wrap gap-3">
                @can('manage-users')
                    <flux:button variant="primary" :href="route('admin.users')" wire:navigate>
                        {{ __('Manage Users') }}
                    </flux:button>
                @endcan

                @can('manage-roles')
                    <flux:button variant="ghost" :href="route('admin.roles')" wire:navigate>
                        {{ __('Manage Roles') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        @if ($this->canViewDashboard)
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ __('Users') }}</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['users']) }}</flux:heading>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ __('Verified Users') }}</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['verified_users']) }}</flux:heading>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ __('Roles') }}</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['roles']) }}</flux:heading>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:text>{{ __('Permissions') }}</flux:text>
                    <flux:heading size="xl" class="mt-2">{{ number_format($this->stats['permissions']) }}</flux:heading>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Recent Users') }}</flux:heading>
                </div>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Email') }}</flux:table.column>
                        <flux:table.column>{{ __('Roles') }}</flux:table.column>
                        <flux:table.column>{{ __('Joined') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($this->recentUsers as $user)
                            <flux:table.row :key="$user->id">
                                <flux:table.cell>{{ $user->name }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $user->email }}</flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($user->roles as $role)
                                            <flux:badge size="sm" wire:key="dashboard-role-{{ $user->id }}-{{ $role->id }}">{{ $role->name }}</flux:badge>
                                        @empty
                                            <flux:text>{{ __('Unassigned') }}</flux:text>
                                        @endforelse
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $user->created_at?->diffForHumans() }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="4">{{ __('No users available yet.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        @else
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-700/50 dark:bg-amber-950/40">
                <flux:heading size="lg">{{ __('No Dashboard Permission') }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Your account is authenticated but has not been granted dashboard permissions. Ask a Super Admin to assign a role.') }}
                </flux:text>
            </div>
        @endif
    </section>
