FROM php:8.2-fpm

ARG user=laravel
ARG workdir=/var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user
RUN useradd -G www-data,root -u 1000 -d /home/${user} -m ${user}
RUN mkdir -p /home/${user}/.composer && chown -R ${user}:www-data /home/${user}

# Set working directory
WORKDIR ${workdir}

# Copy application files
COPY --chown=${user}:www-data . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R ${user}:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy environment file
RUN cp .env.example .env || true

# Generate application key
RUN php artisan key:generate || true

# Run migrations
RUN php artisan migrate --force || true

# Cache config
RUN php artisan config:cache || true
RUN php artisan route:cache || true
RUN php artisan view:cache || true

USER ${user}

EXPOSE 9000

CMD ["php-fpm"]
