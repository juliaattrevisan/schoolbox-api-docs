#!/bin/bash
# Checks an on-prem server is suitable for an on-prem install
# When called with no params, w
#
# Usage:
#   ./check.sh [-v] [--verbose] [system] [dns] [http] [ldap] [schoolbox]
#
# @copyright   Copyright 2020, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/
# @todo This script's purpose is unclear. One use case is checking on on-prem server is suitable for an install
# @todo But it also has code that actually checks schoolbox after an install. Consider splitting into distinct scripts.

# Global constants
readonly COLOUR_RED='\e[91m'
readonly COLOUR_GREEN='\e[92m'
readonly COLOUR_END='\e[0m'

readonly ARG_SYSTEM='system'
readonly ARG_DNS='dns'
readonly ARG_HTTP='http'
readonly ARG_LDAP='ldap'
readonly ARG_SB='schoolbox'

# Global vars. Initialised to default values.
verbose='0';
check_specific='0';            # Whether script was invoked with specific checks indicated
check_system='0';
check_dns='0';
check_http='0';
check_ldap='0';
check_sb='0';

ipv6_check_pretty='true'


# Check scrips is running as root.
function check_run_as_root(){
  if [[ $EUID -ne 0 ]]; then
     echo -e "${RED}This script must be run as root${END}"
     exit 1
  fi
}


# Configure shell environment
function setup_shell(){
  export PATH='/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games';
}


# Parse CLI args
function parse_args() {
  local errors='0'
  while [[ $# -gt 0 ]]; do

  case $1 in
      -v|--verbose)
      verbose="1"
      shift # past argument
      ;;
      "$ARG_SYSTEM")
      check_system="1"
      check_specific='1'
      shift # past argument
      ;;
      "$ARG_DNS")
      check_dns="1"
      check_specific='1'
      shift # past argument
      ;;
      "$ARG_HTTP")
      check_http="1"
      check_specific='1'
      shift # past argument
      ;;
      "$ARG_LDAP")
      check_ldap="1"
      check_specific='1'
      shift # past argument
      ;;
      "$ARG_SB")
      check_sb="1"
      check_specific='1'
      shift # past argument
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


# Render an error message in red
function error() {
  if [[ $# -ne 2 ]]; then
    echo -e "${COLOUR_RED}Invalid param count. Expected 2. Found $#.${COLOUR_END}"
    exit 1;
  fi
  echo -e "${COLOUR_RED}$1 $2${COLOUR_END}";
}


# General network check
function check_network(){
  if [ "$(cat /proc/sys/net/ipv6/conf/all/disable_ipv6)" -eq 1 ]; then
      ipv6_check_pretty='false';
  fi
}


# System Check
function check_system(){
  prefix='[SYSTEM]';
  local prefix;
  local os;
  local arch;
  local cpus;
  local mem;
  local disks;
  local disk_space;
  local disk_total_space;

  os=$(cat /etc/lsb-release | grep CODENAME | cut -d'=' -f2);
  echo "$prefix OS: $os";
  if [ "$os" != "bionic" ]; then
    error "$prefix" "Error: Schoolbox requires Ubuntu 18.04 LTS (bionic) - current: $os";
  fi
  arch=$(uname -i);
  echo "$prefix ARCH: $arch";
  if [ "$arch" != "x86_64" ]; then
    error "$prefix" "Error: Schoolbox requires 64bit linux - current: $arch";
  fi
  cpus=$(cat /proc/cpuinfo | grep processor | wc -l);
  echo "$prefix CPU Cores: $cpus";
  if [ "$cpus" -lt 4 ]; then
    error "$prefix" "Error: Schoolbox requires a minimum of 4 cpu cores - current: $cpus";
  fi
  mem=$(free -m | grep Mem | awk '{print $2}');
  echo "$prefix Memory: $mem MB";
  if [ "$mem" -lt 7480 ]; then
    error "$prefix" "Error: Schoolbox requires a minimum of 8 GB of ram - current: $mem MB";
  fi
  disks=$(lsblk | grep disk | wc -l);
  echo "$prefix HDDs: $disks";
  disk_space=$(lsblk -b | grep disk | awk '{print $4}' | cut -d'G' -f1);
  disk_total_space=0;
  for disk in $disk_space; do
    disk_total_space=$(expr $disk_total_space + $disk);
  done
  disk_total_space=$(expr "$disk_total_space" / 1024 / 1024 / 1024);
  echo "$prefix HDD Total Space: $disk_total_space GB";
  if [ "$disk_total_space" -lt 190 ]; then
    error "$prefix" "Error: Schoolbox requires a minimum of 200GB of HDD space across system and data disks - current: $disk_total_space GB";
  fi
  echo "$prefix IPv6 enabled? (optional): $ipv6_check_pretty";
}


# DNS Check
function check_dns(){
  prefix='[DNS]';
  local ns_list;
  local resolve_list;
  local start_time;
  local check;
  local check_check;
  local end_time;
  local time;
  local time_ms;
  local check_clean;

  ns_list=$(grep nameserver /etc/resolv.conf | grep -v '#' | awk '{print $2}');
  ns_list=$(echo "$ns_list" | awk '{$1=$1};1');

  if [ "$ns_list" == "" ]; then
    error "$prefix" 'Error: no nameservers found in /etc/resolv.conf';
  else
    resolve_list='google.com repo.schoolbox.com.au';
    for dns_server in $ns_list; do
      for resolve_check in $resolve_list; do
        start_time=$(date +%s%N);
        check=$(host "$resolve_check" "$dns_server" -W 10);
        check_check=$(echo $?);
        end_time=$(date +%s%N);
        time=$((end_time - start_time));
        time_ms=$((time / 1000000));
        if [ "$check_check" -ne 0 ]; then
          error "$prefix" "$dns_server: ERROR: resolve check for '$resolve_check' failed : $time_ms ms";
        if [ "$verbose" == "1" ]; then
          error "$prefix" "$dns_server: $resolve_check";
          error "$prefix" "$check";
        fi
        else
          check_clean=$(echo "$check" | grep 'has address' | awk '{print $4}');
          echo "$prefix $dns_server: OK: $resolve_check - $check_clean : $time_ms ms";
        fi
      done
    done
  fi
}


# HTTP Check
function check_http(){
  prefix='[HTTP]';
  local timeout;
  local http_list;
  local http_list_ignore_ssl_check;
  local start_time;
  local check;
  local check_check;
  local end_time;
  local time;
  local time_ms;

  timeout='15'; # in seconds
  which curl >/dev/null 2>&1;
  if [ $? -ne 0 ]; then
    error "$prefix" 'Error: Unable to locate curl';
  else
    http_list='http://google.com.au https://google.com.au http://repo.schoolbox.com.au/testfile.txt https://repo.schoolbox.com.au/testfile.txt https://api.notablepdf.com/';
    http_list_ignore_ssl_check='https://puppetstage.alaress.com.au:8140 https://puppetstage.alaress.com.au:443 https://puppet.alaress.com.au:8140 https://puppet.alaress.com.au:443';
    if [ "$ipv6_check_pretty" == "true" ]; then
      http_list="$http_list http://[2404:6800:4006:806::2003]/";
    fi
    for http_check in $http_list; do
      start_time=$(date +%s%N);
      check=$(curl -vvv -I --connect-timeout $timeout "$http_check" 2>&1);
      check_check=$(echo $?);
      end_time=$(date +%s%N);
      time=$((end_time - start_time));
      time_ms=$((time / 1000000));
      if [ "$check_check" -ne 0 ]; then
        error "$prefix" "ERROR: http check for '$http_check' failed : $time_ms ms";
        if [ "$verbose" == "1" ]; then
          error "$prefix" "$http_check";
          error "$prefix" "$check";
        fi
      else
        echo "$prefix OK: $http_check: $time_ms ms";
      fi
    done
    for http_check in $http_list_ignore_ssl_check; do
      start_time=$(date +%s%N);
      check=$(curl -vvv -Ik --connect-timeout $timeout "$http_check" 2>&1);
      check_check=$(echo $?);
      end_time=$(date +%s%N);
      time=$((end_time - start_time));
      time_ms=$((time / 1000000));
      if [ "$check_check" -ne 0 ]; then
        error "$prefix" "ERROR: http check for '$http_check' failed : $time_ms ms";
        if [ "$verbose" == "1" ]; then
          error "$prefix" "$http_check";
          error "$prefix" "$check";
        fi
      else
        echo "$prefix OK: $http_check: $time_ms ms";
      fi
    done
  fi
}


# SCHOOLBOX Check
function check_sb(){
  prefix='[SCHOOLBOX]';
  local temp_conf_file;
  local timeout;
  local timeout;
  local start_time;
  local check;
  local check_check;
  local end_time;
  local time;
  local time_ms;
  local tls_list;


  if [ -f /etc/schoolbox/conf.php ]; then
    temp_conf_file='/etc/schoolbox/conf.php';
    timeout='5'; # seconds
    http_list='https://localhost/saml/browsertest.php http://localhost/saml/browsertest.php'
    for http_check in $http_list; do
      start_time=$(date +%s%N);
      check=$(curl -vvv -Ik --connect-timeout "$timeout" "$http_check" 2>&1);
      check_check=$(echo $?);
      end_time=$(date +%s%N);
      time=$(("$end_time" - "$start_time"));
      time_ms=$((time / 1000000));
      if [ "$check_check" -ne 0 ]; then
        error "$prefix" "ERROR: http check for '$http_check' failed : $time_ms ms";
        if [ "$verbose" == "1" ]; then
          error "$prefix" "$http_check";
          error "$prefix" "$check";
        fi
      else
        echo "$prefix [WEB] OK: $http_check: $time_ms ms";
      fi
    done
  fi
  which openssl >/dev/null 2>&1;
  if [ $? -ne 0 ]; then
    error "$prefix" 'Error: Unable to locate openssl';
  else
    prefix="[TLS]";
    tls_list='gateway.push.apple.com:2195 gateway.sandbox.push.apple.com:2195';
    for tls_check in $tls_list; do
      start_time=$(date +%s%N);
      check=$(timeout "$timeout"s openssl s_client -connect "$tls_check" 2>&1);
      check_check=$(echo $?);
      end_time=$(date +%s%N);
      time=$(("$end_time" - "$start_time"));
      time_ms=$((time / 1000000));
      if [ "$check_check" -ne 1 ]; then
        error "$prefix" "ERROR: tls check for '$tls_check' failed : $time_ms ms";
        if [ "$verbose" == "1" ]; then
          error "$prefix" "$tls_check";
          error "$prefix" "$check";
        fi
      else
        echo "$prefix OK: $tls_check: $time_ms ms";
      fi
    done
  fi
}


# LDAP Check
function check_ldap(){
  prefix='[SCHOOLBOX]';
  local temp_conf_file;
  local timeout;
  local sql_user;
  local sql_passwd;
  local sql_host;
  local sql_schema;
  local ldap_pass;
  local ldap_enable;
  local ldap_pri_host;
  local ldap_sec_host;
  local ldap_ter_host;
  local ldap_pri_user;

  if [ -f /etc/schoolbox/conf.php ]; then
    check=$(which ldapsearch >/dev/null 2>&1);
    if [ $? -eq 0 ]; then
      temp_conf_file='/etc/schoolbox/conf.php';
      timeout='5'; # seconds
      sql_user=$(grep "\"SQL_user" $temp_conf_file | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4);
      sql_passwd=$(grep "\"SQL_passwd" $temp_conf_file | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4);
      sql_host=$(grep "\"SQL_host" $temp_conf_file | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4);
      sql_schema=$(grep "\"SQL_db" $temp_conf_file | grep -v "#" | grep -v "\/\/" | cut -d'"' -f4);
      config=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_enable'" 2>/dev/null);
      if [ $? -eq 0 ]; then
        ldap_pass=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_pass'" 2>/dev/null);
        ldap_enable=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_enable'" 2>/dev/null);
        ldap_pri_host=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_host'" 2>/dev/null);
        ldap_sec_host=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_host_alt'" 2>/dev/null);
        ldap_ter_host=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_host_ter'" 2>/dev/null);
        if [ "$(echo "$ldap_pri_host" | egrep 'ldap(s|)://' | wc -l)" -eq 0 ]; then
          ldap_pri_host="ldap://$ldap_pri_host";
        fi
        if [ "$(echo "$ldap_sec_host" | egrep 'ldap(s|)://' | wc -l)" -eq 0 ]; then
          ldap_sec_host="ldap://$ldap_sec_host";
        fi
        if [ "$ldap_enable" == "0" ]; then
          echo "$prefix [LDAP] Ok - disabled";
        else
          if [ "$(echo "$ldap_pri_host" | wc -c)" -gt 10 ]; then
            ldap_pri_user=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_user'" 2>/dev/null);
            ldappribase=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_base'" 2>/dev/null);
            ldappricheck=$(echo -n "$ldap_pass" | ldapsearch -H "$ldap_pri_host" -D "$ldap_pri_user" -y /dev/fd/0 -b "$ldappribase" "(userObject=*)" dn 2>&1);
            if [ $? -eq 0 ]; then
              echo "$prefix" "[LDAP] [PRIMARY] OK - $ldap_pri_host";
            else
              error "$prefix" "[LDAP] [PRIMARY] Error with $ldap_pri_host:\n$ldappricheck";
            fi
          fi
          if [ "$(echo "$ldap_sec_host" | wc -c)" -gt 10 ]; then
            ldapsecuser=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_user_alt'" 2>/dev/null);
            ldapsecbase=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_base_alt'" 2>/dev/null);
            ldapseccheck=$(echo -n "$ldap_pass" | ldapsearch -H $ldap_sec_host -D "$ldapsecuser" -y /dev/fd/0 -b "$ldapsecbase" "(userObject=*)" dn 2>&1);
            if [ $? -eq 0 ]; then
              echo "$prefix [LDAP] [SECONDARY] OK - $ldap_sec_host";
            else
              error "$prefix" "[LDAP] [SECONDARY] Error with $ldap_sec_host:\n$ldapseccheck";
            fi
          fi
          if [ "$(echo "$ldap_ter_host" | wc -c)" -gt 10 ]; then
            ldapteruser=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_user_ter'" 2>/dev/null);
            ldapterbase=$(mysql -u "$sql_user" -p"$sql_passwd" -h "$sql_host" "$sql_schema" -BNe "SELECT DISTINCT value FROM config WHERE name='ldap_base_ter'" 2>/dev/null);
            ldaptercheck=$(echo -n "$ldap_pass" | ldapsearch -H "$ldap_ter_host" -D "$ldapteruser" -y /dev/fd/0 -b "$ldapterbase" "(userObject=*)" dn 2>&1);
            if [ $? -eq 0 ]; then
              echo "$prefix [LDAP] [TERTIARY] OK - $ldap_ter_host";
            else
              error "$prefix" "[LDAP] [TERTIARY] Error with $ldap_ter_host:\n$ldaptercheck";
            fi
          fi
        fi
      fi
    else
      error "$prefix" "[LDAP] check missing ldapsearch tool";
    fi
  fi
}


# Schoolbox Health Check
function check_sb_health(){
  prefix='[SCHOOLBOX]';
  if [ -f /etc/schoolbox/conf.php ]; then
    echo "$prefix Services Check";
    php /usr/share/schoolbox/tools/notif/syscheck.php -vvv 2>&1;
    echo "$prefix ExternalDB Health Check";
    php /usr/share/schoolbox/tools/healthcheck.php 2>/dev/null;
  fi
}


function main(){
  check_run_as_root
  setup_shell
  parse_args "$@"

  check_network
  if [ "$check_system" == "1" ] || [ "$check_specific" == "0" ]; then
    check_system
  fi
  if [ "$check_dns" == "1" ] || [ "$check_specific" == "0" ]; then
    check_dns
  fi
  if [ "$check_http" == "1" ] || [ "$check_specific" == "0" ]; then
    check_http
  fi
  if [ "$check_sb" == "1" ] || [ "$check_specific" == "0" ]; then
    check_sb
  fi
  if [ "$check_sb" == "1" ] || [ "$check_ldap" == "1" ] || [ "$check_specific" == "0" ]; then
    check_ldap
  fi
  if [ "$check_sb" == "1" ] || [ "$check_specific" == "0" ]; then
    check_sb_health
  fi
}

main "$@"


