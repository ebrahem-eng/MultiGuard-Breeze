# Laravel Multi-Guard Authentication Package

## Overview
This package automatically creates multiple authentication guards in Laravel with associated models, migrations, middleware, and controllers.

## Directory Structure
```
multi-guard-auth/
├── src/
│   ├── Commands/
│   │   └── CreateGuardsCommand.php
│   ├── MultiGuardAuthServiceProvider.php
│   └── stubs/
│       ├── model.stub
│       ├── migration.stub
│       ├── middleware.stub
│       └── controller.stub
├── README.md
├── LICENSE
└── composer.json
```

## Installation

1. Install via Composer:
```bash
composer require bro/multi-guard-auth
```

2. If Laravel < 5.5, register the service provider in `config/app.php`:
```php
'providers' => [
    YourVendor\MultiGuardAuth\MultiGuardAuthServiceProvider::class,
],
```

## Usage

1. Run the command:
```bash
php artisan create:guards
```

2. Follow the prompts:
   - Enter number of guards you want to create
   - Enter names for each guard (e.g., admin, seller, customer)

3. The package will automatically create:
   - Models in `app/Models`
   - Migrations in `database/migrations`
   - Middleware in `app/Http/Middleware`
   - Controllers in `app/Http/Controllers/{guardName}`
   - Update auth configuration
   - Register middleware

4. Run migrations:
```bash
php artisan migrate
```

## Routes Setup
Add these routes to your `routes/web.php`:

```php
// For each guard (example for 'admin' guard)
Route::prefix('admin')->group(function () {
    Route::get('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'login'])->name('admin.login.submit');
    
    Route::middleware('admin.auth')->group(function () {
        Route::get('/dashboard', function () {
            return 'Admin Dashboard';
        })->name('admin.dashboard');
    });
});
```

## Configuration
The package will automatically configure:

1. Guards in `config/auth.php`:
```php
'guards' => [
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],
```

2. Providers in `config/auth.php`:
```php
'providers' => [
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

## Middleware Registration
- For Laravel < 11: Automatically added to `app/Http/Kernel.php`
- For Laravel 11: Automatically added to `bootstrap/app.php`

## Sample Login Form
Create a login form in `resources/views/admin/login.blade.php`:

```html
<form method="POST" action="{{ route('admin.login.submit') }}">
    @csrf
    <input type="email" name="email" required>
    <input type="password" name="password" required>
    <button type="submit">Login</button>
</form>
```
