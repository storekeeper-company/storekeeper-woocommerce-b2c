ARG PHP_EXTENSIONS="bcmath zip intl imagick gd xdebug mysqli igbinary"

ARG BUILD_THECODINGMACHINE_VERSION
ARG PHP_VERSION
ARG THECODINGMACHINE_VERSION

FROM wordpress:cli-php${PHP_VERSION} as wordpress-cli

FROM thecodingmachine/php:${PHP_VERSION}-${THECODINGMACHINE_VERSION}-slim-cli as base-cli
FROM thecodingmachine/php:${BUILD_THECODINGMACHINE_VERSION} as base-build

FROM base-cli as wordpress-dev
ARG WORDPRESS_VERSION
RUN git clone  --depth 1 \
    --branch=$WORDPRESS_VERSION \
    https://github.com/WordPress/wordpress-develop.git wordpress-develop
RUN cd wordpress-develop &&  composer install && composer require dms/phpunit-arraysubset-asserts --dev

FROM base-cli as woocommerce
ARG WOOCOMMERCE_VERSION
RUN curl -L https://github.com/woocommerce/woocommerce/releases/download/$WOOCOMMERCE_VERSION/woocommerce.zip \
      -o woocommerce.zip
RUN unzip woocommerce.zip

FROM base-build as build
COPY composer.json .
COPY composer.lock .
RUN composer install --prod

FROM base-build as build-dev
COPY composer.json .
COPY composer.lock .
RUN composer install

FROM base-cli as test

USER root
RUN apt-get update && \
	apt-get install -y --no-install-recommends \
		rsync \
	&& \
	rm -rf /var/lib/apt/lists/*

USER docker

ARG CONTAINER_CWD=/usr/src/app
ARG WORPRESS_DEV_DIR=/usr/src/app/wordpress-develop
ARG WORPRESS_DIR=/usr/src/app/wordpress-develop/src
ARG PLUGIN_DIR=/usr/src/app/wordpress-develop/src/wp-content/plugins
ARG SK_PLUGIN_DIR=/usr/src/app/wordpress-develop/src/wp-content/plugins/storekeeper-for-woocommerce

COPY --from=wordpress-cli /usr/local/bin/wp /usr/local/bin/wp
COPY --chown=docker:docker --from=wordpress-dev /usr/src/app/wordpress-develop $WORPRESS_DEV_DIR
COPY --chown=docker:docker --from=woocommerce /usr/src/app/woocommerce $PLUGIN_DIR/woocommerce
COPY --chown=docker:docker . $SK_PLUGIN_DIR
COPY --chown=docker:docker --from=build-dev /usr/src/app/vendor $SK_PLUGIN_DIR/vendor
COPY --chown=docker:docker docker/wp-test-config.php $WORPRESS_DEV_DIR/wp-tests-config.php
COPY --chown=docker:docker docker/wp-test-config.php $WORPRESS_DEV_DIR/src/wp-config.php

RUN mkdir mount
ARG WORDPRESS_VERSION

ENV APP_ENV=test \
    WORPRESS_ROOT=$WORPRESS_DIR \
    WORPRESS_DEV_DIR=$WORPRESS_DEV_DIR \
    WORPRESS_DEV_TEST_DIR=$WORPRESS_DEV_DIR/tests \
    WORDPRESS_VERSION=$WORDPRESS_VERSION \
    WP_TESTS_DOMAIN=localhost \
    WP_TESTS_EMAIL='admin@example.com' \
    WP_TESTS_TITLE='WP-TEST' \
    WP_TESTS_CONFIG_FILE_PATH=$SK_PLUGIN_DIR/docker/wp-test-config.php \
    WP_TESTS_DIR=/app/tests/phpunit \
    STOREKEEPER_PLUGIN_DIR=$SK_PLUGIN_DIR \
    STOREKEEPER_WOOCOMMERCE_B2C_DEBUG=1

ENTRYPOINT $STOREKEEPER_PLUGIN_DIR/docker/docker-test-entrypoint.sh
