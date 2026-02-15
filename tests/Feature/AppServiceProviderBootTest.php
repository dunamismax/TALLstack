<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\DB;

test('it safely skips access control checks when sqlite database file is missing', function (): void {
    $originalDatabase = config('database.connections.sqlite.database');
    $missingDatabasePath = base_path('database/missing-access-control.sqlite');

    if (file_exists($missingDatabasePath)) {
        unlink($missingDatabasePath);
    }

    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', $missingDatabasePath);
    DB::purge('sqlite');

    $provider = new class(app()) extends AppServiceProvider
    {
        public function accessControlTablesExist(): bool
        {
            return $this->hasAccessControlTables();
        }
    };

    expect($provider->accessControlTablesExist())->toBeFalse();

    config()->set('database.connections.sqlite.database', $originalDatabase);
    DB::purge('sqlite');
});
