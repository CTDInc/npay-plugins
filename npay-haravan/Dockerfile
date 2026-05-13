FROM php:8.2-apache

# System deps
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev unzip git ca-certificates curl \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts || true

# Copy the rest
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader \
    && mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html

# Allow .htaccess overrides
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/npay.conf \
    && a2enconf npay

EXPOSE 80
