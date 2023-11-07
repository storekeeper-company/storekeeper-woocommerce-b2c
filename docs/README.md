[![Contributors][contributors-shield]][contributors-url]
[![Issues][issues-shield]][issues-url]
[![LinkedIn][linkedin-shield]][linkedin-url]

<!-- PROJECT LOGO -->
<br />
<div align="center">
  <a href="https://github.com/storekeeper-company/storekeeper-woocommerce-b2c">
    <img src="logo-blue.png" alt="Logo" width="80" height="80">
  </a>

<h3 align="center">StoreKeeper WooCommerce B2C</h3>

  <p align="center">
    This plugin provides sync possibilities with the StoreKeeper Backoffice. Allows synchronization of the WooCommerce product catalog, customers, orders and handles payments using StoreKeeper payment platform.
    <br />
    <a href="https://wordpress.org/plugins/storekeeper-for-woocommerce/"><strong>View on wordpress.org »</strong></a>
    <br />
    
  </p>
</div>

<!-- TABLE OF CONTENTS -->
<details>
  <summary>Table of Contents</summary>
  <ol>
    <li>
      <a href="#getting-started">Getting Started</a>
      <ul>
        <li><a href="#prerequisites">Prerequisite</a></li>
        <li><a href="#development-using-docker">Development using docker</a></li>
        <li><a href="#unit-tests">Unit tests</a></li>
        <li><a href="#logging">Logging</a></li>
        <li><a href="#translations">Translations</a></li>
      </ul>
    </li>
    <li>
      <a href="#development-with-phpstorm">Development with PhpStorm</a>
      <ul>
        <li><a href="#settings-for-development">Settings for development</a></li>
        <li><a href="#settings-for-unit-test">Settings for unit test</a></li>
        <li><a href="#image-configuration-references">Image configuration references</a></li>
      </ul>
    </li>
    <li>
      <a href="#advanced-development-guide">Advanced development guide</a>
      <ul>
        <li><a href="#setting-up-the-webhook-to-local-docker">Setting up the webhook to local docker</a></li>
        <li><a href="#using-api-or-web-hook-dumps">Using API or web hook dumps</a></li>
        <li><a href="#adding-sql-migrations">Adding SQL migrations</a></li>
      </ul>
    </li>
    <li>
      <a href="#development-notes">Development notes</a>
      <ul>
        <li><a href="#hooks">Hooks</a></li>
        <li><a href="#tagging-a-new-release">Tagging a new release</a></li>
      </ul>
    </li>
  </ol>
</details>


<!-- GETTING STARTED -->
## Getting Started
> This guide is favorable when you are working with Linux operating system, specifically Ubuntu.
> 
Having this project setup for development is pretty much straightforward, especially using docker.

### Prerequisites

These are the prerequisites to run the project on development environment.
* php
  ```sh
  sudo apt install php
  ```
* [composer](https://getcomposer.org/download/)
* [docker](https://docs.docker.com/engine/install/ubuntu/)
* [docker compose](https://docs.docker.com/compose/install/)
* [lokalise cli v2](https://github.com/lokalise/lokalise-cli-2-go)
### Development using docker

Running steps below should serve your development environment.

1. Build docker development image.
   ```sh
   make dev-bash
   ```
   > Running command above will build docker image and open a bash inside docker container.
3. Run installation script inside docker container.
   ```sh
   $STOREKEEPER_INSTALL
   # e.g docker@b32031196454:/var/www/html$ $STOREKEEPER_INSTALL
   ```
   > WordPress files will be mounted inside `mount/wordpress` directory of the project.
4. Exit docker container or open a new terminal.
5. Start all containers.
   ```sh
   docker compose up -d
   ```
6. Open browser and visit http://localhost:8888/. You're all set!

### Unit tests

Before doing anything related to unit testing. Run all unit tests with `make` command.
```bash
make test
```

> ⚠️ This will make sure all the permissions are correct inside the `mount/` directory.
> Otherwise, it will result to `root` ownership.

> In case running `make test` throws a `Permission denied` error, try deleting the `mount/wordpress-develop-tests` folder before running it again.

### Logging

Debug mode is active if `WP_DEBUG` or `STOREKEEPER_WOOCOMMERCE_B2C_DEBUG` value is true-ish.

For different log error, add this line in `wp-config.php`.

```
define('STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL', 'DEBUG');
```

### Translations
Before you can pull and push to lokalise, you need to add `LOKALISE_TOKEN` to your environment variables.
```bash
export LOKALISE_TOKEN=xxxTokenFromLokalisexxx
```

Then below are the commands for translations:

Extract strings to be translated from the plugin, mostly all strings that are enclosed in `__("text to translate")` function and compile it in a `.pot` file.
```bash
make extract-translations
```

Download translations file in `.po` format from Lokalise.
```bash
make pull-translations
```

Upload translation template file (`.pot`) to Lokalise.
```bash
make push-translations
```

> Suggested sequence is to first run `make extract-translations` to get the latest strings, then run `make push-translations` to update Lokalise, and finally run `make pull-translations` to download the translated texts.

<!-- DEVELOPMENT WITH PHPSTORM -->
## Development with PhpStorm

### Settings for development
1. Configure CLI interpreters
   1. Go to *Settings > PHP*. On that page, click the Browse (...) button next to the CLI Interpreter list.
   2. On the page that opens, add a new entry. Select `From Docker, Vagrant, VM, WSL, Remote...` after clicking the `+` button and another popup will open.
   3. Choose `Docker Compose`.
   4. For the `Server` dropdown, select `Docker`.
   5. In `Configuration files` field, set `./docker-compose.yml`.
   6. For `Service` dropdown, select `dev`. Then click OK.
   7. An entry will be added, for this guide it will be named `dev`. Click Apply and OK.
   > See this <a href="#cli-interpreter-for-development">image</a> for reference.
2. Configure docker development server and mapping.
   1. Go to *Settings > PHP > Servers*.
   2. Add a new entry. For this guide we name it `docker-dev`.
   3. Put `localhost` in the `Host` field and `8888` in the `Port` field.
   4. Check the `Use path mappings`.
   5. Map your project root directory `e.g /path/to/project/storekeeper-woocommerce-b2c` to `/var/www/html/wordpress/wp-content/plugins/storekeeper-for-woocommerce`.
   6. Map your `wordpress` directory inside mount folder `e.g /path/to/project/storekeeper-woocommerce-b2c/mount/wordpress` to `/var/www/html/wordpress`.
   7. Click Apply and OK.
   > See this <a href="#development-server-and-mapping">image</a> for reference.

### Settings for unit test
1. Configure CLI interpreters
   1. Go to *Settings > PHP*. On that page, click the Browse (...) button next to the CLI Interpreter list.
   2. On the page that opens, add a new entry. Select `From Docker, Vagrant, VM, WSL, Remote...` after clicking the `+` button and another popup will open.
   3. Choose `Docker Compose`.
   4. For the `Server` dropdown, select `Docker`.
   5. In `Configuration files` field, set `./docker-compose.yml`.
   6. For `Service` dropdown, select `test`. Then click OK.
   7. An entry will be added, for this guide it will be named `test`. Click Apply and OK.
   > See this <a href="#cli-interpreter-for-unit-test">image</a> for reference.
2. Configure Test Frameworks
   1. Go to *Settings > PHP > Test Frameworks*.
   2. Add an entry, select `PHPUnit Local` as configuration type.
   3. On the configuration page. Choose `Use Composer autoloader`.
   4. For `Path to script`, select the `autoload.php` inside mounted `wordpress-develop-tests` directory `e.g /path/to/project/storekeeper-woocommerce-b2c/mount/wordpress-develop-tests/wordpress-develop/vendor/autoload.php`.
   5. For `Test Runner > Default configuration file`, select the `phpunit.xml` in the project `tests` directory `e.g /path/to/project/storekeeper-woocommerce-b2c/tests/phpunit.xml`.
   > See this <a href="#test-frameworks">image</a> for reference.
3. Configure docker test server and mapping.
   1. Go to *Settings > PHP > Servers*.
   2. Add a new entry. For this guide we name it `docker-test`.
   3. Put `localhost` in the `Host` field and `8888` in the `Port` field.
   4. Check the `Use path mappings`.
   5. Map your project root directory `e.g /path/to/project/storekeeper-woocommerce-b2c` to `/var/www/html/wordpress-develop/wp-content/plugins/storekeeper-for-woocommerce`.
   6. Map your `wordpress-develop` directory inside mount folder `e.g /path/to/project/storekeeper-woocommerce-b2c/mount/wordpress-develop-tests` to `/var/www/html/wordpress-develop`.
   7. Click Apply and OK.
   > See this <a href="#test-server-and-mapping">image</a> for reference.
4. Configure project path mappings.
   1. Go to *Settings > PHP*.
   2. Select `test` CLI Interpreter that we created.
   3. `Path mappings` field will show up. Click the `folder` icon on the right side. A popup will show.
   4. Add entry. In `Local Path` column, add the project root directory `e.g /path/to/project/storekeeper-woocommerce-b2c` and map it in `Remote Path` to `/var/www/html/wordpress-develop/src/wp-content/plugins/storekeeper-for-woocommerce`.
   5. Add another entry. In `Local Path` column, add the `wordpress-develop` inside the `mount/wordpress-develop-tests` directory `e.g /path/to/project/storekeeper-woocommerce-b2c/mount/wordpress-develop-tests/wordpress-develop` and map it in `Remote Path` to `/var/www/html/wordpress-develop`.
   6. Click OK on the popup. Click Apply and OK on the PHP configuration page.
   > See this <a href="#project-path-mappings-for-test">image</a> for reference.
5. Add an External tool.
   1. Go to *Settings > Tools > External Tools*.
   2. Add an entry. For this guide we name it `docker compose build test`.
   3. Under `Tool Settings`. Set `docker` in `Program` field.
   4. Set `compose build test` in `Arguments` field.
   5. Set project root directory `e.g /path/to/project/storekeeper-woocommerce-b2c` in `Working directory` field.
   > See this <a href="#external-tool-for-building-test-container">image</a> for reference.
6. Edit configuration templates for PHPUnit.
   1. Go to `Run > Edit Configurations`.
   2. A popup will show. Click `Edit configuration templates...` at the bottom left.
   3. Select `PHPUnit` in the list.
   4. On the configuration page, check `Use alternative configuration file` then select the `phpunit.xml` inside tests folder `e.g. path/to/project/storekeeper-woocommerce-b2c/tests/phpunit.xml`.
   4. Under `Command Line`. Select the `test` CLI Interpreter we created in `Interpreter` dropdown. 
   5. Set project root folder `e.g. path/to/project/storekeeper-woocommerce-b2c` in `Custom working directory` field.
   6. Under `Before launch`, add an entry. Choose `Run External tool` then select the `docker compose build test` external tool that we created. Click Apply and OK.
   > See this <a href="#run-configuration-template">image</a> for reference.
### Debugging CLI commands
1. Add a configuration for PHP Script
   1. Go to `Run > Edit Configurations`.
   2. Click the `+` sign to add a new configuration.
   3. Select `PHP Script`.
   4. Set a name for the configuration, for this example we use `sk process-all-tasks`.
   5. Under `Configuration`, set the `wp.phar` from `johnpbloch/wp-cli-phar` inside the project vendor `e.g. path/to/project/storekeeper-woocommerce-b2c/vendor/johnpbloch/wp-cli-phar/wp` on `File` field.
   6. On the `Argument` field, set desired command to be tested `e.g sk process-all-tasks`
   7. Select `dev` CLI Interpreter that we created as the interpreter.
   > See this <a href="#php-script-configuration">image</a> for reference.


### Image configuration references

#### CLI Interpreter for development
![CLI Interpreter - Development][interpreter-dev]

#### Development server and mapping
![Development server and mapping][mapping-docker-dev]

#### CLI Interpreter for Unit test
![CLI Interpreter - Unit test][interpreter-test]

#### Test Frameworks
![Test Frameworks - Unit test][test-frameworks]

#### Test server and mapping
![Test server and mapping][mapping-docker-test]

#### Project path mappings for test
![Project path mappings for test][web-test-mappings]

#### External tool for building test container
![External tool][external-tool-build]

#### Run configuration template
![Run configuration template][run-configuration-template]

#### PHP script configuration
![PHP script configuration][php-script-configuration]

<!-- ADVANCED DEVELOPMENT GUIDE -->
## Advanced development guide

### Setting up the webhook to local docker
Services like cloudflare zero conf tunnel can be used.
https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/

Then change the `WordPress Address (URL)` and `Site Address (URL)` to the URL given out by the service.

You can now connect using the wp command.
```bash
docker-compose exec dev wp sk connect-backend https://external_url/
# The `external_url` is the URL provided by cloudflare
```

If you want to share the site externally, set its urls to the cloudflare zero conf tunnel url.
```bash
docker-compose exec dev bash
root@8942cbe3780d:~/wordpress# wp search-replace http://localhost:8888 https://external_url/ --all-tables
# The `external_url` is the URL provided by cloudflare
```

After this you can set it back to default.
```bash
docker-compose exec dev wp option set home http://localhost:8888/
docker-compose exec dev wp option set siteurl http://localhost:8888/
```

### Using API or web hook dumps

Each time an API call or a webhook is being fired.
It will create a new json file inside `./tmp/sk-tmp/dumps`. 
Those files can be used for unit tests in `tests/data`

#### Place dump files in project

The dump files should be unified based on their parameters and moved to the correct location in the synchronisation plugin project.

The commands below are examples on how to apply this for the `sync-woocommerce-products` dump files.

Unify the dump files based on the parameters used in the calls.

`php tests/rewrite-data-based-on-tests.php tests/data/dumps`

Create the directory where to store the dump files in

`mkdir -p tests/data/commands/sync-woocommerce-products`

Copy the files to the correct directory

`mv tests/data/dumps/* tests/data/commands/sync-woocommerce-products`

Remove unnecessary dump files. All dump files that begin with the date/time of creation can be removed from the tests/data dumpfile directory.

#### Use dump files in tests

The best way to see how to use the data dump files in any unit test is to go to an existing unit test and see how it's done there. Below I've place the three most important functions.

Use data dump for api calls made by the unit test.

`$this->mockApiCallsFromDirectory( DATADUMP_DIRECTORY, true );`

Read content of command data dump to use in the unit test.

```php
$file = $this->getDataDump( PATH_TO_DATADUMP_SOURCE_FILE );
$data = $file->getReturn()['data'];
```
Read content of hook data dump to use in the unit test.
```php
$file = $this->getHookDataDump( PATH_TO_DATADUMP_SOURCE_FILE );
```

### Adding SQL migrations

Add new class in `src/StoreKeeper/WooCommerce/B2C/Migrations/Versions` then include this class as last in 
`StoreKeeper\WooCommerce\B2C\Migrations\AllVersions::VERSION`.

Version migration will run if plugin is updated or activated.

You can find the new version in `wp_storekeeper_migration_versions` table after update/activate.

When adding  migration versions keep in mind that [MySQL has autocommit](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html) 
on some changes. Make sure you place those in separate versions so the migrations remain atomic.


<!-- DEVELOPMENT NOTES -->
## Development notes

### Hooks
1. `storekeeper_order_tracking_message` - Used for overriding the default message for track & trace on customer order page.
   1. Type - `Filter`
   2. Parameters - `$message`,`$url`
   3. Triggering class - `StoreKeeper\WooCommerce\B2C\Frontend\Handlers\OrderHookHandler`

### Tagging a new release

Tagging a commit will trigger a release build on GitHub.

```bash
git tag 5.2.0
git push origin 5.2.0
```

<!-- MARKDOWN LINKS & IMAGES -->
<!-- https://www.markdownguide.org/basic-syntax/#reference-style-links -->
[contributors-shield]: https://img.shields.io/github/contributors/storekeeper-company/storekeeper-woocommerce-b2c.svg?style=for-the-badge
[contributors-url]: https://github.com/storekeeper-company/storekeeper-woocommerce-b2c/graphs/contributors
[issues-shield]: https://img.shields.io/github/issues/storekeeper-company/storekeeper-woocommerce-b2c.svg?style=for-the-badge
[issues-url]: https://github.com/storekeeper-company/storekeeper-woocommerce-b2c/issues
[linkedin-shield]: https://img.shields.io/badge/-LinkedIn-black.svg?style=for-the-badge&logo=linkedin&colorB=555
[linkedin-url]: https://nl.linkedin.com/company/storekeeper

[logo]: logo-blue.png
[interpreter-test]: interpreter-test.png
[test-frameworks]: test-framework.png
[web-test-mappings]: web-test-mappings.png
[mapping-docker-test]: mapping-docker-test.png
[external-tool-build]: external-tool-build.png
[run-configuration-template]: run-configuration-template.png
[interpreter-dev]: interpreter-dev.png
[mapping-docker-dev]: mapping-docker-dev.png
[php-script-configuration]: php-script-configuration.png
