#!/bin/bash
set -euo pipefail

TRANSLATION_FILE_BASE_NAME=storekeeper-for-woocommerce-

cd "$WP_SK_PLUGIN_DIR/i18n"
if test -f "${TRANSLATION_FILE_BASE_NAME}en_US.po"; then
    msgfmt "${TRANSLATION_FILE_BASE_NAME}en_US.po" -o "${TRANSLATION_FILE_BASE_NAME}en_US.mo"
fi

if test -f "${TRANSLATION_FILE_BASE_NAME}nl_NL.po"; then
    msgfmt "${TRANSLATION_FILE_BASE_NAME}nl_NL.po" -o "${TRANSLATION_FILE_BASE_NAME}nl_NL.mo"
fi

if test -f "${TRANSLATION_FILE_BASE_NAME}de_DE.po"; then
    msgfmt "${TRANSLATION_FILE_BASE_NAME}de_DE.po" -o "${TRANSLATION_FILE_BASE_NAME}de_DE.mo"
fi
