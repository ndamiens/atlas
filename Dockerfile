FROM ndamiens/nginx-php:latest

RUN mkdir /etc/baseobs
COPY composer.json composer.lock ./
RUN composer install -o
COPY www /opt/app/www
ADD update.php .
RUN apt-get update && apt-get install -y cron
RUN echo "30 * * * * root php /opt/app/update.php" > /etc/cron.d/atlasupdate
