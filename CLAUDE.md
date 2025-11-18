# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel-based calorie and diet tracking application with a Persian/Farsi interface. The application includes:
- Mobile API backend (Laravel Passport authentication)
- Admin panel (Filament 3)
- Calorie tracking, meal planning, exercise tracking
- Premium subscription system
- AI-powered chat feature using Grok API
- Firebase Cloud Messaging for notifications
- Multi-media support via Spatie Media Library

## Development Commands

### Running the Application
```bash
# Start development server
php artisan serve

# Run Vite for frontend assets
npm run dev

# Build frontend assets for production
npm run build
```

### Database
```bash
# Run all migrations
php artisan migrate

# Rollback last migration
php artisan migrate:rollback

# Fresh migration with seeding
php artisan migrate:fresh --seed

# Create new migration
php artisan make:migration create_table_name
```

### Testing
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run PHPUnit directly
vendor/bin/phpunit
```

### Code Quality
```bash
# Run Laravel Pint (code formatter)
vendor/bin/pint

# Fix specific file or directory
vendor/bin/pint app/Models
```

### Artisan Helpers
```bash
# Generate API documentation (L5 Swagger)
php artisan l5-swagger:generate

# Clear all caches
php artisan optimize:clear

# Create new controller
php artisan make:controller API/ControllerName

# Create new model with migration
php artisan make:model ModelName -m

# Create Filament resource
php artisan make:filament-resource ResourceName
```

## Architecture

### Authentication System
- **Mobile API**: Uses Laravel Passport for OAuth2 token-based authentication
- **Admin Panel**: Uses Filament's built-in authentication
- **Verification**: SMS and email-based verification code system with cooldown and daily limits
  - Cooldown prevention implemented in `VerificationCode::inCoolDown()`
  - Daily limit check in `VerificationCode::exceedsDailyLimit()`
- **User model**: Located at `app/Models/User.php` with computed attributes for premium status, age calculation, BMR calculations

### Directory Structure
```
app/
├── Builders/          # Custom query builders
├── Console/           # Artisan commands
├── Filament/          # Admin panel resources and pages
│   ├── Resources/     # CRUD resources for admin
│   └── Pages/         # Custom admin pages
├── Helpers/           # Helper classes (Util.php autoloaded via composer.json)
├── Http/
│   ├── Controllers/
│   │   └── API/       # All API controllers (diet, exercise, food, auth, etc.)
│   ├── Middleware/    # Custom middleware including DisableUserMiddleware
│   ├── Requests/      # Form request validation
│   └── Resources/     # API response resources
├── Livewire/          # Livewire components for admin
├── Models/            # Eloquent models (User, Diet, Exercise, Food, etc.)
├── Notifications/     # Email and push notifications
├── Services/          # Business logic services (GrokService, HomeService)
└── Traits/            # Reusable traits
```

### API Routes
All API routes are defined in `routes/api.php` and follow RESTful patterns:
- Authentication endpoints: `/api/auth/*`
- Resource endpoints: `/api/{resource}` (exercises, diets, foods, articles, etc.)
- Most routes require authentication via Passport token
- Some routes use `DisableUserMiddleware` to prevent disabled users from accessing

### Key Models
Models do NOT use `$fillable` property (per user preference). Key models include:
- **User**: Main user model with BMR calculations, premium status, health metrics
- **Food/FoodCategory/FoodUnit**: Food database with calorie information
- **Exercise/ExerciseCategory**: Exercise database with calorie burn rates
- **Diet/DietCategory/DietaryCooking**: Diet plans and recipes
- **Mealtime/MealtimeWeekday**: User meal schedules
- **VerificationCode**: SMS/email verification with rate limiting
- **Transaction**: In-app purchase tracking
- **Ticket**: Support ticket system
- **Article/Comment**: Content and community features

### Services Layer
Business logic is extracted into service classes:
- **GrokService**: AI chat integration using X.AI's Grok API
- **HomeService**: Home screen data aggregation and calculations

### Helper Functions
Global helper function `util()` returns `App\Helpers\Util` instance with methods:
- `standardPhoneNumber()`: Normalize Iranian phone numbers
- `simpleSuccess()`: Standard success JSON response
- `throwError()`: Throw validation exception with custom message
- `toSms()`: Send SMS via SMS webservice
- `bazaarAccessToken()`: Retrieve Bazaar payment token from settings

### Filament Admin Panel
- Resources are in `app/Filament/Resources/`
- Each resource has corresponding Pages subdirectory for List/Create/Edit pages
- Uses Persian/Jalali date pickers via `ariaieboy/filament-jalali-datetimepicker`
- Rich text editing via TipTap and TinyEditor
- Media management via Spatie Media Library plugin

### Localization
- Persian (Farsi) is the primary language
- Uses Verta library for Jalali (Persian) calendar dates
- Translation files in `lang/` directory

### API Documentation
- Uses L5 Swagger (Swagger/OpenAPI annotations)
- Annotations are in controller methods as PHPDoc comments
- Generate docs: `php artisan l5-swagger:generate`
- Access at: `/docs` endpoint
- Configuration: `config/l5-swagger.php`

### Testing
- Test structure follows Laravel conventions
- Feature tests: `tests/Feature/`
- Unit tests: `tests/Unit/`
- Uses PHPUnit 10.x
- Telescope disabled in test environment

## Important Patterns

### Model Best Practices
- Do NOT define `$fillable` property in models (use guarded or mass assignment individually)
- Use computed attributes via `Attribute::make()` for derived properties
- Implement media collections in `registerMediaCollections()` for models with uploads
- Use constants for enum-like values (e.g., `User::GOALS`, `User::SEXES`)

### API Response Pattern
- Return JSON responses with `status` and `message` keys
- Use API Resources for transforming model data
- Validation errors return 422 with error details
- Use `util()->simpleSuccess()` for simple success responses
- Use `util()->throwError()` for validation errors

### Database Conventions
- Migration files follow Laravel timestamp naming
- Use foreign key constraints where appropriate
- Soft deletes used on some models
- Media files stored via Spatie Media Library

### Frontend Assets
- Vite for asset bundling (configured in `vite.config.js`)
- Resources in `resources/css/` and `resources/js/`
- Blade views in `resources/views/`

## External Services

### Firebase
- Configuration: `config/firebase.php`
- Used for FCM push notifications
- Credentials should be in storage/app/firebase-credentials.json

### Payment Integration
- Bazaar (Iranian app store) payment verification
- Access token stored in settings table
- Transaction verification in `app/Builders/TransactionVerifier.php`

### AI Integration
- Grok API (X.AI) for chat functionality
- API key in GROK_API environment variable
- System prompts configurable via AiSetting model
- Message history limited to last 30 messages per conversation

### SMS Service
- SMS webservice API for verification codes
- Template-based sending via `util()->toSms()`
- API credentials hardcoded in Util helper

## Common Tasks

### Adding a New API Endpoint
1. Create controller in `app/Http/Controllers/API/`
2. Add route in `routes/api.php`
3. Add Swagger annotations to controller method
4. Create Form Request for validation if needed
5. Create API Resource for response transformation
6. Regenerate API docs: `php artisan l5-swagger:generate`

### Adding Filament Resource
1. Generate: `php artisan make:filament-resource ResourceName`
2. Define form fields in `form()` method
3. Define table columns in `table()` method
4. Add filters, actions as needed
5. Configure media collections if using file uploads

### Working with Dates
- Use Verta library for Jalali/Persian dates
- User birthdays stored as Jalali dates
- Age calculation: `Verta::now()->diffYears(Verta::parse($birthday))`
- Premium expiration uses Carbon for gregorian dates

### Adding New Model
1. Create migration: `php artisan make:migration create_table_name`
2. Create model: `php artisan make:model ModelName`
3. Do NOT add `$fillable` property
4. Add relationships, casts, and computed attributes as needed
5. Register media collections if needed (implement `HasMedia`)
