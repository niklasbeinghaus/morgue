FROM php:7.3-fpm-alpine
MAINTAINER njørd (njoerd@cccfr.de)

EXPOSE 80
ENTRYPOINT ["/bin/sh","/usr/src/app/run.sh"]
RUN apk add bash
RUN mkdir -p /usr/src/app
WORKDIR /usr/src/app
ENV MORGUE_ENVIRONMENT docker
ADD https://raw.githubusercontent.com/njoerd114/wait-for-it/master/wait-for-it.sh /bin/wait-for-it.sh
RUN chmod +x /bin/wait-for-it.sh
RUN sed -i 's|.*listen =.*|listen=9000|g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's|.*error_log =.*|error_log=/proc/self/fd/2|g' /usr/local/etc/php-fpm.conf && \
    sed -i 's|.*access.log =.*|access.log=/proc/self/fd/2|g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's|.*user =.*|user=root|g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's|.*group =.*|group=root|g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i -e "s/;catch_workers_output\s*=\s*yes/catch_workers_output = yes/g" /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's#.*variables_order.*#variables_order=EGPCS#g' /usr/local/etc/php/php.ini-development && \
    sed -i 's#.*variables_order.*#variables_order=EGPCS#g' /usr/local/etc/php/php.ini-production && \
    sed -i 's#.*date.timezone.*#date.timezone=UTC#g' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's#.*clear_env.*#clear_env=no#g' /usr/local/etc/php-fpm.d/www.conf
RUN apk add nginx --update-cache

RUN docker-php-ext-install pdo pdo_mysql

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');"


COPY . /usr/src/app
RUN ln -sf /usr/src/app/nginx/site.conf /etc/nginx/nginx.conf \
	&& php composer.phar update --no-dev
