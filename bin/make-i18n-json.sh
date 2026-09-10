#!/usr/bin/env bash


GREEN_BOLD='\033[1;32m';
YELLOW_BOLD='\033[1;33m';
COLOR_RESET='\033[0m';


success () {
	echo -e "\n${GREEN_BOLD}$1${COLOR_RESET}\n"
}
warning () {
	echo -e "\n${YELLOW_BOLD}$1${COLOR_RESET}\n"
}


# Substitute JS source references with build references
for T in `find languages -name "*.pot"`
	do
		sed \
			-e 's/#: src[^:]*:/#: assets\/js\/app.js:/gp' \
			$T | uniq > $T-build
		rm $T
		mv $T-build $T
	done

success "Successfully generated translations in bagisto-sync-for-woocommerce.pot 🎉 ";
success " 🎉 Happy Coding! 🎉 ";

