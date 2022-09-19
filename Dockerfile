FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libgd-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libicu-dev \
    zip \
    unzip 

#Install Extensions
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql
RUN docker-php-ext-install pdo pdo_pgsql pgsql zip exif pcntl zip
RUN docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u 1000 -d /home/dimensy dimensy

RUN mkdir -p /home/dimensy/.composer && \
    chown -R dimensy:dimensy /home/dimensy

COPY --chown=dimensy:www-data . /var/www
RUN chown -R dimensy:www-data /var/www/storage
RUN chmod -R ug+w /var/www/storage

WORKDIR /var/www
RUN composer update
EXPOSE 7000

CMD ["php", "artisan", "serve", "--host", "0.0.0.0", "--port=7000"]



USER $user