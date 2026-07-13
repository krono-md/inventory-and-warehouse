

## Installation

```bash
# 1. Clone the repository
git clone <repository-url>
cd inventory-frontend-only

# 2. Install PHP dependencies
composer install

# 3. Create the environment file
cp .env.example .env

# 4. Configure your database in .env
# The default expects a local PostgreSQL database named `inventory`.
# Example:
#   DB_CONNECTION=pgsql
#   DB_HOST=127.0.0.1
#   DB_PORT=5432
#   DB_DATABASE=inventory
#   DB_USERNAME=postgres
#   DB_PASSWORD=postgres

# 5. Generate the application key
php artisan key:generate

# 6. Run migrations
php artisan migrate

# 7. Seed the database with sample data
php artisan db:seed

# 8. Install frontend dependencies
npm install

# 9. Build frontend assets
npm run build
```

## Quick setup (one command)

```bash
composer setup
```

## Running the application

```bash
php artisan serve
```

## Development mode

```bash
composer dev
```

## Testing

```bash
composer test
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
