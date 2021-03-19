#!/bin/bash
#Simple script to fire off php stan in our root directory linking to our config file. This script will be used by CI
export INSTANCE_ID=vagrantx
export XDEBUG_MODE=off
./vendor/bin/phpstan analyze
