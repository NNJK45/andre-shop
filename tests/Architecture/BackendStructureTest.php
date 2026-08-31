<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackendStructureTest extends TestCase
{
    #[DataProvider('requiredDirectories')]
    public function test_required_backend_directories_exist(string $directory): void
    {
        $this->assertDirectoryExists(base_path($directory));
    }

    public static function requiredDirectories(): array
    {
        $domains = ['User', 'Catalog', 'Inventory', 'Cart', 'Order', 'Payment', 'Supplier', 'Quote', 'Delivery', 'Shared'];
        $applications = ['User', 'Catalog', 'Inventory', 'Cart', 'Order', 'Payment', 'Supplier', 'Quote', 'Delivery'];

        return array_map(
            static fn (string $directory): array => [$directory],
            [
                ...array_map(static fn (string $name): string => "app/Domain/{$name}", $domains),
                ...array_map(static fn (string $name): string => "app/Application/{$name}", $applications),
                'app/Infrastructure/Persistence',
                'app/Infrastructure/Payment',
                'app/Infrastructure/Notification',
                'app/Infrastructure/Storage',
                'app/Infrastructure/Delivery',
                'app/Infrastructure/ExternalApi',
                'app/Http/Controllers/Admin',
                'app/Http/Controllers/Customer',
                'app/Http/Controllers/Auth',
                'app/Http/Controllers/Webhook',
                'app/Http/Requests',
                'app/Http/Resources',
                'app/Http/Middleware',
                'tests/Integration',
            ],
        );
    }
}
