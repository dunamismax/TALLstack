<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $showRoleModal = false;
    public bool $showDeleteModal = false;

    public ?int $editingRoleId = null;
    public ?int $deletingRoleId = null;

    public string $name = '';
    public string $slug = '';
    public string $description = '';

    /**
     * @var list<int>
     */
    public array $selectedPermissionIds = [];

    public string $deletingRoleName = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedName(string $value): void
    {
        if ($this->editingRoleId === null) {
            $this->slug = Str::slug($value);
        }
    }

    /**
     * @return array<string, array<int, \Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique(Role::class, 'slug')->ignore($this->editingRoleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'selectedPermissionIds' => ['required', 'array', 'min:1'],
            'selectedPermissionIds.*' => ['required', 'integer', Rule::exists(Permission::class, 'id')],
        ];
    }

    #[Computed]
    public function roles(): LengthAwarePaginator
    {
        return Role::query()
            ->with('permissions')
            ->withCount('users')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    #[Computed]
    public function permissions(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        Gate::authorize('create', Role::class);

        $this->editingRoleId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->selectedPermissionIds = [];

        $this->resetValidation();
        $this->showRoleModal = true;
    }

    public function openEditModal(int $roleId): void
    {
        $role = Role::query()->with('permissions')->findOrFail($roleId);

        Gate::authorize('update', $role);

        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->slug = $role->slug;
        $this->description = $role->description ?? '';
        $this->selectedPermissionIds = $role->permissions->pluck('id')->map(fn (int $id): int => $id)->all();

        $this->resetValidation();
        $this->showRoleModal = true;
    }

    public function saveRole(): void
    {
        $validated = $this->validate();

        if ($this->editingRoleId === null) {
            Gate::authorize('create', Role::class);

            $role = Role::query()->create([
                'name' => $validated['name'],
                'guard_name' => 'web',
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?: null,
                'is_system' => false,
            ]);
        } else {
            $role = Role::query()->findOrFail($this->editingRoleId);

            Gate::authorize('update', $role);

            $role->name = $validated['name'];
            $role->slug = $validated['slug'];
            $role->description = $validated['description'] ?: null;
            $role->save();
        }

        $role->syncPermissions($validated['selectedPermissionIds']);

        $this->showRoleModal = false;
    }

    public function confirmDelete(int $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);

        Gate::authorize('delete', $role);

        $this->deletingRoleId = $role->id;
        $this->deletingRoleName = $role->name;
        $this->showDeleteModal = true;
    }

    public function deleteRole(): void
    {
        if ($this->deletingRoleId === null) {
            return;
        }

        $role = Role::query()->findOrFail($this->deletingRoleId);

        Gate::authorize('delete', $role);

        $role->syncPermissions([]);
        $role->delete();

        $this->deletingRoleId = null;
        $this->deletingRoleName = '';
        $this->showDeleteModal = false;

        $this->resetPage();
    }
}; ?>

<section class="space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <flux:heading size="xl">{{ __('Role Management') }}</flux:heading>
                    <flux:text>{{ __('Define reusable roles and permission bundles for your team.') }}</flux:text>
                </div>

                <flux:button variant="primary" wire:click="openCreateModal">{{ __('Create Role') }}</flux:button>
            </div>

            <div class="mt-5 max-w-md">
                <flux:input wire:model.live.debounce.300ms="search" :label="__('Search Roles')" :placeholder="__('Role name')" />
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table :paginate="$this->roles">
                <flux:table.columns>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Slug') }}</flux:table.column>
                    <flux:table.column>{{ __('Permissions') }}</flux:table.column>
                    <flux:table.column>{{ __('Users') }}</flux:table.column>
                    <flux:table.column>{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->roles as $role)
                        <flux:table.row :key="$role->id">
                            <flux:table.cell variant="strong">
                                <div class="flex items-center gap-2">
                                    <span>{{ $role->name }}</span>
                                    @if ($role->is_system)
                                        <flux:badge size="sm">{{ __('System') }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $role->slug }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($role->permissions as $permission)
                                        <flux:badge size="sm" wire:key="role-permission-badge-{{ $role->id }}-{{ $permission->id }}">{{ $permission->name }}</flux:badge>
                                    @endforeach
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="openEditModal({{ $role->id }})">{{ __('Edit') }}</flux:button>
                                    @if (! $role->is_system)
                                        <flux:button size="sm" variant="ghost" class="text-red-600 dark:text-red-400" wire:click="confirmDelete({{ $role->id }})">{{ __('Delete') }}</flux:button>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>

        <flux:modal wire:model="showRoleModal" class="md:w-[36rem]">
            <form wire:submit="saveRole" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $editingRoleId === null ? __('Create Role') : __('Edit Role') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Roles combine one or more permissions for assignment.') }}</flux:text>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input wire:model="name" :label="__('Name')" type="text" required />
                    <flux:input wire:model="slug" :label="__('Slug')" type="text" required />
                </div>

                <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

                <flux:checkbox.group wire:model="selectedPermissionIds" :label="__('Permissions')">
                    @foreach ($this->permissions as $permission)
                        <flux:checkbox :value="$permission->id" :label="$permission->name" wire:key="role-permission-option-{{ $permission->id }}" />
                    @endforeach
                </flux:checkbox.group>

                @error('selectedPermissionIds')
                    <flux:text class="!text-red-600 dark:!text-red-400">{{ $message }}</flux:text>
                @enderror

                <div class="flex items-center gap-3">
                    <flux:button type="submit" variant="primary">{{ __('Save Role') }}</flux:button>
                    <flux:button type="button" variant="ghost" wire:click="$set('showRoleModal', false)">{{ __('Cancel') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal wire:model="showDeleteModal" class="md:w-[28rem]">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Delete Role') }}</flux:heading>
                <flux:text>
                    {{ __('This will permanently remove the :name role and detach it from all users.', ['name' => $deletingRoleName]) }}
                </flux:text>

                <div class="flex items-center gap-3">
                    <flux:button variant="primary" class="bg-red-600! hover:bg-red-700!" wire:click="deleteRole">{{ __('Delete Permanently') }}</flux:button>
                    <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    </section>
