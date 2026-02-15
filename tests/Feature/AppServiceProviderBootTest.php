<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Schema;

test('it safely skips access control checks when schema inspection fails', function (): void {
    Schema::shouldReceive('hasTable')
        ->once()
        ->with('permissions')
        ->andThrow(new \RuntimeException('Database unavailable'));

    $provider = new class(app()) extends AppServiceProvider
    {
        public function accessControlTablesExist(): bool
        {
            return $this->hasAccessControlTables();
        }
    };

    expect($provider->accessControlTablesExist())->toBeFalse();
});
