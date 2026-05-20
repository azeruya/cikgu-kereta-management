FROM richarvey/nginx-php-fpm:3.1.6

WORKDIR /var/www/html

COPY . .

ENV WEBROOT=/var/www/html/public

RUN composer install --no-dev --optimize-autoloader

RUN chmod -R 775 storage bootstrap/cache