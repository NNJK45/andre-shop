<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_assets_use_https_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        (new AppServiceProvider(app()))->boot();

        $this->assertSame(
            'https://localhost/build/assets/app.css',
            url('/build/assets/app.css'),
        );
    }
}
