#!/bin/bash
#
# Enables Microsoft PDO_SQLSRV Support For PHP 7.1/Ubuntu 14.04
#
# This won't be needed once we get 16.04 and native support is available, this downloads a pre-compiled
# version of the PDO_SQLSRV driver and its dependencies for use on Ubuntu 14.04.
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
echo "Microsoft SQL Server ODBC Driver 13 (PHP 7.1 / PDO_SQLSRV) Installer"
echo "-------------------------------------------------------------------------------------------"
echo

{
    echo "-------------------------------------------------------------------------------------------"
    echo "Installation Started"
    echo "-------------------------------------------------------------------------------------------"
    echo
} &>> ~/php_7.1_pdosqlsrv_install.log

eval "cd ${USERPATH}"

echo "Installing driver pre-requisites..."

{
    sudo apt-get -y install libssl1.0.0
    sudo apt-get -y install libgss3
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Downloading driver files..."

{
    cd ~
    wget https://s3-ap-southeast-2.amazonaws.com/damian-s3-test/db2/pdosqlsrv.tar.gz
    tar -xzf pdosqlsrv.tar.gz
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Copying driver files..."

{
    sudo mkdir -p /usr/lib64
    sudo mkdir -p /opt/microsoft/msodbcsql
    sudo bash -c 'echo -e "/usr/lib64" >> /etc/ld.so.conf'
    sudo cp ~/pdosqlsrv/usr-bin/* /usr/bin
    sudo cp ~/pdosqlsrv/etc-php-7.1-cli-conf.d/* /etc/php/7.1/cli/conf.d
    sudo cp ~/pdosqlsrv/usr-lib64/*.a /usr/lib64
    sudo cp ~/pdosqlsrv/usr-lib64/*.so /usr/lib64
    sudo cp ~/pdosqlsrv/usr-lib64/*.la /usr/lib64
    sudo ln -s /usr/lib64/libodbc.so /usr/lib64/libodbc.so.2
    sudo ln -s /usr/lib64/libodbc.so /usr/lib64/libodbc.so.2.0.0vagrant
    sudo ln -s /usr/lib64/libodbccr.so /usr/lib64/libodbccr.so.2
    sudo ln -s /usr/lib64/libodbccr.so /usr/lib64/libodbccr.so.2.0.0
    sudo ln -s /usr/lib64/libodbcinst.so /usr/lib64/libodbcinst.so.2
    sudo ln -s /usr/lib64/libodbcinst.so /usr/lib64/libodbcinst.so.2.0.0
    sudo cp ~/pdosqlsrv/usr-lib-php-20160303/* /usr/lib/php/20160303
    sudo cp ~/pdosqlsrv/usr-lib-x86_64-linux-gnu/libstdc++.so.6.0.22 /usr/lib/x86_64-linux-gnu
    sudo cp -a ~/pdosqlsrv/opt-microsoft-msodbcsql/* /opt/microsoft/msodbcsql
    sudo ln -s /opt/microsoft/msodbcsql/bin/bcp-13.0.0.0 /bin/bcp
    sudo ln -s /opt/microsoft/msodbcsql/bin/sqlcmd-13.0.0.0 /bin/sqlcmd
    sudo cp ~/pdosqlsrv/usr-lib-php-20160303/* /usr/lib/php/20160303
    sudo cp ~/pdosqlsrv/etc/* /etc
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Updating shared library cache..."

{
    sudo ldconfig
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Changing Schoolbox External Connector To Synergetic V68..."

{
    mariadb schoolbox -e "UPDATE config SET value='synergetic.schoolbox.com.au' WHERE name='external_db_host'"
    mariadb schoolbox -e "UPDATE config SET value='Synergetic_AUVIC_ALARESS_PRD' WHERE name='external_db_db'"
    mariadb schoolbox -e "UPDATE config SET value='http://synergetic.schoolbox.com.au:7008/' WHERE name='external_portal'"
    mariadb schoolbox -e "UPDATE config SET value='synergetic' WHERE name='external_type'"
    mariadb schoolbox -e "UPDATE config SET value='sa' WHERE name='external_db_user'"
    mariadb schoolbox -e "UPDATE config SET value='Synergetic#1' WHERE name='external_db_passwd'"
    mariadb schoolbox -e "UPDATE config SET value='0' WHERE name='external_cache_timeout'"
    mariadb schoolbox -e "UPDATE config SET value='1' WHERE name='external_pastoral'"
    mariadb schoolbox -e "UPDATE config SET value='1' WHERE name='external_pastoral_upload'"
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Removing downloaded files..."

{
    sudo rm ~/pdosqlsrv.tar.gz
    sudo rm -rf ~/pdosqlsrv
} &>> ~/php_7.1_pdosqlsrv_install.log

# Lazy hack to upgrade to later v4.1.8

echo "Installing GCC..."

{
    sudo add-apt-repository ppa:ubuntu-toolchain-r/test -y
    sudo apt-get update
    sudo -i export CPPFLAGS="-DSIZEOF_LONG_INT=8"
    sudo apt-get -y install gcc-5 g++-5
    sudo update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-5 60 --slave /usr/bin/g++ g++ /usr/bin/g++-5
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Building UnixODBC..."

{
    cd ~
    wget ftp://ftp.unixodbc.org/pub/unixODBC/unixODBC-2.3.1.tar.gz
    tar -xzf unixODBC-2.3.1.tar.gz
    cd unixODBC-2.3.1/
    ./configure --enable-static --prefix=/usr --libdir=/usr/lib64 --sysconfdir=/etc --disable-gui --disable-drivers --enable-iconv --with-iconv-char-enc=UTF8 --with-iconv-ucode-enc=UTF16LE
    sudo make
    sudo make install
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Building MSSQLPHP v4.1.8..."

{
    cd ~
    wget https://github.com/Microsoft/msphpsql/archive/v4.1.8-preview.tar.gz
    tar -xzf ./v4.1.8-preview.tar.gz
    cd msphpsql-4.1.8-preview/source
    chmod +x packagize.sh
    ./packagize.sh
    cd pdo_sqlsrv
    phpize
    ./configure --enable-static CXXFLAGS=-std=c++11
    make
    sudo make install
} &>> ~/php_7.1_pdosqlsrv_install.log

echo
echo "-------------------------------------------------------------------------------------------"
echo "Installation Completed"
echo "-------------------------------------------------------------------------------------------"
echo
php -i | grep 'PDO drivers'
echo "Log file available at: ~/php_7.1_pdosqlsrv_install.log"
echo

# Deprecation notice
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/***************     Deprecated NOTICE - this will be deleted in future    ****************/\033[0m"
echo -e "\033[1;33m/*    If you are running this, please add a comment of this scripts name into SA-19334    */\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
echo -e "\033[1;33m/******************************************************************************************/\033[0m"
