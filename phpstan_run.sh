#!/bin/bash
#Simple script to fire off php stan in our root directory linking to our config file. This script will be used by CI

#CONFIG_FILE - Points to all the config to run with.
CONFIG_FILE="test/phpstan/phpstan.neon"

./vendor/phpstan/phpstan/bin/phpstan analyze -c $CONFIG_FILE
