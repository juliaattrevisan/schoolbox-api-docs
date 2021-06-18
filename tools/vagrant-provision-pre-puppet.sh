#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.

# Global constants
readonly cache_dir='/vagrant/vagrantcache';

mkdir -p $cache_dir;
cd $cache_dir || exit 1;  # TODO do we need to?

# Ensure running an appropriate era vagrant box
if [ "$(cat /home/vagrant/.vagrant.buildtimestamp)" -gt 1612117411 ]; then
  echo "[SB][$(date +%Y-%m-%d\ %X)] Vagrant box meets minimum requirements: 1612117411 Current: $(cat /home/vagrant/.vagrant.buildtimestamp)";
else
  echo "Error: Your vagrant box is out of date. Please ensure you are using the latest version (>= 1612117411). Current: $(cat /home/vagrant/.vagrant.buildtimestamp)" >&2;
  echo "To fix this:";
  echo "  vagrant destroy;";
  echo "  vagrant box remove ubuntu-1404-php71;";
  echo "  vagrant box remove ubuntu-1804-php71;";
  if [ -f /vagrant/vagrant.yaml ] && [ "$(grep vagrant_box /vagrant/vagrant.yaml | grep -vc '#')" -gt 0 ]; then
    echo "then:";
    echo "  remove the 'vagrant_box' line from your vagrant.yaml file";
  fi
  echo "then:";
  echo "  vagrant up;";
  exit 1;
fi;

# Ensure .env environment file is present
if [ ! -f /vagrant/.env ]; then
    echo "Error: '.env' environment file missing. See LastPass for details" >&2;
    exit 1;
fi

# Function to fetch value from .env file by key
# Usage: value=$(read_var $key)
read_var() {
    VAR=$(grep -E "^\s*$1" /vagrant/.env | xargs)
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


# We want to be able to reference schoolbox code at the standard location,
# but have the disks on the shared mount se we can directly manipulate from the host
rm -rf /usr/share/schoolbox
ln -sf /vagrant /usr/share/schoolbox

rm -rf /var/lib/schoolbox /vagrant/data
mkdir -p /vagrant/data
mkdir -p /mnt/data
ln -sf /vagrant/data /var/lib/schoolbox

# Does vagrant.yaml file indicate we wish this host to use a custom IR endpoint?
# We would do this if we wished to test an integration between Schoolbox, Puppet + IR
instance_registry_url_override=$1
instance_registry_api_token=$2
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
if [[ -n "$3" ]]; then data_source=$3; else echo "Please provide data_source"; exit 1; fi;
s3Referer=$(read_var AWS_S3_REFERER);
if ! /vagrant/tools/download-s3-file.sh --data-source "$data_source" --s3-referer "$s3Referer" --file-name "tls-vagrant.dev.schoolbox.cloud.tar.gz" --cache-dir "$cache_dir"; then
  echo "Error: Unable to download s3 file: 'tls-vagrant.dev.schoolbox.cloud.tar.gz'";
  exit 1;
fi
tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/fullchain.pem > /etc/nginx/certs/schoolbox-vagrantx-fullchain.crt
tar xzfO $cache_dir/tls-vagrant.dev.schoolbox.cloud.tar.gz vagrant.dev.schoolbox.cloud/privkey.pem > /etc/nginx/certs/schoolbox-vagrantx-private.key

# Remove old style named cert/key
rm -f /etc/nginx/certs/schoolbox-fullchain.crt /etc/nginx/certs/schoolbox-private.key
