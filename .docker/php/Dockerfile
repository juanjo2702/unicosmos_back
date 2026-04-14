FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-install \
    zip \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY ./backend /var/www/html

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY ./.docker/php/nginx/default.conf /etc/nginx/sites-available/default
COPY ./.docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]