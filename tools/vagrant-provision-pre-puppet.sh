#!/bin/bash
# This script runs prior to the puppet provisioner.
# It is used to ensure that when puppet runs, the VM is in a similar state as
# when puppet is invoked during a real live schoolbox[dev] installation.
if [[ ! -f /etc/schoolbox/conf.php ]]; then
    mkdir -p /etc/schoolbox;
    ln -sf /vagrant/www/conf/conf.php /etc/schoolbox/conf.php;
fi;
if [[ ! -f /etc/schoolboxdev/conf.php ]]; then
    mkdir -p /etc/schoolboxdev;
    ln -sf /vagrant/www/conf/conf.php /etc/schoolboxdev/conf.php;
fi;
