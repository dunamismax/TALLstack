<?php

use App\Models\Role;
use App\Models\User;
use App\Jobs\SendWelcomeNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'role')]
    public string $roleFilter = '';

    public bool $showUserModal = false;
    public bool $showDeleteModal = false;

    public ?int $editingUserId = null;
    public ?int $deletingUserId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';

    /**
     * @var list<int>
     */
    public array $selectedRoleIds = [];

    public string $deletingUserName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function rules(): array
    {
        $passwordRules = $this->editingUserId === null
            ? ['required', 'string', Password::default()]
            : ['nullable', 'string', Password::default()];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->editingUserId)],
            'password' => $passwordRules,
            'selectedRoleIds' => ['required', 'array', 'min:1'],
            'selectedRoleIds.*' => ['required', 'integer', Rule::exists(Role::class, 'id')],
        ];
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($innerQuery): void {
                    $innerQuery
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->roleFilter !== '', function ($query): void {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('slug', $this->roleFilter));
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
    }

    #[Computed]
    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', User::class);

        $this->editingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->selectedRoleIds = [];

        $this->resetValidation();
        $this->showUserModal = true;
    }

    public function openEditModal(int $userId): void
    {
        $user = User::query()->with('roles')->findOrFail($userId);

        Gate::authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->selectedRoleIds = $user->roles->pluck('id')->map(fn (int $id): int => $id)->all();

        $this->resetValidation();
        $this->showUserModal = true;
    }

    public function saveUser(): void
    {
        $validated = $this->validate();

        if ($this->editingUserId === null) {
            Gate::authorize('create', User::class);

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            SendWelcomeNotification::dispatch($user->id);
        } else {
            $user = User::query()->findOrFail($this->editingUserId);

            Gate::authorize('update', $user);

            $user->name = $validated['name'];
            $user->email = $validated['email'];

            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();
        }

        $user->syncRoles($validated['selectedRoleIds']);

        $this->showUserModal = false;
    }

    public function confirmDelete(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('delete', $user);

        $this->deletingUserId = $user->id;
        $this->deletingUserName = $user->name;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        if ($this->deletingUserId === null) {
            return;
        }

        $user = User::query()->findOrFail($this->deletingUserId);

        Gate::authorize('delete', $user);

        $user->syncRoles([]);
        $user->delete();

        $this->deletingUserId = null;
        $this->deletingUserName = '';
        $this->showDeleteModal = false;

        $this->resetPage();
    }
}; ?>

<section class="space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('User Management') }}</flux:heading>
                    <flux:text>{{ __('Create, search, update, and remove users with role assignments.') }}</flux:text>
                </div>

                <flux:button variant="primary" wire:click="openCreateModal">{{ __('Create User') }}</flux:button>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Name or email')" />

                <flux:select wire:model.live="roleFilter" :label="__('Role')">
                    <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                    @foreach ($this->roles as $role)
                        <flux:select.option :value="$role->slug" wire:key="user-role-filter-{{ $role->id }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table :paginate="$this->users">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Roles') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <flux:badge size="sm" wire:key="user-role-badge-{{ $user->id }}-{{ $role->id }}">{{ $role->name }}</flux:badge>
                                    @empty
                                        <flux:text>{{ __('Unassigned') }}</flux:text>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $user->created_at?->format('M d, Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $user->id }})">{{ __('Edit') }}</flux:button>
                                    <flux:button size="sm" variant="ghost" class="text-red-600 dark:text-red-400" wire:click="confirmDelete({{ $user->id }})">{{ __('Delete') }}</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:modal wire:model="showUserModal" class="md:w-[34rem]">
            <form wire:submit="saveUser" class="space-y-6">
                <div>
                    <flux:heading size="lg">
                        {{ $editingUserId === null ? __('Create User') : __('Edit User') }}
                    </flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Manage account identity and role access.') }}
                    </flux:text>
                </div>

                <flux:input wire:model="name" :label="__('Name')" type="text" required />
                <flux:input wire:model="email" :label="__('Email')" type="email" required />
                <flux:input wire:model="password" :label="__('Password')" type="password" :placeholder="$editingUserId === null ? __('Required') : __('Leave blank to keep current password')" viewable />

                <flux:checkbox.group wire:model="selectedRoleIds" :label="__('Roles')">
                    @foreach ($this->roles as $role)
                        <flux:checkbox :value="$role->id" :label="$role->name" wire:key="user-role-option-{{ $role->id }}" />
                    @endforeach
                </flux:checkbox.group>

                @error('selectedRoleIds')
                    <flux:text class="!text-red-600 dark:!text-red-400">{{ $message }}</flux:text>
                @enderror

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Save User') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="$set('showUserModal', false)">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal wire:model="showDeleteModal" class="md:w-[28rem]">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Delete User') }}</flux:heading>
                <flux:text>
                    {{ __('This will permanently remove :name and revoke all assigned roles.', ['name' => $deletingUserName]) }}
                </flux:text>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" class="bg-red-600! hover:bg-red-700!" wire:click="deleteUser">{{ __('Delete Permanently') }}</flux:button>
                    <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </section>
