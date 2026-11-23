FROM php:8.3-cli-bookworm

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libreoffice-writer \
    fonts-liberation \
    $PHPIZE_DEPS \
  && docker-php-ext-install pdo_mysql zip pcntl bcmath \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apt-get purge -y $PHPIZE_DEPS \
  && apt-get autoremove -y \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
