#!/bin/bash
set -euox pipefail

echo -n "PHP: "
php -v

if [ -f $WORPRESS_ROOT/index.php ]; then
  echo -n "Downloaded wordpress version: "
  wp core version --path=$WORPRESS_ROOT || exit 10
else
  echo "Downloading WP $WORDPRESS_VERSION $WORDPRESS_LOCALE"
  wp core download --path=$WORPRESS_ROOT --version=$WORDPRESS_VERSION  --locale=$WORDPRESS_LOCALE || exit 10
fi

cd $WORPRESS_ROOT || exit 11

echo "Plugin composer install"
cd wp-content/plugins/storekeeper-for-woocommerce && composer install || exit 22

cd $WORPRESS_ROOT || exit 12

if [ -f "wp-config.php" ]; then
  echo "wp-config.php already created"
else
  wp config create \
    --dbname=$WORDPRESS_DB_NAME \
    --dbuser=$WORDPRESS_DB_USER \
    --dbpass=$WORDPRESS_DB_PASSWORD \
    --dbhost=$WORDPRESS_DB_HOST \
    --locale=$WORDPRESS_LOCALE \
    --skip-check || exit 11

  # set FS_METHOD to direct so we can upload/install plugin during development
  wp config set FS_METHOD direct || exit 11
  wp config set WP_DEBUG true || exit 11
fi

if ! wp core is-installed; then
  wp core install \
    --url=$WORPRESS_URL --title=$WORPRESS_TITLE --admin_user=$WORDPRESS_ADMIN_USER \
    --admin_password=$WORDPRESS_ADMIN_PASSWORD --admin_email=$WORDPRESS_ADMIN_EMAIL \
    --skip-email || exit 13
else
  echo "Wordpress is already installed"
fi

echo "Env settings: "
env | grep VERSION
echo -n "Installed Wordpress version: "
wp core version || exit 2

if [[ ! -d wp-content/plugins/woocommerce ]]
then
  echo "Installing woocomerce"
  wp plugin install woocommerce --activate --version=$WOOCOMMERCE_VERSION || exit 20
fi
if [[ ! -d wp-content/themes/storefront ]]
then
  echo "Installing storefront"
  wp theme install storefront --activate || exit 21
fi

if ! wp plugin is-active storekeeper-for-woocommerce; then
  wp plugin activate storekeeper-for-woocommerce || exit 23
else
  echo "storekeeper-for-woocommerce is already active"
fi

cd $WORPRESS_ROOT

REPORT_FILE=versions-`date "+%Y-%m-%d"`.txt
echo "== Wordpress" > $REPORT_FILE \
  && wp core version >> $REPORT_FILE \
  && echo "== Plugins" >> $REPORT_FILE \
  && wp plugin list >> $REPORT_FILE \
  && echo "== Themes" >> $REPORT_FILE \
  && wp theme list >> $REPORT_FILE
cat $REPORT_FILE
