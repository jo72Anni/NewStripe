FROM php:8.2-apache

# INSTALLA LE DIPENDENZE MANCANTI
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    && docker-php-ext-install zip

WORKDIR /var/www/html

COPY . .

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installa le dipendenze Stripe
RUN composer install --no-dev --optimize-autoloader

# Abilita mod_rewrite per Apache
RUN a2enmod rewrite

EXPOSE 80
CMD ["apache2-foreground"]
