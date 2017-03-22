#!/bin/bash
#
# Enables Microsoft PDO_SQLSRV Support For PHP 7.1/Ubuntu 14.04
#
# This won't be needed once we get 16.04 and native support is available, this downloads, compiles
# and installs the PHP 7 / Microsoft SQL Server ODBC linux drivers so they will work with Ubuntu 14.04
# and we can at least test and develop with our Vagrants. 
#
# Standard Disclaimer: If it breaks your world, don't come crying!
#
# @author      Damian Sloane <damian.sloane@alaress.com.au>
# @copyright   2017 Alaress Pty Ltd
# @link        http://www.alaress.com.au

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

echo "Switching Active PHP Version To 7.1..."

{
sudo a2dismod php5
sudo a2enmod php7.1
sudo update-alternatives --set php /usr/bin/php7.1
sudo service apache2 restart 
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Adding Ubuntu Toolchain PPA & Updating APT Repositories..."

{
sudo add-apt-repository ppa:ubuntu-toolchain-r/test -y 
sudo apt-get update
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Installing GCC/G++ Compiler (This will take a few minutes)..."
{
sudo -i export CPPFLAGS="-DSIZEOF_LONG_INT=8"
sudo apt-get -y install gcc-5 g++-5
sudo update-alternatives --install /usr/bin/gcc gcc /usr/bin/gcc-5 60 --slave /usr/bin/g++ g++ /usr/bin/g++-5
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Downloading unixODBC 2.3.1..."

{
cd ~
wget ftp://ftp.unixodbc.org/pub/unixODBC/unixODBC-2.3.1.tar.gz
tar -xzf unixODBC-2.3.1.tar.gz
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Configuring unixODBC 2.3.1..."

{
cd unixODBC-2.3.1/
./configure --prefix=/usr --libdir=/usr/lib64 --sysconfdir=/etc --disable-gui --disable-drivers --enable-iconv --with-iconv-char-enc=UTF8 --with-iconv-ucode-enc=UTF16LE 1> odbc_con.log 2> make_err.log
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Building & Installing unixODBC 2.3.1..."

{
sudo make 1> make_std.log 2> make_err.log
sudo make install 1> makeinstall_std.log 2> makeinstall_err.log
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Downloading Microsoft SQL Server ODBC Driver 13..."

{
cd ~
wget "https://meetsstorenew.blob.core.windows.net/contianerhd/Ubuntu%2013.0%20Tar/msodbcsql-13.0.0.0.tar.gz?st=2016-10-18T17%3A29%3A00Z&se=2022-10-19T17%3A29%3A00Z&sp=rl&sv=2015-04-05&sr=b&sig=cDwPfrouVeIQf0vi%2BnKt%2BzX8Z8caIYvRCmicDL5oknY%3D" -O msodbcsql-13.0.0.0.tar.gz
tar -xzf msodbcsql-13.0.0.0.tar.gz
cd msodbcsql-13.0.0.0/
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Installing Additional Dependencies..."

{
sudo apt-get -y install libssl1.0.0
sudo apt-get -y install libgss3
sudo echo "/usr/lib64" >> /etc/ld.so.conf
sudo ldconfig
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Installing Microsoft SQL Server ODBC Driver 13..."

{
sudo bash ./install.sh install --force --accept-license
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Compiling Driver..."

{
cd ~
wget https://github.com/Microsoft/msphpsql/archive/4.0.6-Linux.tar.gz
tar -xzf 4.0.6-Linux.tar.gz
cd msphpsql-4.0.6-Linux/source/pdo_sqlsrv/
phpize && ./configure CXXFLAGS=-std=c++11 && make
sudo make install
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Adding Extension To PHP 7.1 Configuration Files..."

{
sudo bash -c 'echo -e "; configuration for php common module \n; priority=20 \nextension=pdo_sqlsrv.so" > /etc/php/7.1/mods-available/pdo_sqlsrv.ini'
sudo ln -s /etc/php/7.1/mods-available/pdo_sqlsrv.ini /etc/php/7.1/apache2/conf.d/20-pdo_sqlsrv.ini
sudo ln -s /etc/php/7.1/mods-available/pdo_sqlsrv.ini /etc/php/7.1/cli/conf.d/20-pdo_sqlsrv.ini
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Cleaning Up..."

{
cd ~
sudo rm -rf msphpsql-4.0.6-Linux/
sudo rm -rf unixODBC-2.3.1/
sudo rm -rf msodbcsql-13.0.0.0/
sudo rm 4.0.6-Linux.tar.gz
sudo rm msodbcsql-13.0.0.0.tar.gz
sudo rm unixODBC-2.3.1.tar.gz
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Changing Schoolbox External Connector To Synergetic V68..."

{
mysql schoolbox -e "UPDATE config SET value='synergetic.schoolbox.com.au' WHERE name='external_db_host'"
mysql schoolbox -e "UPDATE config SET value='Synergetic_AUVIC_ALARESS_PRD' WHERE name='external_db_db'"
mysql schoolbox -e "UPDATE config SET value='http://synergetic.schoolbox.com.au:7008/' WHERE name='external_portal'"
mysql schoolbox -e "UPDATE config SET value='synergetic' WHERE name='external_type'"
mysql schoolbox -e "UPDATE config SET value='sa' WHERE name='external_db_user'"
mysql schoolbox -e "UPDATE config SET value='Synergetic#1' WHERE name='external_db_passwd'"
mysql schoolbox -e "UPDATE config SET value='0' WHERE name='external_cache_timeout'"
mysql schoolbox -e "UPDATE config SET value='1' WHERE name='external_pastoral'"
mysql schoolbox -e "UPDATE config SET value='1' WHERE name='external_pastoral_upload'"
} &>> ~/php_7.1_pdosqlsrv_install.log

echo "Restarting Apache Service & Schoolbox Daemons..."

{
sudo service apache2 restart 
sudo service schoolbox-scheduled restart
sudo service schoolbox-rerouted restart
sudo service schoolbox-socketd restart
sudo service schoolbox-digestd restart
} &>> ~/php_7.1_pdosqlsrv_install.log

{
echo
echo php -i | grep 'PDO drivers'
echo
echo "-------------------------------------------------------------------------------------------"
echo "Installation Completed"
echo "-------------------------------------------------------------------------------------------"
echo
} &>> ~/php_7.1_pdosqlsrv_install.log

echo
echo "-------------------------------------------------------------------------------------------"
echo "Installation Completed"
echo "-------------------------------------------------------------------------------------------"
echo
echo "Log file available at: ~/php_7.1_pdosqlsrv_install.log"
echo
php -i | grep 'PDO drivers'
echo
