#!/bin/bash
#
# Runs `puppet apply` inside vagrant environment to quickly test puppet changes
# Example usage:
#   ./puppet-apply.sh
#   ./puppet-apply.sh --debug
#
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

readonly DEFAULT='\e[39m';
readonly RED='\e[91m';

##################################################################################
# Main logic flow:
# * Check running as root
# * Parse puppet vars
# * Execute puppet apply
##################################################################################
function main() {
  check_running_as_root
  local module_path
  module_path=$(find /tmp/vagrant-puppet/ -maxdepth 1 -iname 'modules-*')
  local hiera_config='/tmp/vagrant-puppet/hiera.yaml'
  local environment_path='/tmp/vagrant-puppet/environments/'
  local environment='staging'
  local manifest_file
  manifest_file=$(find /tmp/vagrant-puppet/ -maxdepth 1 -iname 'manifests-*')"/site.pp"

  /opt/puppetlabs/puppet/bin/ruby /opt/puppetlabs/puppet/bin/puppet apply \
    --modulepath "$module_path" \
    --hiera_config="$hiera_config" \
    --detailed-exitcodes \
    --environmentpath "$environment_path" \
    --environment "$environment" \
    "$manifest_file" "$@"

  echo "[SB][$(date +%Y-%m-%d\ %X)] Enabling Schoolbox nginx vhost";
  chown -fR vagrant:vagrant /mnt/data/php_tmp;
  sed -i 's/php_flag\[display_startup_errors\]\ \=\ off/php_flag\[display_startup_errors\]\ \=\ on/g' /etc/php/*/fpm/pool.d/schoolbox*.conf;
  sed -i 's/php_flag\[display_errors\]\ \=\ off/php_flag\[display_errors\]\ \=\ on/g' /etc/php/*/fpm/pool.d/schoolbox*.conf;
  sed -i 's/php_flag\[html_errors\]\ \=\ off/php_flag\[html_errors\]\ \=\ on/g' /etc/php/*/fpm/pool.d/schoolbox*.conf;
  sed -i 's/www-data/vagrant/g' /etc/php/*/fpm/pool.d/schoolbox*;
  # disable nginx open file cache since not a production server
  sed -i '/open_file_cache/d' /etc/nginx/nginx.conf;

  service php7.4-fpm restart
}

##################################################################################
# Check script is being run as root, exits with error if not root.
##################################################################################
function check_running_as_root() {
  if [[ $EUID -ne 0 ]]; then
    err  "Error - please run script as root."
    exit 1
  fi
}

##################################################################################
# Log message to STDERR
##################################################################################
err() {
  echo -e "${RED}[$(date +'%Y-%m-%dT%H:%M:%S%z')]: $*${DEFAULT}" >&2
}

main "$@"
