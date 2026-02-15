<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        $this->addGuardNameColumns($tableNames);
        $this->createSpatiePivotTables($tableNames, $columnNames, $pivotRole, $pivotPermission);
        $this->migrateLegacyPivotData($tableNames, $columnNames, $pivotRole, $pivotPermission);

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
    }

    /**
     * Ensure roles and permissions are guard-aware for Spatie.
     *
     * @param  array<string, string>  $tableNames
     */
    protected function addGuardNameColumns(array $tableNames): void
    {
        if (! Schema::hasColumn($tableNames['permissions'], 'guard_name')) {
            Schema::table($tableNames['permissions'], function (Blueprint $table): void {
                $table->string('guard_name')->default('web')->after('name');
            });
        }

        if (! Schema::hasColumn($tableNames['roles'], 'guard_name')) {
            Schema::table($tableNames['roles'], function (Blueprint $table): void {
                $table->string('guard_name')->default('web')->after('name');
            });
        }

        DB::table($tableNames['permissions'])->update(['guard_name' => 'web']);
        DB::table($tableNames['roles'])->update(['guard_name' => 'web']);
    }

    /**
     * Create the pivot tables required by Spatie permissions.
     *
     * @param  array<string, string>  $tableNames
     * @param  array<string, string>  $columnNames
     */
    protected function createSpatiePivotTables(array $tableNames, array $columnNames, string $pivotRole, string $pivotPermission): void
    {
        if (! Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission): void {
                $table->unsignedBigInteger($pivotPermission);
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

                $table->foreign($pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->cascadeOnDelete();

                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        if (! Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole): void {
                $table->unsignedBigInteger($pivotRole);
                $table->string('model_type');
                $table->unsignedBigInteger($columnNames['model_morph_key']);
                $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

                $table->foreign($pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->cascadeOnDelete();

                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }

        if (! Schema::hasTable($tableNames['role_has_permissions'])) {
            Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission): void {
                $table->unsignedBigInteger($pivotPermission);
                $table->unsignedBigInteger($pivotRole);

                $table->foreign($pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->cascadeOnDelete();

                $table->foreign($pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->cascadeOnDelete();

                $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
            });
        }
    }

    /**
     * Move existing role / permission assignments into Spatie pivot tables.
     *
     * @param  array<string, string>  $tableNames
     * @param  array<string, string>  $columnNames
     */
    protected function migrateLegacyPivotData(array $tableNames, array $columnNames, string $pivotRole, string $pivotPermission): void
    {
        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')
                ->select('permission_id', 'role_id')
                ->orderBy('permission_id')
                ->orderBy('role_id')
                ->chunk(500, function ($rows) use ($tableNames, $pivotRole, $pivotPermission): void {
                    $payload = collect($rows)
                        ->map(fn (object $row): array => [
                            $pivotPermission => $row->permission_id,
                            $pivotRole => $row->role_id,
                        ])
                        ->values()
                        ->all();

                    DB::table($tableNames['role_has_permissions'])->insertOrIgnore($payload);
                });
        }

        if (Schema::hasTable('role_user')) {
            DB::table('role_user')
                ->select('role_id', 'user_id')
                ->orderBy('role_id')
                ->orderBy('user_id')
                ->chunk(500, function ($rows) use ($tableNames, $columnNames, $pivotRole): void {
                    $payload = collect($rows)
                        ->map(fn (object $row): array => [
                            $pivotRole => $row->role_id,
                            $columnNames['model_morph_key'] => $row->user_id,
                            'model_type' => User::class,
                        ])
                        ->values()
                        ->all();

                    DB::table($tableNames['model_has_roles'])->insertOrIgnore($payload);
                });
        }
    }
};
