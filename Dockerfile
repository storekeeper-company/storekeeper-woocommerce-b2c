
ARG PHP_VERSION
ARG WORDPRESS_VERSION
ARG WORDPRESS_DOCKER_PHP_VERSION
FROM wordpress:${WORDPRESS_VERSION}-php${WORDPRESS_DOCKER_PHP_VERSION}-apache AS wordpress-docker
FROM php:${PHP_VERSION}-apache as wordpress-distro

# copied from https://raw.githubusercontent.com/docker-library/wordpress/master/php7.3/apache/Dockerfile

# persistent dependencies
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
# Ghostscript is required for rendering PDF previews
		ghostscript \
	; \
	rm -rf /var/lib/apt/lists/*

# install the PHP extensions we need (https://make.wordpress.org/hosting/handbook/handbook/server-environment/#php-extensions)
RUN set -ex; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libfreetype6-dev \
		libjpeg-dev \
		libmagickwand-dev \
		libpng-dev \
		libzip-dev \
	; \
	\
	docker-php-ext-configure gd --with-freetype-dir=/usr --with-jpeg-dir=/usr --with-png-dir=/usr; \
	docker-php-ext-install -j "$(nproc)" \
		bcmath \
		exif \
		gd \
		mysqli \
		opcache \
		zip \
	; \
	pecl install imagick-3.4.4; \
	docker-php-ext-enable imagick; \
	\
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=2'; \
		echo 'opcache.fast_shutdown=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini
# https://wordpress.org/support/article/editing-wp-config-php/#configure-error-logging
RUN { \
# https://www.php.net/manual/en/errorfunc.constants.php
# https://github.com/docker-library/wordpress/issues/420#issuecomment-517839670
		echo 'error_reporting = E_ERROR | E_WARNING | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_RECOVERABLE_ERROR'; \
		echo 'display_errors = Off'; \
		echo 'display_startup_errors = Off'; \
		echo 'log_errors = On'; \
		echo 'error_log = /dev/stderr'; \
		echo 'log_errors_max_len = 1024'; \
		echo 'ignore_repeated_errors = On'; \
		echo 'ignore_repeated_source = Off'; \
		echo 'html_errors = Off'; \
	} > /usr/local/etc/php/conf.d/error-logging.ini

RUN set -eux; \
	a2enmod rewrite expires; \
	\
# https://httpd.apache.org/docs/2.4/mod/mod_remoteip.html
	a2enmod remoteip; \
	{ \
		echo 'RemoteIPHeader X-Forwarded-For'; \
# these IP ranges are reserved for "private" use and should thus *usually* be safe inside Docker
		echo 'RemoteIPTrustedProxy 10.0.0.0/8'; \
		echo 'RemoteIPTrustedProxy 172.16.0.0/12'; \
		echo 'RemoteIPTrustedProxy 192.168.0.0/16'; \
		echo 'RemoteIPTrustedProxy 169.254.0.0/16'; \
		echo 'RemoteIPTrustedProxy 127.0.0.0/8'; \
	} > /etc/apache2/conf-available/remoteip.conf; \
	a2enconf remoteip; \
# https://github.com/docker-library/wordpress/issues/383#issuecomment-507886512
# (replace all instances of "%h" with "%a" in LogFormat)
	find /etc/apache2 -type f -name '*.conf' -exec sed -ri 's/([[:space:]]*LogFormat[[:space:]]+"[^"]*)%h([^"]*")/\1%a\2/g' '{}' +

# vvv The part part was in the original docker file but won't use it vvv
#
#VOLUME /var/www/html
#
#ENV WORDPRESS_VERSION 5.3.2
#ENV WORDPRESS_SHA1 fded476f112dbab14e3b5acddd2bcfa550e7b01b
#
#RUN set -ex; \
#	curl -o wordpress.tar.gz -fSL "https://wordpress.org/wordpress-${WORDPRESS_VERSION}.tar.gz"; \
#	echo "$WORDPRESS_SHA1 *wordpress.tar.gz" | sha1sum -c -; \
## upstream tarballs include ./wordpress/ so this gives us /usr/src/wordpress
#	tar -xzf wordpress.tar.gz -C /usr/src/; \
#	rm wordpress.tar.gz; \
#	chown -R www-data:www-data /usr/src/wordpress
#
#COPY docker-entrypoint.sh /usr/local/bin/
#
#ENTRYPOINT ["docker-entrypoint.sh"]
#CMD ["apache2-foreground"]

# todo: make a productions image
#FROM wordpress-distro as prod
#
#RUN pecl install apcu && docker-php-ext-enable apcu && apt-get clean
#
#ARG WORDPRESS_VERSION
#ARG WORDPRESS_SHA1
#
#RUN set -ex; \
#	curl -o wordpress.tar.gz -fSL "https://wordpress.org/wordpress-${WORDPRESS_VERSION}.tar.gz"; \
#	echo "$WORDPRESS_SHA1 *wordpress.tar.gz" | sha1sum -c -; \
## upstream tarballs include ./wordpress/ so this gives us /usr/src/wordpress
#	tar -xzf wordpress.tar.gz -C /tmp/; \
#	mv /tmp/wordpress/* /var/www/html; \
#	rm -r wordpress.tar.gz /tmp/wordpress; \
#	chown -R www-data:www-data /var/www/html/;
#
## make tmp a volume to preserve the plugin logs
#RUN mkdir /tmp/sk-log/ && touch /tmp/sk-log/.persist  && chown -R www-data:www-data /tmp/sk-log;
#VOLUME /tmp/sk-log/
#
## Add WP-CLI and sudo so we can easy run it from root
#COPY docker/wp.sh /bin/wp
#RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -q -y install less sudo &&\
# curl -o /bin/wp-distro https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar &&\
# chmod +x /bin/wp /bin/wp-distro &&\
# mkdir -p /var/www/.wp-cli/cache/ && chown -R www-data:www-data /var/www/.wp-cli;
#
## download plugins
#ARG WOOCOMMERCE_VERSION
#RUN curl -o  /var/www/html/wp-content/woocommerce.zip -fSL "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip"
#
#COPY docker/docker-entrypoint-distro.sh /usr/local/bin/docker-entrypoint-distro.sh
#COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
#ENTRYPOINT ["docker-entrypoint-distro.sh"]
#CMD ["docker-entrypoint.sh","apache2-foreground"]

FROM wordpress-distro as test

# get the develop version wo we can run tests
ARG WORDPRESS_VERSION
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -q -y install git
RUN git clone --branch ${WORDPRESS_VERSION} --single-branch git://develop.git.wordpress.org/ /app/

RUN rmdir /var/www/html/ && ln -s /app/src /var/www/html
# need to copy from original image, because it's not included in development repo
COPY --from=wordpress-docker /usr/src/wordpress/wp-config-sample.php /var/www/html

# Add WP-CLI and sudo so we can easy run it from root
COPY docker/wp.sh /bin/wp
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -q -y install less sudo &&\
 curl -o /bin/wp-distro https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar &&\
 chmod +x /bin/wp /bin/wp-distro &&\
 mkdir -p /var/www/.wp-cli/cache/ && chown -R www-data:www-data /var/www/.wp-cli;

#install xdebug
RUN pecl install xdebug && docker-php-ext-enable xdebug && apt-get clean

# install node,npm,js + css
RUN DEBIAN_FRONTEND=noninteractive apt-get -q -y  install curl gnupg && \
    curl -sL https://deb.nodesource.com/setup_12.x  | bash - &&\
    apt-get -y install nodejs;
RUN cd /app/ && npm install && npm run build:dev

# make a php version which always run as www-data
RUN cp /usr/local/bin/php /usr/local/bin/php-www && \
    chown www-data:www-data  /usr/local/bin/php-www && \
    chmod u+s /usr/local/bin/php-www;

ARG WOOCOMMERCE_VERSION
RUN mkdir /app/plugins/ /app/themes/ \
    && curl -o  /app/plugins/woocommerce.zip -fSL "https://downloads.wordpress.org/plugin/woocommerce.${WOOCOMMERCE_VERSION}.zip"

# set up tests
COPY docker/phpunit.sh /bin/phpunit
COPY docker/run-unit-tests.sh /bin/run-unit-tests
RUN chmod +x /bin/phpunit && chmod +x /bin/run-unit-tests

# make sure the env are available for www-data user
RUN echo 'Defaults env_keep += "APP_ENV"' >> /etc/sudoers &&\
    echo 'Defaults env_keep += "WP_TESTS_DOMAIN WP_TESTS_EMAIL WP_TESTS_TITLE WP_PHP_BINARY WP_TESTS_CONFIG_FILE_PATH WP_TESTS_DIR WP_SK_PLUGIN_DIR"' >> /etc/sudoers

COPY docker/docker-entrypoint-distro.sh /usr/local/bin/docker-entrypoint-distro.sh
COPY docker/docker-test-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod 755 /usr/local/bin/docker-entrypoint-distro.sh /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint-distro.sh"]
CMD ["docker-entrypoint.sh","apache2-foreground"]

# set the envs
ENV APP_ENV=test \
    WORPRESS_TITLE='WP-TEST' \
    WORDPRESS_ADMIN_USER='admin' \
    WORDPRESS_ADMIN_EMAIL='admin@example.com' \
    WORPRESS_URL=localhost:80 \
    WP_TESTS_DOMAIN=localhost \
    WP_TESTS_EMAIL='admin@example.com' \
    WP_TESTS_TITLE='WP-TEST' \
    WP_PHP_BINARY=php-www \
    WP_TESTS_CONFIG_FILE_PATH=/app/src/wp-config.php \
    WP_TESTS_DIR=/app/tests/phpunit \
    WP_SK_PLUGIN_DIR=/app/src/wp-content/plugins/storekeeper-woocommerce-b2c \
    STOREKEEPER_WOOCOMMERCE_B2C_DEBUG=1

FROM test as dev
ENV APP_ENV=dev \
    WORPRESS_URL=localhost:8888 \
    WORPRESS_TITLE='WP-DEV'

COPY docker/disable-canonical-url.php /app/src/wp-content/plugins/
COPY docker/docker-dev-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

ARG STOREFRONT_VERSION
RUN chmod 755 /usr/local/bin/docker-entrypoint.sh \
    && curl -o  /app/themes/storefront.zip -fSL "https://downloads.wordpress.org/theme/storefront.${STOREFRONT_VERSION}.zip"

FROM dev as dev-local
ARG USER_ID
ARG GROUP_ID

# rewrite the www-data to user uid
RUN usermod -u $USER_ID www-data && groupmod -g $GROUP_ID www-data \
    && grep www-data /etc/passwd && grep www-data /etc/group \
    && find /app/src /tmp /var /usr/local/bin /run -user 33 -exec chown $USER_ID {} \; \
    && find /app/src /tmp /var /usr/local/bin /run -group 33 -exec chgrp $GROUP_ID {} \;

RUN mkdir -p /app/src/wp-content/ && touch /app/src/wp-content/.persist  && chown -R www-data:www-data /app/src/wp-content \
    && mkdir /tmp/storekeeper-woocommerce-b2c/ && touch /tmp/storekeeper-woocommerce-b2c/.persist  \
    && chown -R www-data:www-data /tmp/storekeeper-woocommerce-b2c \
    && mkdir /tmp/sk-log/ && touch /tmp/sk-log/.persist  && chown -R www-data:www-data /tmp/sk-log

RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get -q -y install gettext

# For generating .pot and .mo file using make command
COPY docker/extract-translations.sh /bin/extract-translations
COPY docker/translate-to-machine-object.sh /bin/translate-to-machine-object

VOLUME /app/src/wp-content/
VOLUME /tmp/storekeeper-woocommerce-b2c/
VOLUME /tmp/sk-log/
