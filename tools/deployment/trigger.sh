#!/usr/bin/env bash
# Script to trigger the cutover of a deployment of Schoolbox containers in ecs.
# Uses a configuration file to get deployment parameters and a file to read the deployment ID from.
# usage: trigger.sh [-h|--help] [-c|--config CONFIG_FILE] [-i|--id DEPLOYMENT_ID_FILE] [-v|--verbose]

set -e
readonly LOG=/usr/bin/alaress-logger
readonly L_DEBUG=100
readonly L_INFO=200
readonly L_WARN=300
readonly L_ERROR=400
DEPLOYMENT_ID_FILE="/tmp/deployment_id"
CONFIG_FILE="/etc/schoolbox/deployment_config.json"
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
  echo "Usage: trigger.sh [options]"
  echo ""
  echo "Options:"
  echo "  -h, --help        Show this help message and exit"
  echo "  -c, --config      Specify a custom configuration file (default: /etc/schoolbox/deploy_config.json)"
  echo "  -i, --id-file     Specify a file to write the deployment ID to (default: /tmp/deployment_id)"
  echo "  -v, --verbose     Enable verbose output for debugging"
  echo ""
  echo "This script marks the deployment as ready to be cut over in ecs."
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
    -i | --id-file)
      DEPLOYMENT_ID_FILE="$2"
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
function trigger_deployment() {
  if [[ ! -f "$CONFIG_FILE" ]]; then
    error "Configuration file not found: $CONFIG_FILE"
    exit 1
  fi
  if [[ -z "$DEPLOYMENT_ID_FILE" ]]; then
    error "Deployment ID file path is empty."
    exit 1
  fi
  deployment_id=$(cat "$DEPLOYMENT_ID_FILE" 2>/dev/null || echo "")
  if [[ -z "$deployment_id" ]]; then
    error "Deployment ID not found in file: $DEPLOYMENT_ID_FILE"
    exit 1
  fi
  log "Triggering deployment with ID: $deployment_id"
  api_url=$(jq -r '.api_url' "$CONFIG_FILE")
  auth_token=$(jq -r '.auth_token' "$CONFIG_FILE")
  if response=$(curl --fail-with-body -X POST "$api_url/ready" \
    -H "X-API-Key: $auth_token" \
    -H "Content-Type: application/json" \
    -d "{\"serviceRevisionId\": \"$deployment_id\"}"); then
    message=$(echo "$response" | jq -r '.message')
    log "Deployment triggered: $message"
    debug "Full response: $response"
    rm "$DEPLOYMENT_ID_FILE"
  else
    error "Failed to trigger deployment. Response: $response"
    exit 1
  fi
}

function main() {
  parse_args "$@"
  trigger_deployment
}

main "$@"
