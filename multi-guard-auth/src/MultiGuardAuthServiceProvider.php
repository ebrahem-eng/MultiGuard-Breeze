<?php

namespace Bro\MultiGuardAuth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MultiGuardAuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\CreateGuardsCommand::class,
            ]);
        }
    }

    public function register()
    {
        //
    }
}

// Commands/CreateGuardsCommand.php
namespace Bro\MultiGuardAuth\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CreateGuardsCommand extends Command
{
    protected $signature = 'create:guards';
    protected $description = 'Create multiple authentication guards';

    public function handle()
    {
        // Get number of guards
        $guardCount = $this->ask('How many guards do you want to create?');
        $guards = [];

        // Collect guard names
        for ($i = 0; $i < $guardCount; $i++) {
            $guardName = $this->ask('Enter name for guard #' . ($i + 1));
            $guards[] = Str::lower($guardName);
        }

        foreach ($guards as $guard) {
            $this->createModel($guard);
            $this->createMigration($guard);
            $this->createMiddleware($guard);
            $this->createController($guard);
            $this->updateAuthConfig($guard);
            $this->registerMiddleware($guard);
        }

        $this->info('Guards created successfully!');
    }

    protected function createModel($guard)
    {
        $modelName = Str::studly(Str::singular($guard));
        $modelPath = app_path("Models/{$modelName}.php");

        $modelContent = <<<EOT
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class {$modelName} extends Authenticatable
{
    protected \$fillable = [
        'name',
        'email',
        'password',
    ];

    protected \$hidden = [
        'password',
        'remember_token',
    ];
}
EOT;

        File::put($modelPath, $modelContent);
    }

    protected function createMigration($guard)
    {
        $tableName = Str::plural(Str::snake($guard));
        $migrationName = date('Y_m_d_His') . "_create_{$tableName}_table.php";
        $migrationPath = database_path("migrations/{$migrationName}");

        $migrationContent = <<<EOT
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->string('email')->unique();
            \$table->string('password');
            \$table->rememberToken();
            \$table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('{$tableName}');
    }
};
EOT;

        File::put($migrationPath, $migrationContent);
    }

    protected function createMiddleware($guard)
    {
        $middlewareName = Str::studly($guard) . 'AuthMiddleware';
        $middlewarePath = app_path("Http/Middleware/{$middlewareName}.php");

        $middlewareContent = <<<EOT
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class {$middlewareName}
{
    public function handle(\$request, Closure \$next)
    {
        if (!Auth::guard('{$guard}')->check()) {
            return redirect()->route('{$guard}.login');
        }
        return \$next(\$request);
    }
}
EOT;

        File::put($middlewarePath, $middlewareContent);
    }

    protected function createController($guard)
    {
        $controllerName = Str::studly($guard) . 'AuthController';
        $controllerPath = app_path("Http/Controllers/{$guard}/{$controllerName}.php");

        // Ensure directory exists
        File::makeDirectory(dirname($controllerPath), 0755, true, true);

        $controllerContent = <<<EOT
<?php

namespace App\Http\Controllers\\{$guard};

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class {$controllerName} extends Controller
{
    public function login(Request \$request)
    {
        \$check = \$request->all();
        
        if (Auth::guard('{$guard}')->attempt([
            'email' => \$check['email'],
            'password' => \$check['password']
        ])) {
            return redirect()->route('{$guard}.dashboard');
        } else {
            return redirect()->back()->with('error', 'Invalid email or password');
        }
    }
}
EOT;

        File::put($controllerPath, $controllerContent);
    }

    protected function updateAuthConfig($guard)
    {
        $configPath = config_path('auth.php');
        $config = File::get($configPath);

        // Add guard configuration
        $guardConfig = <<<EOT
        '{$guard}' => [
            'driver' => 'session',
            'provider' => '{$guard}s',
        ],
EOT;
        $config = preg_replace(
            "/'guards' => \[([\s\S]*?)\],/",
            "'guards' => [$1{$guardConfig}],",
            $config
        );

        // Add provider configuration
        $providerConfig = <<<EOT
        '{$guard}s' => [
            'driver' => 'eloquent',
            'model' => App\Models\\' . Str::studly(Str::singular($guard)) . '::class,
        ],
EOT;
        $config = preg_replace(
            "/'providers' => \[([\s\S]*?)\],/",
            "'providers' => [$1{$providerConfig}],",
            $config
        );

        File::put($configPath, $config);
    }

    protected function registerMiddleware($guard)
    {
        $middlewareName = Str::studly($guard) . 'AuthMiddleware';
        
        if (version_compare(app()->version(), '11.0.0', '<')) {
            // For Laravel < 11, update Kernel.php
            $kernelPath = app_path('Http/Kernel.php');
            $kernel = File::get($kernelPath);
            
            $middlewareAlias = "        '{$guard}.auth' => \App\Http\Middleware\\{$middlewareName}::class,\n";
            
            $kernel = preg_replace(
                "/(protected \$middlewareAliases = \[[\s\S]*?)\];/",
                "$1{$middlewareAlias}];",
                $kernel
            );
            
            File::put($kernelPath, $kernel);
        } else {
            // For Laravel 11, update bootstrap/app.php
            $bootstrapPath = base_path('bootstrap/app.php');
            $bootstrap = File::get($bootstrapPath);
            
            $middlewareConfig = <<<EOT
            ->withMiddleware(function (Middleware \$middleware) {
                \$middleware->alias([
                    '{$guard}.auth' => \App\Http\Middleware\\{$middlewareName}::class
                ]);
            })
EOT;
            
            // Add middleware configuration if not exists
            if (!str_contains($bootstrap, '->withMiddleware')) {
                $bootstrap = str_replace(
                    "return \$app;",
                    "{$middlewareConfig}\n\nreturn \$app;",
                    $bootstrap
                );
                File::put($bootstrapPath, $bootstrap);
            } else {
                // Update existing middleware configuration
                $bootstrap = preg_replace(
                    "/(->withMiddleware\(function \(Middleware \\\$middleware\) {[\s\S]*?)\]\);/",
                    "$1    '{$guard}.auth' => \App\Http\Middleware\\{$middlewareName}::class,\n        ]);",
                    $bootstrap
                );
                File::put($bootstrapPath, $bootstrap);
            }
        }
    }
}