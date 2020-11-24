#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.
if [[ ! -f /etc/schoolbox/conf.php ]]; then
    mkdir -p /etc/schoolbox;
    ln -sf /vagrant/www/conf/conf.php /etc/schoolbox/conf.php;
fi;

# This isn't a schoolboxdev
if [[ -f /etc/schoolboxdev/conf.php ]]; then
    rm /etc/schoolboxdev/conf.php;
fi;

# We want to be able to reference schoolbox code at the standard location,
# but have the disks on the shared mount se we can directly manipulate from the host
rm -rf /usr/share/schoolbox
ln -sf /vagrant /usr/share/schoolbox

rm -rf /var/lib/schoolbox /vagrant/data
mkdir /vagrant/data
ln -sf /vagrant/data /var/lib/schoolbox


# Does vagrant environment file indicate we wish this host to use a custom IR endpoint?
# We would do this if we wished to test an integration between Schoolbox, Puppet + IR
instance_registry_url_override=$1
instance_registry_conf_file="/etc/schoolbox/instance-registry.json"
if [[ -n $instance_registry_url_override ]]; then
  echo "Setting instance_registry_url_override to '$instance_registry_url_override' in $instance_registry_conf_file";
else
  echo "Using default instance registry URL";
fi
echo "{\"instance_registry_url_override\": \"${instance_registry_url_override}\"}" > $instance_registry_conf_file

# This is needed by the puppet Instance Registry API integration
if ! /opt/puppetlabs/puppet/bin/gem list | grep -q lookup_http; then
  echo "Installing 'lookup_http' gem"
  /opt/puppetlabs/puppet/bin/gem install lookup_http
fi
