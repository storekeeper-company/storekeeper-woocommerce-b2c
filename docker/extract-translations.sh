#!/bin/bash
set -euo pipefail
cd "$WP_SK_PLUGIN_DIR" && php dev-tools/make-pot.php
