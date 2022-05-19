#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.

# Global constants
readonly cache_dir='/usr/share/schoolbox/vagrantcache';

mkdir -p $cache_dir;

if [[ $# -ne 6 ]]; then
  error "Script requires 6 params, found $#"
  exit 1
fi
instance_registry_url_override=$1
instance_registry_api_token=$2
data_source=$3
extra_hostname=$4
aws_iam_key=$5
aws_iam_secret=$6

if [[ -z "$data_source" ]]; then
  echo "Please provide data_source" >&2;
  exit 1;
fi;
if [[ -n $extra_hostname ]]; then
  if [[ -z $aws_iam_key ]] || [[ -z $aws_iam_secret ]]; then
    echo "If provisioning extra domain name '$extra_hostname', you must provide both IAM key + secret" >&2
    exit 1
  fi
fi

# Ensure running an appropriate era vagrant box
if [ "$(cat /home/vagrant/.vagrant.buildtimestamp)" -gt 1612117411 ]; then
  echo "[SB][$(date +%Y-%m-%d\ %X)] Vagrant box meets minimum requirements: 1612117411 Current: $(cat /home/vagrant/.vagrant.buildtimestamp)";
else
  echo "Error: Your vagrant box is out of date. Please ensure you are using the latest version (>= 1612117411). Current: $(cat /home/vagrant/.vagrant.buildtimestamp)" >&2;
  echo "To fix this:";
  echo "  vagrant destroy;";
  echo "  vagrant box remove ubuntu-1404-php71;";
  echo "  vagrant box remove ubuntu-1804-php71;";
  if [ -f /usr/share/schoolbox/vagrant.yaml ] && [ "$(grep vagrant_box /usr/share/schoolbox/vagrant.yaml | grep -vc '#')" -gt 0 ]; then
    echo "then:";
    echo "  remove the 'vagrant_box' line from your vagrant.yaml file";
  fi
  echo "then:";
  echo "  vagrant up;";
  exit 1;
fi;

# Ensure .env environment file is present
if [ ! -f /usr/share/schoolbox/.env ]; then
    echo "Error: '.env' environment file missing. See LastPass for details" >&2;
    exit 1;
fi

# Function to fetch value from .env file by key
# Usage: value=$(read_var $key)
read_var() {
    VAR=$(grep -E "^\s*$1" /usr/share/schoolbox/.env | xargs)
    IFS="=" read -ra VAR <<< "$VAR"
    echo "${VAR[1]}"
}


# Is mysql installed ? (it won't if packer image build)
if type mysql 2>/dev/null >&2 && test -f /root/.my.cnf; then
  echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox MySQL database";
  mysql --defaults-extra-file=/root/.my.cnf -e "DROP DATABASE IF EXISTS schoolbox";

  echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox MySQL user";
  mysql --defaults-extra-file=/root/.my.cnf -e "DROP USER IF EXISTS 'schoolboxvagrant'@'localhost'";
fi


#rm -rf /var/lib/schoolbox /vagrant/data  # TODO remove '/vagrant/data' once v22 is released
mkdir -p /mnt/data

# Does vagrant.yaml file indicate we wish this host to use a custom IR endpoint?
# We would do this if we wished to test an integration between Schoolbox, Puppet + IR
instance_registry_conf_file="/etc/schoolbox/instance-registry.json"
if [[ -n $instance_registry_url_override ]]; then
  echo "Setting instance_registry_url_override to '$instance_registry_url_override' in $instance_registry_conf_file";
else
  echo "Using default instance registry URL";
fi
mkdir -p /etc/schoolbox
echo -e \
"{\n" \
"  \"instance_registry_url_override\": \"${instance_registry_url_override}\",\n" \
"  \"instance_registry_api_token\": \"${instance_registry_api_token}\"\n" \
"}" > $instance_registry_conf_file

if [[ -f /etc/cron.d/vagrant ]]; then
  rm /etc/cron.d/vagrant
fi

# Remove old single-instance daemons
for service in socketd digestd routed scheduled; do
  if systemctl --plain --no-legend | grep -q "schoolbox-$service.service"; then
    systemctl stop "schoolbox-$service.service";
  fi
  find /etc/systemd/system/multi-user.target.wants/ -iname "schoolbox-$service.service" -delete
done


# vagrant does not use LE to deploy SSL certs, we need to deploy certs prior to running puppet so puppet can configure nginx vhosts
# If no extra hostname, just use vagrant cert. Otherwise we'll need the wildcard cert
if [[ -z $extra_hostname ]]; then
  # Download vagrant cert
  s3Referer=$(read_var AWS_S3_REFERER);
  if ! /usr/share/schoolbox/tools/download-s3-file.sh --data-source "$data_source" --s3-referer "$s3Referer" --file-name "tls-vagrant.dev.schoolbox.cloud.tar.gz" --cache-dir "$cache_dir"; then
    echo "Error: Unable to download s3 file: 'tls-vagrant.dev.schoolbox.cloud.tar.gz'";
    exit 1;
  fi
  tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/fullchain.pem > /etc/nginx/certs/schoolbox-vagrantx-fullchain.crt
  tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/privkey.pem > /etc/nginx/certs/schoolbox-vagrantx-private.key
  tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/chain.pem > /etc/nginx/certs/schoolbox-vagrantx-intermediate.crt
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
  tar xzfO $cache_dir/$filename dev.schoolbox.cloud/fullchain.pem > /etc/nginx/certs/schoolbox-vagrantx-fullchain.crt
  tar xzfO $cache_dir/$filename dev.schoolbox.cloud/privkey.pem > /etc/nginx/certs/schoolbox-vagrantx-private.key
  tar xzfO $cache_dir/$filename dev.schoolbox.cloud/chain.pem > /etc/nginx/certs/schoolbox-vagrantx-intermediate.crt
fi

# Remove old style named cert/key
rm -f /etc/nginx/certs/schoolbox-fullchain.crt /etc/nginx/certs/schoolbox-private.key
