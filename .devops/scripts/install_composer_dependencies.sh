#!/bin/bash
cd /var/www/html
wget https://getcomposer.org/composer.phar # download composer
php composer.phar install # install all dependencies
sudo chmod -R 777 /var/www/html/storage # allow access to storage folder
aws s3 cp s3://asapdrop-api-config-files/.env /var/www/html #update environment file from S3
#php artisan migrate # migrate to database
