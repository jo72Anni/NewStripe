# Usa l'immagine PHP ufficiale
FROM php:8.2-apache

# Installa le estensioni PHP necessarie per Stripe
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Abilizza mod_rewrite per Apache
RUN a2enmod rewrite

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Crea la directory dell'app
WORKDIR /var/www/html

# Copia i file del progetto
COPY . .

# Installa le dipendenze PHP (Stripe SDK)
RUN composer install --no-dev --optimize-autoloader

# Configura Apache per servire i file PHP
RUN chown -R www-data:www-data /var/www/html

# Espone la porta 80
EXPOSE 80

# Avvia Apache in foreground
CMD ["apache2-foreground"]
