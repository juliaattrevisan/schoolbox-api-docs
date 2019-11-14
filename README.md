# Schoolbox

This README outlines the relevant information needed for developers, operations and support to familiarise with this system.

This file is internal use and is not shipped to clients.

## Directory and Structure

* dav - WebDav site for exporting the Schoolbox resources
* debian - APT package building definitions (not deployed to clients)
* include
* library - external (from Schoolbox core) and 3rd party libraries
* sql - initial sql schema configuration for new installations
* templates - default and per client skins
* test - PHP and other tests (not deployed to clients)
* tools - Build, update and maintenance tools. Also includes cli scripts for sync.
* updates - per release update files, primarily sql schema changes 
* www
* data - client data and caches (client side only)
* vendor - directory for external libraries loaded via Composer

## Installation

Deployed using packages with Puppet

Installing from the package will place the following directories in new locations.
This is to follow the standard packaging process linux distributions use for web applications.

/etc/schoolbox
* contents from www/conf/* copied to /etc/schoolbox/examples
* www/conf symlinked to /etc/schoolbox
* client side conf.php to be located in /etc/schoolbox

/usr/share/schoolbox
* dav
* include
* library
* sql
* templates
* tools
* updates
* www
* data symlinked to /var/lib/schoolbox

/var/lib/schoolbox
* replaces previous data folder
* directory structure auto created on install/update by tools/create-data-dirs.sh

## Table of Contents
1. Only top-level docs should be listed here, sub-docs should be listed in their parent README.md.
1. Sub-docs should link back to parent README.md

* Architecture:
  * [Quickstart](./docs/quickstart.md)
  * [MVC](./docs/mvc/readme.md)
* Technical Processes:
  * [Build Process](./docs/front-end/build-process.md)
  * [Database Guide](./docs/database.md)
  * [Standards](./docs/charter/standards.md)
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
  * [Glossary](./docs/charter/glossary.md)
* Core Modules:
  * [Bell Daemon](./tools/notif/bell/README.md)
  * [Curriculum](./library/Schoolbox/Curriculum/README.md)
  * [EOTC](./www/modules/eotc/README.md)
  * [NESA](./docs/nesa.md)
  * [Notifications](./docs/notifications/README.md)
  * [ResourceBooking](./library/Schoolbox/ResourceBooking/README.md)
* Custom Modules
  * [Loyola: Survey](./library/Custom/Loyola/Survey/README.md)
  * [Meriden: Pastoral](./library/Custom/Meriden/Pastoral/README.md)
* Javascript
  * [Selectize](./www/css/core/selectize/README.md)
* Custom Templates:
  * [For Aura modules](./docs/custom/custom-templates-for-aura-modules.md)
  * [Outside Aura modules](./docs/custom/custom-templates-outside-aura-modules.md)
* Testing:
  * [Acceptance Tests](./test/codeception/acceptance/README.md)
  * [Smoke Tests](./test/codeception/smoketests/README.md)
  * [Behaviour Tests](./test/behavior/README.md)
  * [Unit Tests](./docs/unit-test.md)
