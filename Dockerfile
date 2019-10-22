FROM php:7.3-fpm-alpine
MAINTAINER Triptease (ops@triptease.com)

EXPOSE 80 443
ENTRYPOINT ["/bin/sh","/usr/src/app/run.sh"]

RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
ENV MORGUE_ENVIRONMENT docker

RUN apk add nginx --update-cache

RUN docker-php-ext-install pdo pdo_mysql

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');"


COPY . /usr/src/app
RUN ln -sf /usr/src/app/nginx/site.conf /etc/nginx/nginx.conf \
	&& php composer.phar update
