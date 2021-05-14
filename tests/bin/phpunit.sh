#!/usr/bin/env bash

# from https://raw.githubusercontent.com/woocommerce/woocommerce/trunk/tests/bin/phpunit.sh
if [[ ${RUN_PHPCS} == 1 ]] || [[ ${RUN_E2E} == 1 ]]; then
	exit
fi

if [[ ${RUN_CODE_COVERAGE} == 1 ]]; then
	phpdbg -qrr ./vendor/bin/phpunit -d memory_limit=-1 -c phpunit.xml --coverage-clover=coverage.clover --exclude-group=timeout $@
else
	vendor/bin/phpunit -c phpunit.xml $@
fi
