FROM php:8.3.2-alpine as base
WORKDIR /usr/src/php
COPY . .
RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql
CMD [ "php", "./test.php" ]