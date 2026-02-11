# Agent Guidelines

## Tech Stack

- PHP 8.4
- Laravel 12 (streamlined structure)
- Livewire 4
- Flux UI Pro 2
- Tailwind CSS 4
- Pest 4

## Build, Lint & Test Commands

```bash
# Development server
composer run dev

# Build for production
npm run build

# Run all tests
php artisan test --compact
composer run test

# Run a single test file
php artisan test --compact tests/Feature/Settings/ProfileUpdateTest.php

# Run tests matching a filter
php artisan test --compact --filter=profile

# Format PHP code (MUST run before committing)
vendor/bin/pint --dirty

# Format Blade/views with Prettier
npm run format
```

## Code Style Guidelines

### PHP

- **Imports**: Explicit imports only, no wildcards. Group: Laravel, third-party, then App.
- **Types**: Always use explicit return types and parameter type hints.
- **Constructor Property Promotion**: Use PHP 8 promotion.
- **Control Structures**: Always use curly braces, even for single-line bodies.
- **Naming**: Classes=PascalCase, Methods/Variables=camelCase. Use descriptive names like `isRegisteredForDiscounts`, not `discount()`.
- **Comments**: Prefer PHPDoc blocks. No inline comments unless logic is complex.

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\User;

class UserService
{
    public function __construct(private User $user) {}

    public function findByEmail(string $email): ?User
    {
        return Cache::remember("user:{$email}", 3600, fn () =>
            $this->user->where('email', $email)->first()
        );
    }
}
```

### Livewire Components

- Place in `app/Livewire/` directory
- Use class-based components with proper namespaces
- Public properties for persistent state
- Use `mount()` for initialization
- Validate and authorize in actions

```php
<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Profile extends Component
{
    public string $name = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
    }

    public function updateProfile(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Auth::user()->update($validated);
    }
}
```

### Blade / Views

- Use Flux UI components: `<flux:button>`, `<flux:input>`, etc.
- 4-space indentation
- 120 character print width
- Single quotes for attributes

### Tailwind CSS

- Use utility classes, avoid custom CSS
- Follow existing patterns
- Mobile-first responsive design

## Testing (Pest)

- Use Pest syntax (not PHPUnit)
- Create tests: `php artisan make:test --pest {name}`
- Feature tests in `tests/Feature/`, Unit tests in `tests/Unit/`
- Use factories for model creation
- Use `expect()` for assertions

```php
<?php

use App\Models\User;

test('user can update profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => 'New Name'])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('New Name');
});
```

## Laravel Conventions

- **Structure**: Middleware registered in `bootstrap/app.php`, not `app/Http/Kernel.php`
- **Database**: Use Eloquent relationships with return type hints. Avoid `DB::`, prefer `Model::query()`.
- **Validation**: Create Form Request classes, avoid inline validation.
- **Config**: Use `config('app.name')`, never `env('APP_NAME')` outside config files.
- **Models**: Use `casts()` method instead of `$casts` property.
- **Routes**: Use named routes and `route()` helper.

## Error Handling

- Use Laravel's exception handling
- Prefer early returns over nested conditionals
- Validate input before processing
- Use type safety to prevent runtime errors

## Git Workflow

- Run `vendor/bin/pint --dirty` before committing
- Run affected tests before committing
- Keep commits focused and atomic
- Do NOT commit secrets or .env files

## Key Directories

- `app/Livewire/` - Livewire components
- `app/Models/` - Eloquent models
- `app/Http/Controllers/` - HTTP controllers
- `resources/views/` - Blade templates
- `tests/Feature/` - Feature tests
- `tests/Unit/` - Unit tests
- `routes/web.php` - Web routes
- `bootstrap/app.php` - App configuration & middleware

## Common Tasks

```bash
# Create a model with factory and seeder
php artisan make:model Post --factory --seeder

# Create a Livewire component
php artisan make:livewire Posts/Create

# Create a test
php artisan make:test --pest Posts/CreatePostTest
```

## Important Notes

- Always check sibling files for existing conventions
- Reuse existing components before creating new ones
- Keep responses concise and focused
- Only create documentation files if explicitly requested
- Never use `env()` outside of config files
- Use `search-docs` tool for version-specific documentation

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

- php - 8.4.17
- laravel/cashier (CASHIER) - v16
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- livewire/flux (FLUXUI_FREE) - v2
- livewire/flux-pro (FLUXUI_PRO) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- tailwindcss (TAILWINDCSS) - v4
- prettier (PRETTIER) - v3

## Skills Activation

- `fluxui-development` — Develops UIs with Flux UI Pro components.
- `livewire-development` — Develops reactive Livewire 4 components.
- `pest-testing` — Tests applications using Pest 4.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4.
- `developing-with-fortify` — Laravel Fortify authentication development.

## Conventions

- Follow all existing code conventions. Check sibling files for structure.
- Use descriptive names: `isRegisteredForDiscounts`, not `discount()`.
- Reuse existing components before creating new ones.

## Boost Tools

- `search-docs` — Search version-specific documentation.
- `tinker` — Execute PHP for debugging.
- `database-query` — Read from database.
- `database-schema` — Inspect table structure.
- `list-artisan-commands` — Check available Artisan parameters.
- `get-absolute-url` — Generate correct URLs.
- `browser-logs` — Read browser logs and errors.

=== php rules ===

- Always use curly braces for control structures.
- Use PHP 8 constructor property promotion.
- Always use explicit return types and parameter type hints.
- Enum keys should be TitleCase.
- Prefer PHPDoc blocks over inline comments.

=== laravel/v12 rules ===

- Middleware are configured in `bootstrap/app.php`.
- `bootstrap/providers.php` contains service providers.
- Console commands in `app/Console/Commands/` are auto-registered.
- Use `casts()` method instead of `$casts` property.
- When modifying a column, include all previous attributes.

=== pest/core rules ===

- Run `php artisan test --compact` or filter with `--filter=testName`.
- Every change must be programmatically tested.
- Do NOT delete tests without approval.

=== pint/core rules ===

- Run `vendor/bin/pint --dirty` before finalizing changes.

=== tailwindcss/core rules ===

- Check project patterns before adding new ones.
- Always use `search-docs` for version-specific documentation.
  </laravel-boost-guidelines>
