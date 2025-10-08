#!/bin/bash
#
# Compiles & Enables IBM DB2 Module For PHP 7.1/Ubuntu 14.04
#
# Standard Disclaimer: If it breaks your world, don't come crying!
#
# @author      Damian Sloane <damian.sloane@alaress.com.au>
# @copyright   Copyright 2019, Schoolbox Pty Ltd
# @link        https://schoolbox.com.au/

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"

echo
echo "-------------------------------------------------------------------------------------------"
echo "IBM DB2 PHP Module Installer"
echo "-------------------------------------------------------------------------------------------"
echo

{
echo "-------------------------------------------------------------------------------------------"
echo "Installation Started"
echo "-------------------------------------------------------------------------------------------"
echo
} &>> ~/ibm_db2_install.log

echo "Downloading IBM DB2 source and CLI drivers..."

{
cd /opt

sudo wget https://s3-ap-southeast-2.amazonaws.com/damian-s3-test/db2/ibm_db2-1.9.9.tgz -O /opt/ibm_db2-1.9.9.tgz
sudo wget https://s3-ap-southeast-2.amazonaws.com/damian-s3-test/db2/ibm_data_server_driver_for_odbc_cli_linuxx64_v11.1.tar.gz -O /opt/ibm_data_server_driver_for_odbc_cli_linuxx64_v11.1.tar.gz
} &>> ~/ibm_db2_install.log

echo "Extracting downloaded files to /opt folder..."

{
sudo tar -xvzf /opt/ibm_db2-1.9.9.tgz -C /opt
sudo tar -xvzf /opt/ibm_data_server_driver_for_odbc_cli_linuxx64_v11.1.tar.gz -C /opt
sudo mv /opt/clidriver /opt/sqllib
} &>> ~/ibm_db2_install.log

echo "Deleting temporary files..."

{
sudo  rm /opt/ibm_data_server_driver_for_odbc_cli_linuxx64_v11.1.tar.gz
sudo  rm /opt/ibm_db2-1.9.9.tgz
} &>> ~/ibm_db2_install.log

echo "Exporting environment variables..."

{
export IBM_DB_HOME=/opt/sqllib
export IBM_DB_DIR=/opt/sqllib
export IBM_DB_LIB=/opt/sqllib/lib
} &>> ~/ibm_db2_install.log


echo "Compiling driver from source..."

{
cd /opt/ibm_db2-1.9.9
sudo phpize --clean
sudo phpize
sudo -E ./configure -with-IBM_DB2=/opt/sqllib
sudo make clean
sudo make
sudo make install
} &>> ~/ibm_db2_install.log


echo "Adding Extension To PHP 7.1 Configuration Files..."

# echo | sudo... Gotta make a move to a town that's right for me...

{
echo 'extension=ibm_db2.so' | sudo tee /etc/php/7.1/cli/conf.d/20-ibm_db2.ini
} &>> ~/ibm_db2_install.log

{
echo
php -i | grep 'IBM DB2'
echo
echo "-------------------------------------------------------------------------------------------"
echo "Installation Completed"
echo "-------------------------------------------------------------------------------------------"
echo
} &>> ~/ibm_db2_install.log

echo
echo "-------------------------------------------------------------------------------------------"
echo "Installation Completed"
echo "-------------------------------------------------------------------------------------------"
echo
php -i | grep 'IBM DB2'
echo "Log file available at: ~/ibm_db2_install.log"
echo

eval "cd ${USERPATH}"

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"