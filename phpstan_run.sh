#!/bin/bash
#Simple script to make running phpstan really simple. This script will be used by CI
#For custom runs of php stan -> ./vendor/phpstan/phpstan/bin/phpstan analyze -l [0-9] -c test/phpstan/phpstan.neon [space seperated list of folders to analyze]

#RUNLEVEL - Scan Strictness, how picky phpstan will be about the codebase.
RUNLEVEL=0

#FOLDERS_TO_ANALYZE - As it sounds, all the folders to analyze in the run through. Limit it to what needs to be checked.
FOLDERS_TO_ANALYZE="library www tools"

#CONFIG_FILE - Points to where the autoload file is.
CONFIG_FILE="test/phpstan/phpstan.neon"

./vendor/phpstan/phpstan/bin/phpstan analyze -l $RUNLEVEL -c $CONFIG_FILE $FOLDERS_TO_ANALYZE
