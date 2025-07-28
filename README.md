# Schoolbox

This README outlines the relevant information needed for developers, operations and support to familiarise with this system.

This file is internal use and is not shipped to clients.

## Directory and Structure

* conf - initialisation code for Schoolbox modules, see the [MVC docs](./docs/mvc/readme.md)
  for more information
* dav - WebDav site for exporting the Schoolbox resources
* data - client data and caches (client side only)
* debian - APT package building definitions (not deployed to clients)
* docker - definition of Docker images used in Schoolbox development and
  production environments
* docs - documentation for developers
* library - Schoolbox code base
* node_modules - directory for external libraries loaded via NPM
* resources - directory for storing static assets which may be compiled or used
  by scripts, and which do not directly relate to a module within `library/`
* sql - initial sql schema configuration for new installations
* templates - default and per client skins
* test - PHP and other tests (not deployed to clients)
* tools - Build, update and maintenance tools. Also includes cli scripts for sync.
* updates - per release update files, primarily sql schema changes
* www - web root directory
* www/static - web root directory for static assets (JS, CSS, images) only
* vendor - directory for external libraries loaded via Composer
* vue - directory for storing Vue components that are not directly related to a
  module within `library/`

## Installation

### Download Schoolbox code

* Create an SSH key and add to Github
* Fork the [Schoolbox repo](https://github.com/alaress/schoolbox) to your Github:
  ```shell
  git clone git@github.com:%YourGitHubUsername%/schoolbox.git schoolbox (for example git clone git@github.com:low613/schoolbox.git schoolbox)
  cd schoolbox
  git remote add upstream git@github.com:alaress/schoolbox.git
  git fetch upstream
  git checkout origin/master
  git checkout -b master
  ```
* Install the [Git Scripts](https://github.com/alaress/git-scripts) suite of tools:
  * If the `jira-issue-tag` doesn't work after install, add the file from the downloaded zip file at `git-scripts-master/hooks/prepare-commit-msg.jira-issue-tag` into `.git/hooks/` and remove the `.jira-issue-tag` from the file name.

### Set up Docker development environment

The Docker development environment closely matches the proposed cloud-hosted
environment, which will be deployed to scalable containers and orchestrated via
AWS' [Elastic Container Service](https://docs.aws.amazon.com/ecs/).

Ideally, only the Docker development environment will need to be set up.

* install the most recent [Docker Engine](https://docs.docker.com/engine/install/)
  for your operating system
* add this line to `/etc/hosts` or its Windows equivalent
  (usually `C:\Windows\System32\drivers\etc\hosts`)
  ```
  127.0.0.1 docker.dev.schoolbox.cloud
  ```
* create `docker.env`, and override any configuration in `docker.default.env`
  if needed:
  * the default settings should work for most development requirements, but
    `cdn_bucket_prefix` will need to be set if testing S3 static file hosting
* start the development environment's containers:
  ```shell
  sudo docker/up
  ```
* install Schoolbox within the development environment:
  ```shell
  sudo docker/provision
  ```
* visit https://docker.dev.schoolbox.cloud/

See [docker/README.md](docker/README.md) for more information about the Docker
development environment, including a migration guide from Vagrant.

### Set up Vagrant development environment (optional)

The Vagrant development environment more closely matches the current
cloud-hosted and on-premise environment, which is deployed as an APT package to
servers which are running an Ubuntu LTS release.

Ideally, developers will only need to consider the Docker environment: however,
the Vagrant environment is still supported.

* Install [Virtualbox](https://www.virtualbox.org/) (sudo apt install virtualbox)
* Install [vagrant](https://vagrantup.com/downloads)
* Fork the [Puppet repo](https://github.com/alaress/puppet-alaress) to your Github
  ```shell
  git clone git@github.com:%YourGitHubUsername%/puppet-alaress.git puppet-alaress (for example git clone git@github.com:low613/puppet-alaress.git puppet-alaress)
  cd puppet-alaress
  git remote add upstream git@github.com:alaress/puppet-alaress.git
  git fetch upstream
  git checkout origin/stable
  git checkout -b stable
  ```
* create `vagrant.yaml` for overrides, see `vagrant.default.yaml` for defaults
  ```yaml
  processors: 3
  memory: 4096
  data_filename: "demo.tar.gz"
  external_db: "schoolbox"
  puppet_sync_type: "gitsync"
  ```
* Create a `/etc/vbox/networks.conf` on your local computer
  ```shell
  sudo mkdir -p /etc/vbox/
  echo "* 192.168.36.0/24" |sudo tee -a /etc/vbox/networks.conf
  ```
* If a Docker development environment has been set up, it should be cleaned up
  before starting Vagrant as otherwise Vagrant cannot write files owned by
  Docker:
  ```shell
  sudo docker/clean
  ```
* In your schoolbox directory import your vagrant box and bring it up for the first time
  ```shell
  vagrant box add ubuntu-2204-php82 https://s3-ap-southeast-2.amazonaws.com/schoolbox-data/ubuntu-2204-php82.box;
  vagrant up;
  vagrant provision;
  ```
* visit https://vagrant.dev.schoolbox.cloud/

## Live environment

When a release is tagged, APT packages are built by [ConcourseCI](https://ci.schoolbox.com.au/). These are then uploaded to the Schoolbox repo for distribution to servers.  The [Puppet repo](https://github.com/alaress/puppet-alaress) is then updated to trigger staging, and then live to update to the latest package.

For full information on the build process of making a APT package and deploying [please refer to this guide](https://schoolbox.atlassian.net/wiki/spaces/SUP/pages/350257186/Building+Schoolbox+Releases+and+Packages).

## Table of Contents
1. Only top-level docs should be listed here, sub-docs should be listed in their parent README.md.
1. Sub-docs should link back to parent README.md

* Architecture:
  * [Quickstart](./docs/quickstart.md)
  * [MVC](./docs/mvc/readme.md)
* Technical Processes:
  * [Build Process](./docs/front-end/build-process.md)
  * [Database Guide](./docs/database.md)
  * [Data Sets](./docs/datasets.md)
  * [Standards](./docs/charter/standards.md)
  * [Code Style](./docs/charter/code-style.md)
  * [Sanitisation](./docs/scrub.md)
  * [Updates](./updates/README.md)
  * [Packaging](./tools/pkg-build/README.md)
* Team Processes:
  * [Responsibilities](./docs/charter/responsibilities.md)
  * [Ceremonies](./docs/charter/ceremonies.md)
  * [Processes](./docs/charter/processes.md)
  * [Hats](./docs/charter/hats.md)
  * [Spiking](./docs/charter/spike.md)
  * [Definition of Ready](./docs/charter/definition-of-ready.md)
  * [Definition of Done](./docs/charter/definition-of-done.md)
  * [Issue Types](./docs/charter/issue-types.md)
  * [Logging Time](./docs/charter/logging-time.md)
  * [BAU](./docs/charter/bau.md)
  * [QA](./docs/charter/qa.md)
  * [Glossary](./docs/charter/glossary.md)
* Core Modules:
  * [Bell Daemon](./tools/notif/bell/README.md)
  * [Curriculum](./library/Schoolbox/Curriculum/README.md)
  * [EOTC](./www/modules/eotc/README.md)
  * [Notifications](./docs/notifications/README.md)
  * [ResourceBooking](./library/Schoolbox/ResourceBooking/README.md)
  * [Task Daemon](./library/Schoolbox/TaskDaemon/README.md)
* Custom Modules
  * [Meriden: Pastoral](library/Custom/Ms/Pastoral/README.md)
* Javascript
  * [Vue](./docs/vue/00-intro.md)
  * [Selectize](./www/static/css/core/selectize/README.md)
* Custom Templates:
  * [For Aura modules](./docs/custom/custom-templates-for-aura-modules.md)
  * [Outside Aura modules](./docs/custom/custom-templates-outside-aura-modules.md)
* Testing:
  * [Acceptance Tests](./test/playwright/README.md)
  * [Smoke Tests](./test/codeception/Smoketests/README.md)
  * [Behaviour Tests](./test/behavior/README.md)
  * [Unit Tests](./docs/unit-test.md)
  * [PHP Stan](./docs/php-stan.md)
