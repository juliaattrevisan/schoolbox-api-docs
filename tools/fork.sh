#!/bin/bash
# This script executes a command in a forked child process.
# It exists primarily so that PHP scripts can execute another script and exit cleanly, this is not trivial
# in PHP due to shared resources.
# For some reason passing and executing a command that contains shell redirection blocks this parent from returning.
# You must explicitly declare where to pipe OUT + ERR streams
# Usage:
#   ./fork.sh --cmd "php test.php" --out /var/log/out.log --err /var/log/err.log [-a] [--append]

# Global const
readonly ARG_COMMAND="cmd";
readonly ARG_OUT_STREAM="out";
readonly ARG_ERR_STREAM="err";

# Global vars
declare command='';
declare out_stream='';
declare err_stream='';
declare append='0';

# Parse CLI args
function parse_args() {
  local errors='0'
  while [[ $# -gt 0 ]]; do

  case $1 in
      -a|--append)
      append="1"
      shift # past argument
      ;;
      --$ARG_COMMAND)
      command="$2"
      shift # past argument
      shift # past value
      ;;
      --$ARG_OUT_STREAM)
      out_stream="$2"
      shift # past argument
      shift # past value
      ;;
      --$ARG_ERR_STREAM)
      err_stream="$2"
      shift # past argument
      shift # past value
      ;;
      *)    # unknown option
      echo -e "${COLOUR_RED}Unrecognised option: '$1'${COLOUR_END}"
      errors='1'
      shift # past argument
      ;;
  esac
  done
  if [[ "$errors" == '1' ]]; then
    echo -e "${COLOUR_RED}Errors detected, exiting${COLOUR_END}"
    exit 1;
  fi
}


function validate_args(){
  local errors='0';
  if [[ $command == '' ]]; then
    echo "You must specify a command with the --$ARG_COMMAND flag" >&2
    errors='1'
  fi
  if [[ $command == '' ]]; then
    echo "You must specify an out stream with the --$ARG_OUT_STREAM flag" >&2
    errors='1'
  fi
  if [[ $command == '' ]]; then
    echo "You must specify an err stream with the --$ARG_ERR_STREAM flag" >&2
    errors='1'
  fi
  if [[ "$errors" == '1' ]]; then
    echo -e "${COLOUR_RED}Errors detected, exiting${COLOUR_END}"
    exit 1;
  fi
}


function execute_command_in_fork(){
  if [[ $append == '0' ]]; then
    bash -c "$command" 1>"$out_stream" 2>"$err_stream" &
  else
    bash -c "$command" 1>>"$out_stream" 2>>"$err_stream" &
  fi
}


main(){
  parse_args "$@"
  validate_args
  execute_command_in_fork
}

main "$@"
