# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'yaml'

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"


# Defaults
vagrant_box = "ubuntu-1204-gold-PHP55"
download_url = "vagrant.alaress-dev.com.au"
memory = "1024"
external_db = "synergetic"
external_db_host = "192.168.1.20"
demo_data_version = "latest"
public_network = true
private_network_ip = "192.168.36.10"

# Load custom options
if File.file?('vagrant.yaml')
  conf = YAML.load_file('vagrant.yaml')

  if !conf["vagrant_box"].nil?
    vagrant_box = conf["vagrant_box"]
  end
  if !conf["download_url"].nil?
    download_url = conf["download_url"]
  end
  if !conf["memory"].nil?
    memory = conf["memory"]
  end
  if !conf["external_db"].nil?
    external_db = conf["external_db"]
  end
  if !conf["external_db_host"].nil?
    external_db_host = conf["external_db_host"]
  end
  if !conf["demo_data_version"].nil?
    demo_data_version = conf["demo_data_version"]
  end
  if !conf["public_network"].nil?
    public_network = conf["public_network"]
  end
  if !conf["private_network_ip"].nil?
    private_network_ip = conf["private_network_ip"]
  end

end

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

  config.vm.box = vagrant_box
  config.vm.box_url = "http://#{download_url}/#{vagrant_box}.box"

  config.vm.network :private_network, ip: private_network_ip

  if public_network
    config.vm.network :public_network
  end

  config.vm.provider :virtualbox do |vb|
    vb.customize ["modifyvm", :id, "--memory", memory]
  end

  config.vm.provider "vmware_fusion" do |vf|
    vf.vmx["memsize"] = memory
  end

  config.vm.provision "shell" do |s|
    s.path = "tools/vagrant-provision-ubuntu-1204.sh"
    s.args = "#{download_url} #{demo_data_version} #{external_db} #{external_db_host}"
  end

end
