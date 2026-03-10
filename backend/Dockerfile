FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libgd-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install gd pdo pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Redis PHP extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Only copy and install composer dependencies if composer.json exists.
# This allows the container to build even on a fresh/empty backend folder.
COPY . .

RUN if [ -f "composer.json" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist && \
        composer dump-autoload --optimize; \
    else \
        echo "No composer.json found — skipping composer install. Run it after Laravel is initialized."; \
    fi

# Set correct permissions for Laravel storage and cache if they exist
RUN if [ -d "storage" ]; then \
        chown -R www-data:www-data storage bootstrap/cache && \
        chmod -R 775 storage bootstrap/cache; \
    fi

# Allow larger multipart payloads for cover + gallery uploads
RUN { \
                echo 'upload_max_filesize=12M'; \
                echo 'post_max_size=64M'; \
                echo 'max_file_uploads=20'; \
            } > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 9000

CMD ["php-fpm"]