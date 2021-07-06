# StoreKeeper WooCommerce B2C

## Docker development

Start services

```bash
docker-compose up -d --build
```

Connect

```bash
docker-compose exec web bash
```

Setup the backend proxy. Add to ssh config:

```
Host b1.code4.pizza
    RemoteForward 8888 localhost:8888
```
After starting the ssh connection you can use the hooks.

Gotcha: only one developer can use it at the same time.

## Debugging

Debug is active if `WP_DEBUG` or `STOREKEEPER_WOOCOMMERCE_B2C_DEBUG` is true-ish


For different log error put in your `wp-config.php` 

```
define('STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL', 'DEBUG');
```

## PhpStorm setup

Define a new configuration template:
![phpunit configuration template](./phpunit-config.png)

Add extra includes
![Add extra includes](./extra-includes.png)

Setup mappings
![Mappings](./extra-includes-mappings.png)

Configure interpreter
![php interpreter](./interpreter-config.png)

Configure server to script debug
![server configuration](./docker-server-config.png)

WP cli debug
![WP cli config run](./wp-cli-config.png)

## Setting up the webhook to local docker

Services like ngrok.com or localtunnel.me  can be used

Example: `./ngrok http 8888 --region=eu` or `lt --port 8080 -s lukiwp` 

Then change the `WordPress Address (URL)` and `Site Address (URL)` to the url given out by service.
