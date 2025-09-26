FROM php:8.2-apache

WORKDIR /var/www/html

# Copia PRIMA composer.json e composer.lock (se esistono)
COPY composer.* ./

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installa dipendenze SOLO se composer.json esiste
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; else echo "No composer.json found"; fi

# Copia tutto il resto
COPY . .

# Permessi
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
