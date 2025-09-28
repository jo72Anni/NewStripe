FROM php:8.2-apache

# Installa tutte le dipendenze in un unico layer
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpq-dev \
    curl \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install zip pdo pdo_pgsql \
    && a2enmod rewrite

WORKDIR /var/www/html

COPY . .

# Installa Composer e dipendenze in un unico layer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
