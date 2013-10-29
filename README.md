# Schoolbox

This README outlines the relevant information needed for developers, operations and support to familiarise with this system.

This file is internal use and is not shipped to clients.

## Directory and Structure

* app - mobile site application
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

TBC

### By Package

Installing from the package will place the following directories in new locations.
This is to follow the standard packaging process linux distributions use for web applications.

/etc/schoolbox
* contents from www/conf/* copied to /etc/schoolbox/examples
* www/conf symlinked to /etc/schoolbox
* client side conf.php to be located in /etc/schoolbox

/usr/share/schoolbox
* app
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

### By Script

TBC

## Packaging

TBC

## Development Process

TBCz