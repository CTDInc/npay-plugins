FROM php:8.2-apache

# System deps.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-install pdo pdo_mysql zip intl \
    && rm -rf /var/lib/apt/lists/*

# Apache config: enable rewrite + allow .htaccess.
RUN a2enmod rewrite headers
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf || true
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/sites-available/*.conf || true

# Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install deps first (cache layer).
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts || true

# App source.
COPY . /var/www/html/
RUN composer dump-autoload -o || true
RUN chown -R www-data:www-data /var/www/html

ENV APP_URL=http://localhost
EXPOSE 80

CMD ["apache2-foreground"]
