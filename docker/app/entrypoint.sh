#!/bin/sh

if [ ! -f "/var/www/vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "Running migrations..."
php artisan migrate --seed --force

exec php artisan serve --host=0.0.0.0 --port=8000
