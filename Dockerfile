FROM php:8.2-apache

# INSTALLA ZIP IN MODO SEMPLICE
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install zip

WORKDIR /var/www/html

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

RUN a2enmod rewrite

EXPOSE 80
CMD ["apache2-foreground"]
