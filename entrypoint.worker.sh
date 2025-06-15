#!/bin/bash

# Run deploy tasks (just in case)
./deploy.sh

# Start the queue worker
php artisan queue:work --verbose --tries=3 --timeout=90
