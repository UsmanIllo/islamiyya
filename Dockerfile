FROM php:8.2-apache
# Install MySQL drivers
RUN docker-php-ext-install mysqli pdo pdo_mysql
# copy files
COPY . /var/www/html/
# enable apache rewrite
RUN a2enmod rewrite
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80