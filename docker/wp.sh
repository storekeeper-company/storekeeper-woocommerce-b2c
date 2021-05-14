#!/bin/bash
set -euo pipefail
if [ "$EUID" -eq 0 ]
then sudo -u www-data wp-distro "$@"
else wp-distro "$@"
fi