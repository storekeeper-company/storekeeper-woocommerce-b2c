#!/bin/bash
set -euox pipefail

if [ ! -z "$WORPRESS_URL" ]
then
  echo "Installing WP"

  cd /app/src/
  wp core install --url=$WORPRESS_URL --title=$WORPRESS_TITLE --admin_user=$WORDPRESS_ADMIN_USER --admin_password=$WORDPRESS_ADMIN_PASSWORD --admin_email=$WORDPRESS_ADMIN_EMAIL --skip-email

  # set config
  wp config set WP_DEBUG true

  # install plugins
  chown www-data:www-data  /app/src/wp-content /app/src/wp-content/plugins
  if [[ ! -d wp-content/plugins/woocommerce ]]
  then
      wp plugin install /app/plugins/woocommerce.zip
  fi
  wp plugin activate woocommerce
  wp plugin activate storekeeper-woocommerce-b2c

else
  echo "Not install wordpress because variables are not set"
fi

exec "$@"
