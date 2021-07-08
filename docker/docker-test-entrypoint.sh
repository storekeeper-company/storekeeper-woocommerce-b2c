#!/bin/bash
set -euox pipefail

usermod -u $USER_ID www-data
groupmod -g $GROUP_ID www-data

touch /tmp/xdebug.log && chmod og+wr /tmp/xdebug.log

if [ ! -z "$WORPRESS_URL" ]
then
  echo "Installing WP"

  chown www-data:www-data /app/src/wp-content || exit 9

  cd /app/src/
  wp core install \
    --url=$WORPRESS_URL --title=$WORPRESS_TITLE --admin_user=$WORDPRESS_ADMIN_USER \
    --admin_password=$WORDPRESS_ADMIN_PASSWORD --admin_email=$WORDPRESS_ADMIN_EMAIL \
    --skip-email

  # set config
  chown www-data:www-data /app/src/wp-config.php
  wp config set WP_DEBUG true || exit 11

  # install plugins
  mkdir -p /app/src/wp-content/themes /app/src/wp-content/plugins /app/src/wp-content/uploads \
   && chown www-data:www-data /app/src/wp-content /app/src/wp-content/themes /app/src/wp-content/plugins /app/src/wp-content/uploads || exit 12
  if [[ ! -d wp-content/plugins/woocommerce ]]
  then
      wp plugin install /app/plugins/woocommerce.zip || exit 13
  fi
  if [[ ! -d wp-content/themes/storefront ]]
  then
      wp theme install /app/themes/storefront.zip || exit 14
  fi
  wp plugin activate woocommerce \
    && wp plugin activate storekeeper-woocommerce-b2c \
    && wp theme activate storefront \
    || exit 15

else
  echo "Not install wordpress because variables are not set"
fi

exec "$@"
