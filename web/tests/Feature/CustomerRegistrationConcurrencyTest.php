<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class CustomerRegistrationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'pgsql' || ! str_starts_with((string) getenv('DB_DATABASE'), 'shop_auth_test_')) {
            $this->markTestSkipped('Requires an isolated PostgreSQL authentication test database.');
        }
        parent::setUp();
    }

    public function test_concurrent_registrations_that_both_pass_validation_create_only_one_account(): void
    {
        $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
\Illuminate\Support\Facades\Notification::fake();
\Illuminate\Support\Facades\Event::listen('eloquent.creating: '.\App\Models\User::class, function (): void {
    echo "ready\n";
    fflush(STDOUT);
    fgets(STDIN);
});
$request = \Illuminate\Http\Request::create('/register', 'POST', [
    'name' => 'Concurrent buyer', 'email' => 'buyer@example.com',
    'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123',
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$response = $kernel->handle($request);
echo $response->getStatusCode()."\n";
$kernel->terminate($request, $response);
PHP;
        $children = [];
        try {
            for ($index = 0; $index < 2; $index++) {
                $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
                $this->assertIsResource($process);
                stream_set_timeout($pipes[1], 20);
                $children[] = [$process, $pipes];
                $this->assertSame("ready\n", fgets($pipes[1]));
            }
            foreach ($children as [$process, $pipes]) {
                fwrite($pipes[0], "continue\n");
                fclose($pipes[0]);
            }
            $statuses = [];
            foreach ($children as [$process, $pipes]) {
                $statuses[] = (int) trim(stream_get_contents($pipes[1]));
                $this->assertSame('', stream_get_contents($pipes[2]));
            }
            sort($statuses);
            $this->assertSame([201, 422], $statuses);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('customer_verification_deliveries', 1);
        } finally {
            foreach ($children as [$process, $pipes]) {
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
            }
        }
    }
}
