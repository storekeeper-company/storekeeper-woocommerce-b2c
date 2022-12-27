#!/bin/bash
set -euox pipefail

touch /tmp/xdebug.log && chmod og+wr /tmp/xdebug.log
chown www-data:www-data /tmp/storekeeper-for-woocommerce/ /tmp/sk-log/
chmod 755 -R /tmp/storekeeper-for-woocommerce

if [ ! -z "$WORPRESS_URL" ]
then
  echo "Installing WP"

  cd /app/src/
  wp core install \
    --url=$WORPRESS_URL --title=$WORPRESS_TITLE --admin_user=$WORDPRESS_ADMIN_USER \
    --admin_password=$WORDPRESS_ADMIN_PASSWORD --admin_email=$WORDPRESS_ADMIN_EMAIL \
    --skip-email

  # set config
  chown www-data:www-data /app/src/wp-config.php
  # set FS_METHOD to direct so we can upload/install plugin during development
  wp config set FS_METHOD direct || exit 11
  wp config set WP_DEBUG true || exit 11

  # install plugins
  if [[ ! -d wp-content/plugins/woocommerce ]]
  then
      wp plugin install /app/plugins/woocommerce.zip || exit 13
  fi
  if [[ ! -d wp-content/themes/storefront ]]
  then
      wp theme install /app/themes/storefront.zip || exit 14
  fi
  wp plugin activate woocommerce \
    && wp plugin activate storekeeper-for-woocommerce \
    && wp theme activate storefront \
    || exit 15

else
  echo "Not install wordpress because variables are not set"
fi

exec "$@"
