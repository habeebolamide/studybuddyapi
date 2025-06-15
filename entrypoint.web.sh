#!/bin/bash

# Run deploy tasks (build, migrate, cache etc.)
./deploy.sh

# Start PHP-FPM
php-fpm
