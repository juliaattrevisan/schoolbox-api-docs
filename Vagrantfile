# -*- mode: ruby -*-
# vi: set ft=ruby :

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

  config.vm.box = "ubuntu-1204-gold-PHP55"
  config.vm.box_url = "http://vagrant.alaress-dev.com.au/ubuntu-1204-gold-PHP55.box"

  config.vm.network :private_network, ip: "192.168.36.10"

  # Create a public network, which generally matched to bridged network.
  # Bridged networks make the machine appear as another physical device on
  # your network, and is accessible by others.
  #
  # Comment this out for the vm to only be available on your machine.
  #config.vm.network :public_network

  config.vm.provider :virtualbox do |vb|
    vb.customize ["modifyvm", :id, "--memory", "2048"]
  end

  config.vm.provider "vmware_fusion" do |vf|
    vf.vmx["memsize"] = "2048"
  end

  # Default provision script
  config.vm.provision "shell" do |s|
    s.path = "tools/vagrant-provision-ubuntu-1204.sh"
    # Provision using Schoolbox as the External DB (uncomment the following)
    # s.args = "schoolbox"
  end

end
