ARG BUILD_THECODINGMACHINE_VERSION
ARG PHP_VERSION
ARG THECODINGMACHINE_VERSION

FROM wordpress:cli-php${PHP_VERSION} as wordpress-cli

FROM thecodingmachine/php:${PHP_VERSION}-${THECODINGMACHINE_VERSION}-apache as base-run
ENV PHP_EXTENSION_BCMATH=1 \
    PHP_EXTENSION_INTL=1 \
    PHP_EXTENSION_IMAGICK=1 \
    PHP_EXTENSION_GD=1

FROM thecodingmachine/php:${BUILD_THECODINGMACHINE_VERSION}-cli as base-build
ENV PHP_EXTENSION_BCMATH=1 \
    PHP_EXTENSION_INTL=1 \
    PHP_EXTENSION_IMAGICK=1 \
    PHP_EXTENSION_GD=1

FROM base-run as wordpress-dev
ARG WORDPRESS_DEV_VERSION
RUN git clone  --depth 1 \
    --branch=$WORDPRESS_DEV_VERSION \
    https://github.com/WordPress/wordpress-develop.git wordpress-develop
RUN cd wordpress-develop &&  composer install && composer require dms/phpunit-arraysubset-asserts --dev

FROM base-run as wordpress-prod
ARG WORDPRESS_VERSION
COPY --from=wordpress-cli /usr/local/bin/wp /usr/local/bin/wp
RUN wp core download --path=wordpress --version=$WORDPRESS_VERSION

FROM base-run as woocommerce
ARG WOOCOMMERCE_VERSION
RUN curl -L https://github.com/woocommerce/woocommerce/releases/download/$WOOCOMMERCE_VERSION/woocommerce.zip \
      -o woocommerce.zip
RUN unzip -q woocommerce.zip

FROM base-build as build-plugin-prod
COPY composer.json .
COPY composer.lock .
RUN composer install --prefer-dist --no-dev --optimize-autoloader

FROM base-build as build-plugin-dev
COPY composer.json .
COPY composer.lock .
RUN composer install

FROM base-run as test

USER root
RUN apt-get update && \
	apt-get install -y --no-install-recommends \
		rsync \
	&& \
	rm -rf /var/lib/apt/lists/*

USER docker

ARG CONTAINER_CWD=/var/www/html
ARG WORPRESS_DEV_DIR=/var/www/html/wordpress-develop
ARG WORPRESS_DIR=/var/www/html/wordpress-develop/src
ARG WORPRESS_PLUGIN_DIR=/var/www/html/wordpress-develop/src/wp-content/plugins
ARG STOREKEEPER_PLUGIN_DIR=/var/www/html/wordpress-develop/src/wp-content/plugins/storekeeper-for-woocommerce

COPY --from=wordpress-cli /usr/local/bin/wp /usr/local/bin/wp
COPY --chown=docker:docker --from=wordpress-dev /var/www/html/wordpress-develop $WORPRESS_DEV_DIR
COPY --chown=docker:docker --from=woocommerce /var/www/html/woocommerce $WORPRESS_PLUGIN_DIR/woocommerce
COPY --chown=docker:docker . $STOREKEEPER_PLUGIN_DIR
COPY --chown=docker:docker --from=build-plugin-dev /usr/src/app/vendor $STOREKEEPER_PLUGIN_DIR/vendor
COPY --chown=docker:docker docker/wp-test-config.php $WORPRESS_DEV_DIR/wp-tests-config.php
COPY --chown=docker:docker docker/wp-test-config.php $WORPRESS_DEV_DIR/src/wp-config.php
COPY docker/run-phpunit /usr/local/bin/run-phpunit

RUN mkdir -p mount/wordpress-develop-tests
ARG WORDPRESS_VERSION

ENV WORPRESS_ROOT=$WORPRESS_DIR \
    WORPRESS_PLUGIN_DIR=$WORPRESS_PLUGIN_DIR \
    WORPRESS_DEV_DIR=$WORPRESS_DEV_DIR \
    WORPRESS_DEV_TEST_DIR=$WORPRESS_DEV_DIR/tests \
    WORDPRESS_VERSION=$WORDPRESS_VERSION \
    WP_TESTS_CONFIG_FILE_PATH=$STOREKEEPER_PLUGIN_DIR/docker/wp-test-config.php \
    WP_TESTS_DIR=/app/tests/phpunit \
    STOREKEEPER_PLUGIN_DIR=$STOREKEEPER_PLUGIN_DIR \
    STOREKEEPER_WOOCOMMERCE_B2C_DEBUG=1 \
    PHP_EXTENSION_XDEBUG=1

FROM base-run as dev

USER root
RUN apt-get update && \
	apt-get install -y --no-install-recommends \
		gettext \
        less \
	&& \
	rm -rf /var/lib/apt/lists/*

USER docker

COPY --from=wordpress-cli /usr/local/bin/wp /usr/local/bin/wp

ARG CONTAINER_CWD=/var/www/html
ARG WORPRESS_DEV_DIR=/var/www/html/wordpress-develop
ARG WORPRESS_DIR=/var/www/html/wordpress
ARG WORPRESS_PLUGIN_DIR=/var/www/html/wordpress/wp-content/plugins
ARG STOREKEEPER_PLUGIN_DIR=/var/www/html/wordpress/wp-content/plugins/storekeeper-for-woocommerce

RUN mkdir -p $STOREKEEPER_PLUGIN_DIR

ENV WORPRESS_ROOT=$WORPRESS_DIR \
    WORPRESS_PLUGIN_DIR=$WORPRESS_PLUGIN_DIR \
    STOREKEEPER_PLUGIN_DIR=$STOREKEEPER_PLUGIN_DIR \
    STOREKEEPER_INSTALL=$STOREKEEPER_PLUGIN_DIR/docker/install.sh \
    STOREKEEPER_WOOCOMMERCE_B2C_DEBUG=1 \
    APACHE_DOCUMENT_ROOT=wordpress/ \
    PHP_EXTENSION_XDEBUG=1

