FROM ndamiens/nginx-php:latest

RUN mkdir /etc/baseobs
COPY composer.json composer.lock ./
RUN composer install -o
COPY www /opt/app/www
ADD update.php .
RUN apt-get update && apt-get install -y anacron
RUN echo "php /opt/app/update.php" > /etc/cron.daily/atlasupdate; chmod a+x /etc/cron.daily/atlasupdate
VOLUME /opt/app/www/data
ADD boot.sh /usr/local/bin/boot
RUN chmod a+x /usr/local/bin/boot
