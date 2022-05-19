#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.
# Cannot directly/indirectly depend on puppet-provisioned files as puppet may not have run yet.

# Global constants
readonly cache_dir='/usr/share/schoolbox/vagrantcache'

# Global vars
declare instance_registry_url_override
declare instance_registry_api_token
declare data_source
declare extra_hostname
declare aws_iam_key
declare aws_iam_secret

function parse_args() {
  instance_registry_url_override=$1
  instance_registry_api_token=$2
  data_source=$3
  extra_hostname=$4
  aws_iam_key=$5
  aws_iam_secret=$6
}

function validate_args() {
  if [[ $# -ne 6 ]]; then
    error "Script requires 6 params, found $#"
    exit 1
  fi
  if [[ -z "$data_source" ]]; then
    echo "Please provide data_source" >&2
    exit 1
  fi
  if [[ -z $aws_iam_key ]]; then
    echo "Please provide IAM Access Key" >&2
    exit 1
  fi
  if [[ -z $aws_iam_secret ]]; then
    echo "Please provide IAM Secret Key" >&2
    exit 1
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
  if [ "$(cat /home/vagrant/.vagrant.buildtimestamp)" -gt 1612117411 ]; then
    echo "[SB][$(date +%Y-%m-%d\ %X)] Vagrant box meets minimum requirements: 1612117411 Current: $(cat /home/vagrant/.vagrant.buildtimestamp)"
  else
    echo "Error: Your vagrant box is out of date. Please ensure you are using the latest version (>= 1612117411). Current: $(cat /home/vagrant/.vagrant.buildtimestamp)" >&2
    echo "To fix this:"
    echo "  vagrant destroy;"
    echo "  vagrant box remove ubuntu-1404-php71;"
    echo "  vagrant box remove ubuntu-1804-php71;"
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
    mysql --defaults-extra-file=/root/.my.cnf -e "DROP DATABASE IF EXISTS schoolbox"

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

# Remove old single-instance daemons
function remove_old_notification_daemons() {
  for service in socketd digestd routed scheduled; do
    if systemctl --plain --no-legend | grep -q "schoolbox-$service.service"; then
      systemctl stop "schoolbox-$service.service"
    fi
    find /etc/systemd/system/multi-user.target.wants/ -iname "schoolbox-$service.service" -delete
  done
}

function deploy_nginx_ssl_cert() {
  # vagrant does not use LE to deploy SSL certs, we need to deploy certs prior to running puppet so puppet can configure nginx vhosts
  # If no extra hostname, just use vagrant cert. Otherwise we'll need the wildcard cert
  if [[ -z $extra_hostname ]]; then
    # Download vagrant cert
    s3Referer="TODO-invalid" # TODO replace this with IAM cred auth
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
    file="certs/$filename"
    bucket='schoolbox-cloud-tls'
    resource="/${bucket}/${file}"
    dateValue="$(date -u +'%a, %d %b %Y %H:%M:%S GMT')"
    contentType="application/x-compressed-tar"
    stringToSign="GET\n\n${contentType}\n\nx-amz-date:${dateValue}\n${resource}"
    signature=$(/bin/echo -en "$stringToSign" | openssl sha1 -hmac "${aws_iam_secret}" -binary | base64)
    if ! curl --silent --show-error -H "Host: ${bucket}.s3.amazonaws.com" \
      -H "x-amz-date: ${dateValue}" \
      -H "Content-Type: ${contentType}" \
      -H "Authorization: AWS ${aws_iam_key}:${signature}" \
      https://${bucket}.s3.amazonaws.com/${file} \
      -o $cache_dir/$filename; then
      echo "Unable to download wildcard certificate" >&2
      exit 1
    fi
    tar xzfO $cache_dir/$filename dev.schoolbox.cloud/fullchain.pem >/etc/nginx/certs/schoolbox-vagrantx-fullchain.crt
    tar xzfO $cache_dir/$filename dev.schoolbox.cloud/privkey.pem >/etc/nginx/certs/schoolbox-vagrantx-private.key
    tar xzfO $cache_dir/$filename dev.schoolbox.cloud/chain.pem >/etc/nginx/certs/schoolbox-vagrantx-intermediate.crt
  fi
}

function remove_old_nginx_certs() {
  # Remove old style named cert/key
  rm -f /etc/nginx/certs/schoolbox-fullchain.crt /etc/nginx/certs/schoolbox-private.key
}

function main() {
  parse_args "$@"
  validate_args "$@"
  create_cache_dir
  create_mount_dir
  check_vagrant_box_version
  drop_mysql_schema_and_user
  create_ir_conf_file
  remove_old_notification_daemons
  deploy_nginx_ssl_cert
  remove_old_nginx_certs
}

main "$@"
