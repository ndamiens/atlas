FROM ndamiens/nginx-php:latest

RUN mkdir /etc/baseobs
COPY composer.json composer.lock ./
RUN composer install -o
COPY www /opt/app/www
ADD update.php .
RUN apt-get update && apt-get install -y cron
RUN echo "30 9-23/2 * * * root php /opt/app/update.php" > /etc/cron.d/atlasupdate
ADD boot.sh /usr/local/bin/boot
RUN chmod a+x /usr/local/bin/boot
