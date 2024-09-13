#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.
# Cannot directly/indirectly depend on puppet-provisioned files as puppet may not have run yet.

# Global constants
readonly cache_dir='/usr/share/schoolbox/vagrantcache'
readonly VAGRANT_TIMESTAMP_MIN=1660612589              # Box must have been built at or after this

# Global vars
declare instance_registry_url_override
declare instance_registry_api_token
declare data_source
declare extra_hostname
declare aws_account_id
declare aws_role_name
declare aws_start_url
declare access_key_id
declare secret_access_key

function parse_args() {
  instance_registry_url_override=$1
  instance_registry_api_token=$2
  data_source=$3
  extra_hostname=$4
  aws_account_id=$5
  aws_role_name=$6
  aws_start_url=$7
}

function validate_args() {
  if [[ $# -ne 7 ]]; then
    error "Script requires 7 params, found $#"
    exit 1
  fi
  if [[ -z "$data_source" ]]; then
    echo "Please provide data_source" >&2
    exit 1
  fi
  if [[ -z $aws_account_id ]]; then
    echo "Please provide aws_account_id" >&2
    exit 1
  fi
  if [[ -z $aws_role_name ]]; then
    echo "Please provide aws_role_name" >&2
    exit 1
  fi
  if [[ -z $aws_start_url ]]; then
    echo "Please provide aws_start_url" >&2
    exit 1
  fi
}

function setup_aws_cli() {
  if type aws 2>/dev/null >&2; then
    echo "AWS CLI already installed"
    return
  fi
  echo "Installing AWS CLI"
  curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
  unzip -q awscliv2.zip
  ./aws/install
  rm -rf awscliv2.zip aws
}

function setup_aws_sso() {
  if [ -f /root/.aws/credentials ]; then
    if grep -q 'default' /root/.aws/credentials; then
      echo "AWS using static creds"
      return
    fi
  fi
  echo "Setting up AWS SSO"
  mkdir -p /root/.aws
  echo "[default]
sso_session = vagrant
sso_account_id = $aws_account_id
sso_role_name = $aws_role_name
region = ap-southeast-2

[sso-session vagrant]
sso_region = ap-southeast-2
sso_start_url = $aws_start_url" > /root/.aws/config

  if ! aws configure list > /dev/null; then
    aws sso login --no-browser
  fi
}

function create_cache_dir() {
  mkdir -p $cache_dir
}

function create_mount_dir() {
  #rm -rf /var/lib/schoolbox /vagrant/data  # TODO remove '/vagrant/data' once v22 is released
  mkdir -p /mnt/data
}

# Ensure running an appropriate era vagrant box
function check_vagrant_box_version() {
  if [ "$(cat /home/vagrant/.vagrant.buildtimestamp)" -ge $VAGRANT_TIMESTAMP_MIN ]; then
    echo "[SB][$(date +%Y-%m-%d\ %X)] Vagrant box meets minimum requirements: $VAGRANT_TIMESTAMP_MIN Current: $(cat /home/vagrant/.vagrant.buildtimestamp)"
  else
    echo "Error: Your vagrant box is out of date. Please ensure you are using the latest version (>= $VAGRANT_TIMESTAMP_MIN). Current: $(cat /home/vagrant/.vagrant.buildtimestamp)" >&2
    echo "To fix this:"
    echo "  vagrant destroy;"
    echo "  vagrant box remove ubuntu-1404-php71;"
    echo "  vagrant box remove ubuntu-1804-php71;"
    echo "  vagrant box remove ubuntu-1804-php74;"
    if [ -f /usr/share/schoolbox/vagrant.yaml ] && [ "$(grep vagrant_box /usr/share/schoolbox/vagrant.yaml | grep -vc '#')" -gt 0 ]; then
      echo "then:"
      echo "  remove the 'vagrant_box' line from your vagrant.yaml file"
    fi
    echo "then:"
    echo "  vagrant up;"
    exit 1
  fi
}

function drop_mysql_schema_and_user() {
  # Is mysql installed ? (it won't if packer image build)
  if type mysql 2>/dev/null >&2 && test -f /root/.my.cnf; then
    echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox MySQL database"
    mysql --defaults-extra-file=/root/.my.cnf -e "SET MAX_STATEMENT_TIME=0; DROP DATABASE IF EXISTS schoolbox"

    echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox MySQL user"
    mysql --defaults-extra-file=/root/.my.cnf -e "DROP USER IF EXISTS 'schoolboxvagrant'@'localhost'"
  fi
}

function create_ir_conf_file() {
  # Does vagrant.yaml file indicate we wish this host to use a custom IR endpoint?
  # We would do this if we wished to test an integration between Schoolbox, Puppet + IR
  instance_registry_conf_file="/etc/schoolbox/instance-registry.json"
  if [[ -n $instance_registry_url_override ]]; then
    echo "Setting instance_registry_url_override to '$instance_registry_url_override' in $instance_registry_conf_file"
  else
    echo "Using default instance registry URL"
  fi
  mkdir -p /etc/schoolbox
  echo -e \
    "{\n" \
    "  \"instance_registry_url_override\": \"${instance_registry_url_override}\",\n" \
    "  \"instance_registry_api_token\": \"${instance_registry_api_token}\"\n" \
    "}" >$instance_registry_conf_file
}

function deploy_nginx_ssl_cert() {
  # vagrant does not use LE to deploy SSL certs, we need to deploy certs prior to running puppet so puppet can configure nginx vhosts
  # If no extra hostname, just use vagrant cert. Otherwise we'll need the wildcard cert
  if [[ -z $extra_hostname ]]; then
    # Download vagrant cert
    s3Referer="TODO-invalid"
    if ! /usr/share/schoolbox/tools/download-s3-file.sh --data-source "$data_source" --s3-referer "$s3Referer" --file-name "tls-vagrant.dev.schoolbox.cloud.tar.gz" --cache-dir "$cache_dir"; then
      echo "Error: Unable to download s3 file: 'tls-vagrant.dev.schoolbox.cloud.tar.gz'"
      exit 1
    fi
    tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/fullchain.pem >/etc/nginx/certs/schoolbox-vagrantx-fullchain.crt
    tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/privkey.pem >/etc/nginx/certs/schoolbox-vagrantx-private.key
    tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/chain.pem >/etc/nginx/certs/schoolbox-vagrantx-intermediate.crt
  else
    # Download wildcard cert
    filename='tls-wildcard.dev.schoolbox.cloud.tar.gz'

    if ! /usr/share/schoolbox/tools/download-s3-file.sh --data-source "https://s3-ap-southeast-2.amazonaws.com/schoolbox-cloud-tls" \
        --file-name "certs/$filename" \
        --cache-dir "$cache_dir"; then
      echo "Unable to download wildcard certificate" >&2
      exit 1
    fi
    tar xzfO $cache_dir/$filename dev.schoolbox.cloud/fullchain.pem >/etc/nginx/certs/schoolbox-vagrantx-fullchain.crt
    tar xzfO $cache_dir/$filename dev.schoolbox.cloud/privkey.pem >/etc/nginx/certs/schoolbox-vagrantx-private.key
    tar xzfO $cache_dir/$filename dev.schoolbox.cloud/chain.pem >/etc/nginx/certs/schoolbox-vagrantx-intermediate.crt
  fi
}

function main() {
  parse_args "$@"
  validate_args "$@"
  setup_aws_cli
  setup_aws_sso
  create_cache_dir
  create_mount_dir
  check_vagrant_box_version
  drop_mysql_schema_and_user
  create_ir_conf_file
  deploy_nginx_ssl_cert
}

main "$@"
