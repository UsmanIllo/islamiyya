FROM php:8.1-apache
# Install MySQL drivers
RUN docker-php-ext-install pdo pdo_mysql mysqli
# copy files
COPY . /var/www/html/
# enable apache rewrite
RUN a2enmod rewrite