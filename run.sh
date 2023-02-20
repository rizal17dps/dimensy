#!/bin/bash

php artisan optimize
php artisan config:cache
php artisan view:cache
php artisan view:clear
php artisan route:clear
php artisan serve --host 0.0.0.0 --port=7000
