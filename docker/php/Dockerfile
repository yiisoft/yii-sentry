FROM yiisoftware/yii-php:8.0-fpm AS php-build
RUN apt-get update -y && apt-get upgrade -y
RUN apt-get clean all

FROM php-build AS php-cli
COPY ./ /app
RUN composer install
ENTRYPOINT []

