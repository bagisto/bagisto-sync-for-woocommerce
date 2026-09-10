#!/bin/bash
PLUGIN_SLUG="bagisto-sync-for-woocommerce"
PROJECT_PATH=$(pwd)

# Exit if any command fails.
set -e

# Change to the expected directory.
cd "$(dirname "$0")"


# Enable nicer messaging for build status.
BLUE_BOLD='\033[1;34m';
GREEN_BOLD='\033[1;32m';
RED_BOLD='\033[1;31m';
YELLOW_BOLD='\033[1;33m';
COLOR_RESET='\033[0m';
error () {
	echo -e "\n${RED_BOLD}$1${COLOR_RESET}\n"
}
status () {
	echo -e "\n${BLUE_BOLD}$1${COLOR_RESET}\n"
}
success () {
	echo -e "\n${GREEN_BOLD}$1${COLOR_RESET}\n"
}
warning () {
	echo -e "\n${YELLOW_BOLD}$1${COLOR_RESET}\n"
}

status "💃 Time to release Bagisto Sync for WooCommerce 🙂"

warning "Ready to proceed to release? [Y/N]: "
read -r PROCEED

if [ "$(echo "${PROCEED:-n}" | tr "[:upper:]" "[:lower:]")" != "y" ]; then
	error "Release cancelled!"
	exit 1
else
	status "Generating build..."
	npm run buildr || exit "$?"
fi


status "Done. You've built Bagisto Sync for WooCommerce! 🎉 "
warning "Generating language files..."
exit


