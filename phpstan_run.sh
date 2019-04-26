#!/bin/bash
#Simple script to make running phpstan really simple.

#RUNLEVEL - Scan Strictness, how picky phpstan will be about the codebase.
RUNLEVEL=0

#FOLDERS_TO_ANALYZE - As it sounds, all the folders to analyze in the run through. Limit it to what needs to be checked.
FOLDERS_TO_ANALYZE="library www tools"

./vendor/phpstan/phpstan/bin/phpstan analyze -l $RUNLEVEL $FOLDERS_TO_ANALYZE