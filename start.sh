#!/bin/bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php -S 0.0.0.0:$PORT -t public/