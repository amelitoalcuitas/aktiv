FROM php:8.3-fpm

# Install system dependencies 
# (Removed the mirror swap for now to troubleshoot DNS)
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
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock* ./

RUN if [ -f "composer.json" ]; then \
        composer install --no-scripts --no-autoloader --prefer-dist --no-interaction; \
    fi

COPY . .

RUN if [ -f "composer.json" ]; then \
        composer dump-autoload --optimize; \
    fi

RUN if [ -d "storage" ]; then \
        chown -R www-data:www-data storage bootstrap/cache && \
        chmod -R 775 storage bootstrap/cache; \
    fi

RUN { \
        echo 'upload_max_filesize=12M'; \
        echo 'post_max_size=64M'; \
        echo 'max_file_uploads=20'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 9000

CMD ["php-fpm"]