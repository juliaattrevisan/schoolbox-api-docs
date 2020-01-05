#!/bin/bash
#Simple script to fire off php stan in our root directory linking to our config file. This script will be used by CI

./vendor/bin/phpstan analyze
