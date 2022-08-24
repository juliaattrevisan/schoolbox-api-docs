#!/bin/bash
# Useful constants and functions for vagrant provision scripts

# Global constants
readonly SITE_ROOT=/usr/share/schoolbox
readonly LOG=/usr/bin/alaress-logger
readonly L_INFO=200;
readonly L_WARN=300;
readonly L_ERROR=400;
readonly cachedir="$SITE_ROOT"/vagrantcache;

function log(){
  $LOG $L_INFO "$1"
}
function warn(){
  $LOG $L_WARN "$1"
}
function error(){
  $LOG $L_ERROR "$1"
}
