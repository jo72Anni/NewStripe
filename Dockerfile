FROM php:8.2-apache

# Installa SOLO libzip-dev PRIMA di tutto
RUN apt-get update && apt-get install -y libzip-dev
RUN docker-php-ext-install zip

WORKDIR /var/www/html
COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
CMD ["apache2-foreground"]
