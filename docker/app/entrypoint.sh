#!/bin/sh

if [ ! -f "/var/www/vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader --no-dev
fi

exec php artisan serve --host=0.0.0.0 --port=8000
