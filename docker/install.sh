#!/bin/bash
set -euox pipefail

echo -n "PHP: "
php -v

cd $WORPRESS_ROOT || exit 10

if [ ! -z "$WORPRESS_URL" ]
then
  echo "Installing WP"

  wp core install \
    --url=$WORPRESS_URL --title=$WORPRESS_TITLE --admin_user=$WORDPRESS_ADMIN_USER \
    --admin_password=$WORDPRESS_ADMIN_PASSWORD --admin_email=$WORDPRESS_ADMIN_EMAIL \
    --skip-email || exit 12

  # set FS_METHOD to direct so we can upload/install plugin during development
  wp config set FS_METHOD direct || exit 11
  wp config set WP_DEBUG true || exit 11
else
  echo "Not install wordpress because variables are not set"
  exit 15
fi

echo "Env settings: "
env | grep VERSION
echo -n "Installed Wordpress version: "
wp core version --path=$WORPRESS_ROOT || exit 2

if [[ ! -d wp-content/plugins/woocommerce ]]
then
    wp plugin install woocommerce --activate --version=$WOOCOMMERCE_VERSION || exit 13
fi
if [[ ! -d wp-content/themes/storefront ]]
then
    wp theme install storefront --activate --version=$WOOCOMMERCE_VERSION || exit 14
fi

cd wp-content/plugins/storekeeper-for-woocommerce
composer install || exit 15

if [ "$COPY_TO_MOUNT" == "1" ]; then
  echo "Making a copy of wordpress in the ./mount easier debugging."
  rsync -rclD --delete --exclude storekeeper-for-woocommerce $WORPRESS_DEV_DIR mount/wordpress-develop || exit 3
else
  echo "COPY_TO_MOUNT is not set to 1 (=$COPY_TO_MOUNT). No copy"
fi
