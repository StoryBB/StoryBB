FROM php:7.0-apache
COPY ./ /var/www/html/
COPY ./docker/Settings_phpContainer.php /var/www/html/Settings.php