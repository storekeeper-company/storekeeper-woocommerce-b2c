#!/bin/bash
set -euo pipefail

TRANSLATION_FILE_BASE_NAME=storekeeper-woocommerce-b2c-

cd "$WP_SK_PLUGIN_DIR/i18n"
if test -f "${TRANSLATION_FILE_BASE_NAME}en.po"; then
    msgfmt "${TRANSLATION_FILE_BASE_NAME}en.po" -o "${TRANSLATION_FILE_BASE_NAME}en.mo"
fi

if test -f "${TRANSLATION_FILE_BASE_NAME}nl.po"; then
    msgfmt "${TRANSLATION_FILE_BASE_NAME}nl.po" -o "${TRANSLATION_FILE_BASE_NAME}nl.mo"
fi

if test -f "${TRANSLATION_FILE_BASE_NAME}de.po"; then
    msgfmt "${TRANSLATION_FILE_BASE_NAME}de.po" -o "${TRANSLATION_FILE_BASE_NAME}de.mo"
fi
