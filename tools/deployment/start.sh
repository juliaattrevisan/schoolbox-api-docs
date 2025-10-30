#!/usr/bin/env bash
# Script to start the deployment of Schoolbox containers in ecs.
# Uses a configuration file to get deployment parameters.
# usage: start.sh [-h|--help] [-c|--config CONFIG_FILE] [-v|--verbose]

set -e
readonly LOG=/usr/bin/alaress-logger
readonly L_DEBUG=100
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
CONFIG_FILE=/etc/schoolbox/deployment_config.json
verbose=0

function debug() {
  if [[ $verbose == '1' ]]; then
    $LOG $L_DEBUG "$1"
  fi
}
function log() {
  $LOG $L_INFO "$1"
}
function warn() {
  $LOG $L_WARN "$1"
}
function error() {
  $LOG $L_ERROR "$1"
}

function help() {
  echo "Usage: start.sh [options]"
  echo ""
  echo "Options:"
  echo "  -h, --help        Show this help message and exit"
  echo "  -c, --config      Specify a custom configuration file (default: /etc/schoolbox/deploy_config.json)"
  echo "  -v, --verbose     Enable verbose output for debugging"
  echo ""
  echo "This script starts the deployment process for Schoolbox containers in ecs."
}

function parse_args() {
  while [[ "$#" -gt 0 ]]; do
    case $1 in
    -h | --help)
      help
      exit 0
      ;;
    -c | --config)
      CONFIG_FILE="$2"
      shift
      ;;
    -v | --verbose) verbose=1 ;;
    *)
      error "Unknown parameter passed: $1"
      help
      exit 1
      ;;
    esac
    shift
  done
}

function start_deployment() {
  if [[ ! -f "$CONFIG_FILE" ]]; then
    error "Configuration file not found: $CONFIG_FILE"
    exit 1
  fi

  log "Starting deployment with configuration file: $CONFIG_FILE"
  cluster_name=$(jq -r '.cluster_name' "$CONFIG_FILE")
  tag=$(jq -r '.tag' "$CONFIG_FILE")
  api_url=$(jq -r '.api_url' "$CONFIG_FILE")
  auth_token=$(jq -r '.auth_token' "$CONFIG_FILE")
  log "Deploying to cluster: $cluster_name with tag: $tag"
  if response=$(curl --fail-with-body -X POST "$api_url/deploy" \
    -H "X-API-Key: $auth_token" \
    -H "Content-Type: application/json" \
    -d "{\"cluster\": \"$cluster_name\", \"tag\": \"$tag\"}"); then

    message=$(echo "$response" | jq -r '.message')
    deploymentID=$(echo "$response" | jq -r '.serviceRevisionId')
    log "Deployment initiated: $message (ID: $deploymentID)"
    debug "Full response: $response"
    echo "$deploymentID" >/tmp/deployment_id
  else
    error "Failed to start deployment. Response: $response"
    exit 1
  fi
  log "Deployment started successfully."
}

function main() {
  parse_args "$@"
  start_deployment
}
main "$@"
