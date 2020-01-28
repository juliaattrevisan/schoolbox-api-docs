# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'yaml'

# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"


# Load Defaults
default = YAML.load_file('vagrant.default.yaml')
vagrant_box = default["vagrant_box"]
memory = default["memory"]
external_db = default["external_db"]
external_db_host = default["external_db_host"]
external_db_db = default["external_db_db"]
public_network = default["public_network"]
private_network_ip = default["private_network_ip"]
provision_script = default["provision_script"]
data_source = default["data_source"]
data_filename = default["data_filename"]
enable_xdebug = default["enable_xdebug"]
bridged_network_interface = default["bridged_network_interface"]
puppet_dir = default["puppet_dir"]
puppet_debug = default["puppet_debug"]

# Load custom options
if File.file?('vagrant.yaml')
  conf = YAML.load_file('vagrant.yaml')

  if !conf["vagrant_box"].nil?
    vagrant_box = conf["vagrant_box"]
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
  if !conf["external_db_db"].nil?
    external_db_db = conf["external_db_db"]
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
  if !conf["provision_script"].nil?
    provision_script = conf["provision_script"]
  end
  if !conf["data_source"].nil?
    data_source = conf["data_source"]
  end
  if !conf["data_filename"].nil?
    data_filename = conf["data_filename"]
  end
  if !conf["enable_xdebug"].nil?
    enable_xdebug = conf["enable_xdebug"]
  end
  if !conf["bridged_network_interface"].nil?
    bridged_network_interface = conf["bridged_network_interface"]
  end
  if !conf["puppet_dir"].nil?
    puppet_dir = conf["puppet_dir"]
  end
  if !conf["puppet_debug"].nil?
    puppet_debug = conf["puppet_debug"]
  end

end

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

  config.vm.box = vagrant_box
  config.vm.box_url = "#{data_source}/#{vagrant_box}.box"

  config.vm.network :private_network, ip: private_network_ip

  if public_network
    if bridged_network_interface
        config.vm.network "public_network", bridge: "#{bridged_network_interface}"
    else
        config.vm.network :public_network
    end
  end

  config.vm.provider :virtualbox do |vb|
    vb.customize ["modifyvm", :id, "--memory", memory]
    # Workaround issues in Vitualbox < 5.1.6
    vb.customize ['modifyvm', :id, '--cableconnected1', 'on']
  end

  config.vm.provider "vmware_fusion" do |vf|
    vf.vmx["memsize"] = memory
  end

  # Puppet Apply Provisioner (without a puppet master)
  # See https://www.vagrantup.com/docs/provisioning/puppet_apply.html
  config.vm.synced_folder "#{puppet_dir}/environments/staging/hieradata/node", "/etc/puppetlabs/code/hieradata/node"

  config.vm.provision "puppet" do |puppet|
    puppet.hiera_config_path = "#{puppet_dir}/environments/staging/hiera.yaml"
    puppet.manifest_file = "site.pp"
    puppet.manifests_path = "#{puppet_dir}/environments/staging/manifests"
    puppet.module_path = "#{puppet_dir}/environments/staging/modules"
    puppet.environment_path = "#{puppet_dir}/environments"
    puppet.environment = "staging"
    puppet.working_directory = "/tmp/vagrant-puppet"
    if puppet_debug
        puppet.options = "--verbose --debug"
    end
  end

  config.vm.provision "shell" do |s|
    s.path = "#{provision_script}"
    s.args = "#{data_source} \"#{data_filename}\" #{external_db} #{external_db_host} #{external_db_db} #{enable_xdebug}"
  end
  config.vm.provision "shell", run: "always", path: "tools/vagrant-restart.sh"
end
