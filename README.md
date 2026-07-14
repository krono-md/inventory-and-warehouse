
## Installation

git clone <repository-url>
cd inventory-frontend-only

composer install

cp .env.example .env

Configure your database in .env

php artisan key:generate

php artisan migrate

php artisan db:seed

npm install

npm run build
```

## Quick setup

composer setup

## Run 

php artisan serve

## Development mode

composer dev

## Testing

composer test

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
