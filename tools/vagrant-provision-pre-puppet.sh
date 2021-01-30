#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.

# Ensure running an appropriate era vagrant box
if [ "$(cat /home/vagrant/.vagrant.buildtimestamp)" -gt 1528782104 ]; then
  echo "[SB][$(date +%Y-%m-%d\ %X)] Vagrant box meets minimum requirements: 1528782105 Current: $(cat /home/vagrant/.vagrant.buildtimestamp)";
else
  echo "Error: Your vagrant box is out of date. Please ensure you are using the latest version (>= 1528782105). Current: $(cat /home/vagrant/.vagrant.buildtimestamp)" >&2;
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

echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox MySQL database";
mysql --defaults-extra-file=/root/.my.cnf -e "DROP DATABASE IF EXISTS schoolbox";

echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox MySQL user";
mysql --defaults-extra-file=/root/.my.cnf -e "DROP USER IF EXISTS 'schoolboxvagrant'@'localhost'";

echo "[SB][$(date +%Y-%m-%d\ %X)] Removing current Schoolbox Data directory";
rm /var/lib/schoolbox/vagrantx/* -fR;

# We want to be able to reference schoolbox code at the standard location,
# but have the disks on the shared mount se we can directly manipulate from the host
rm -rf /usr/share/schoolbox
ln -sf /vagrant /usr/share/schoolbox

rm -rf /var/lib/schoolbox /vagrant/data
mkdir /vagrant/data
ln -sf /vagrant/data /var/lib/schoolbox

# See https://github.com/alaress/packer-templates/commit/3c1d0ae56ad5f870e6026fa99933373a954ae802#diff-f0a3cfe79f4fee4578823022ff15cff935dd423042caeb7c51e7d93b841de4b2
if [[ -d /etc/udev/rules.d/70-persistent-net.rules ]]; then
  echo "[SB][$(date +%Y-%m-%d\ %X)] Removing '/etc/udev/rules.d/70-persistent-net.rules' dir";
  rm -rf /etc/udev/rules.d/70-persistent-net.rules
fi

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

# This is needed by the puppet Instance Registry API integration
if ! /opt/puppetlabs/puppet/bin/gem list | grep -q lookup_http; then
  echo "Installing 'lookup_http' gem"
  /opt/puppetlabs/puppet/bin/gem install lookup_http
fi


if [[ -f /etc/cron.d/vagrant ]]; then
  rm /etc/cron.d/vagrant
fi


# Remove old single-instance daemons
for service in socketd digestd routed scheduled; do
  if systemctl | grep -q "schoolbox-$service.service"; then
    systemctl stop "schoolbox-$service.service";
  fi
  find /etc/systemd/system/multi-user.target.wants/ -iname "schoolbox-$service.service" -delete
done

# Ensure puppet provisions a compatible PHP version
echo "Setting Vagrant's schoolbox::set_packages::php_majver to '7.4'"
sed -i "s/^schoolbox::set_packages::php_majver:.*/schoolbox::set_packages::php_majver: '7.4'/g" /etc/puppetlabs/code/hieradata/node/ubuntu-1804-php71.vagrant.yaml
