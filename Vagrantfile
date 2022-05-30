# -*- mode: ruby -*-
# vi: set ft=ruby :

require 'yaml'
# Vagrantfile API/syntax version. Don't touch unless you know what you're doing!
VAGRANTFILE_API_VERSION = "2"


# Load Defaults
default = YAML.load_file('vagrant.default.yaml')
vagrant_box = default["vagrant_box"]
memory = default["memory"]
processors = default["processors"]
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
instance_registry_url_override = default["instance_registry_url_override"]
instance_registry_api_key = default["instance_registry_api_key"]
instance_id_env_var = default["instance_id_env_var"]
puppet_sync_type = default["puppet_sync_type"]
force_upgrade = default["force_upgrade"]
extra_hostname = default["extra_hostname"]
aws_iam_key = default["aws_iam_key"]
aws_iam_secret = default["aws_iam_secret"]
primary_php_majver = default["primary_php_majver"]
taskd_dir = default["taskd_dir"]
taskd_sync_type = default["taskd_sync_type"]
taskd_build_from_local = default["taskd_build_from_local"]

# Load custom options
if File.file?('vagrant.yaml')
  conf = YAML.load_file('vagrant.yaml')

  if !conf["vagrant_box"].nil?
    vagrant_box = conf["vagrant_box"]
  end
  if !conf["memory"].nil?
    memory = conf["memory"]
  end
  if !conf["processors"].nil?
    processors = conf["processors"]
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
  if !conf["instance_registry_url_override"].nil?
    instance_registry_url_override = conf["instance_registry_url_override"]
  end
  if !conf["instance_registry_api_key"].nil?
    instance_registry_api_key = conf["instance_registry_api_key"]
  end
  if !conf["instance_id_env_var"].nil?
    instance_id_env_var = conf["instance_id_env_var"]
  end
  if !conf["puppet_sync_type"].nil?
    puppet_sync_type = conf["puppet_sync_type"]
  end
  if !conf["force_upgrade"].nil?
    force_upgrade = conf["force_upgrade"]
  end
  if !conf["extra_hostname"].nil?
    extra_hostname = conf["extra_hostname"]
  end
  if !conf["aws_iam_key"].nil?
    aws_iam_key = conf["aws_iam_key"]
  end
  if !conf["aws_iam_secret"].nil?
    aws_iam_secret = conf["aws_iam_secret"]
  end
  if !conf["primary_php_majver"].nil?
    primary_php_majver = conf["primary_php_majver"]
  end
  if !conf["taskd_dir"].nil?
    taskd_dir = conf["taskd_dir"]
  end
  if !conf["taskd_sync_type"].nil?
    taskd_sync_type = conf["taskd_sync_type"]
  end
  if !conf["taskd_build_from_local"].nil?
    taskd_build_from_local = conf["taskd_build_from_local"]
  end
end

Vagrant.configure(VAGRANTFILE_API_VERSION) do |config|

  config.vagrant.plugins = "vagrant-vbguest"
  if puppet_sync_type != 'none'
    config.trigger.before :provision do |sync|
      sync.name = "Syncing Puppet"
      sync.run = {inline: "./tools/vagrant-sync-repo.sh #{puppet_sync_type} #{puppet_dir} stable"}
    end
  end

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
    vb.customize ["modifyvm", :id, "--memory", memory, "--cpus", processors]
    # Workaround issues in Vitualbox < 5.1.6
    vb.customize ['modifyvm', :id, '--cableconnected1', 'on']
  end

  config.vm.provider "vmware_fusion" do |vf|
    vf.vmx["memsize"] = memory
  end

  config.vm.provision "shell" do |s|
    s.path = "tools/vagrant-provision-pre-puppet.sh"
    s.args = "'#{instance_registry_url_override}' '#{instance_registry_api_key}' '#{data_source}' '#{extra_hostname}' '#{aws_iam_key}' '#{aws_iam_secret}'"
  end


  # We cannot mount package files with ownership that simulates real deployments because we want to be able to run
  # composer which needs to write to this path. And we don't want to run composer as root.
  config.vm.synced_folder ".", "/usr/share/schoolbox"


  # Mount the puppet directory and execute the puppet hook.
  # This enables us to invoke current environment code for non-current SB versions.
  # This script runs inside vagrant and can access the shared mount at '/tmp/puppet'
  config.vm.synced_folder "#{puppet_dir}/keys", "/tmp/puppet/keys"
  config.vm.synced_folder "#{puppet_dir}/vagrant/hooks", "/tmp/puppet/hooks"
  config.vm.provision "shell", inline: "/tmp/puppet/hooks/provision-pre-puppet --data-source '#{data_source}' --iam-access-key '#{aws_iam_key}' --iam-secret-key '#{aws_iam_secret}'"


  # Sync the taskd shared folder, if taskd_build_from_local is on
  if taskd_build_from_local
    config.vm.synced_folder taskd_dir, "/tmp/taskd"

    # also sync the folder
    if taskd_sync_type != 'none'
      config.trigger.before :provision do |sync|
        sync.name = "Syncing taskd"
        sync.run = {inline: "./tools/vagrant-sync-repo.sh #{taskd_sync_type} #{taskd_dir} master"}
      end
    end
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
    puppet.facter = {
      "suppress_instance_configuration" => true,
      "primary_php_majver" => primary_php_majver,
      "taskd_systemd_service_installed" => taskd_build_from_local ? "0" : "1",
      "iam_access_key" => "#{aws_iam_key}",
      "iam_secret_key" => "#{aws_iam_secret}",
    }
  end

  config.vm.provision "shell" do |s|
    s.path = "#{provision_script}"
    s.args = \
        "'#{data_source}' " \
        + "'#{data_filename}' " \
        + "'#{external_db}' " \
        + "'#{external_db_host}' " \
        + "'#{external_db_db}' " \
        + "'#{enable_xdebug}' " \
        + "'#{instance_id_env_var}' " \
        + "'#{force_upgrade}' " \
        + "'#{instance_registry_url_override}' " \
        + "'#{extra_hostname}' " \
        + "'#{primary_php_majver}' " \
        + "'#{taskd_build_from_local ? 1 : 0}' " \
        + "'#{aws_iam_key}' " \
        + "'#{aws_iam_secret}'"
  end
  config.vm.provision "shell", run: "always", path: "tools/vagrant-restart.sh", args: "'#{primary_php_majver}' '#{taskd_build_from_local ? 1 : 0}'"
end
