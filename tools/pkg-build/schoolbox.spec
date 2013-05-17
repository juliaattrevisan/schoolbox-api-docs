%define rel		rc1
%define beta		1
%define tag		13.0.0RC1
%if %beta
%define release		0.%beta.%rel
%define distname	%version-%beta.tar.gz
%define dirname		%name-%version-%beta
%else
%define release		%rel
%define distname	%version.tar.gz
%define dirname		%name-%version
%define tag		%{version}
%endif

# set exceptions for detected but internally shipped php-pear modules
%define	_requires_exceptions	pear(Archive/Tar.php)\\|pear(/home/ryan/xhprof-0.9.2/xhprof_lib/utils/xhprof_lib.php)\\|pear(/home/ryan/xhprof-0.9.2/xhprof_lib/utils/xhprof_runs.php)\\|pear(Net/LDAP2.php)\\|pear(OS/Guess.php)\\|pear(PEAR/Command/Packaging.php)\\|pear(PEAR/FTP.php)\\|pear(PHPUnit/Framework/TestCase.php)\\|pear(Structures/Graph/Manipulator/TopologicalSorter.php)\\|pear(Structures/Graph/Node.php)\\|pear(Structures/Graph.php)\\|pear(System.php)\\|pear(XML/RPC.php)

Name:		schoolbox
Version:	13.0.0
Release:	%{release}
Summary:	Schoolbox LMS by Alaress Pty. Ltd.
Group:		Applications/Internet
License:	Proprietory
URL:		http://www.schoolbox.com.au/
Source0:	https://github.com/alaress/schoolbox/archive/%{tag}.tar.gz
Epoch:		1
BuildRequires:	php-cli
BuildRequires:	php-xml
BuildRequires:	php-mbstring
Requires:	httpd
Requires:	mod_ssl
Requires:	php
Requires:	php-cli
Requires:	php-gd
Requires:	php-imap
Requires:	php-ldap
Requires:	php-mysql
Requires:	php-pdo
Requires:	php-pear
Requires:	php-soap
Requires:	php-xml
Requires:	php-xmlrpc
Requires:	mysql
Requires:	mysql-server
Requires:	wget
Requires:	cifs-utils

# TODO: Fix missing build dependencies (npm)
BuildArch:	noarch

%description
Schoolbox is at the leading edge of eLearning solutions for the K-12
education market. The Schoolbox system is carefully integrated with school
data-stores and into each unique existing infrastructure to provide an
interface that empowers each school's community by making data easily
accessible. Furthermore Schoolbox streamlines the education process by
increasing staff efficiency, leaving greater time for the core purpose of
schools - learning.

%package	sso
Summary:	Kerberos dependencies for SSO usage
Group:		System/Servers
Requires:	%{name} = 1:%{version}-%{release}
# TODO: Fix missing sso dependencies

%description	sso
Ensures the Kerberos dependencies for SSO are installed and provides an
example apache vhost file

%package	leave
Summary:	Schoolbox Leave Module
Group:		System/Servers
Requires:	%{name} = 1:%{version}-%{release}

%description	leave
Leave module for the Schoolbox LMS

%package        mobile
Summary:        Schoolbox Mobile Module
Group:          System/Servers
Requires:       %{name} = 1:%{version}-%{release}

%description    mobile
Mobile module for the Schoolbox LMS

%package	mgssport
Summary:	MGS Sports module for the Schoolbox LMS
Group:		System/Servers
Requires:	%{name} = 1:%{version}-%{release}

%description	mgssport
Provides old to new template system links unique to MGS to allow the
Sports module to work correctly.

%prep
%setup -q -n %{name}-%{tag}

%build

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}%{_datadir}/%{name}
mkdir -p %{buildroot}%{_sysconfdir}/%{name}/examples
mkdir -p %{buildroot}%{_localstatedir}/lib/%{name}
cp -a www/conf/* %{buildroot}%{_sysconfdir}/%{name}/examples
rm -rf www/conf

# Remove cron and logrotate files not for this package
tools/pkg-remove-other-crons.sh %{name}

mkdir -p %{buildroot}%{_sysconfdir}/cron.d
cp -a tools/cron/* %{buildroot}%{_sysconfdir}/cron.d
rm -rf tools/cron
mkdir -p %{buildroot}%{_sysconfdir}/logrotate.d
cp -a tools/logrotate/* %{buildroot}%{_sysconfdir}/logrotate.d
rm -rf tools/logrotate

# Install submodules using composer
tools/composer.sh

cp -a tools %{buildroot}%{_datadir}/%{name}

# Auto build the sql update scripts
%{buildroot}%{_datadir}/%{name}/tools/build-sql-update.sh

cp -a * %{buildroot}%{_datadir}/%{name}
ln -s %{_sysconfdir}/%{name} %{buildroot}%{_datadir}/%{name}/www/conf
ln -s %{_localstatedir}/lib/%{name} %{buildroot}%{_datadir}/%{name}/data

# Remove non-deployable files and folders
rm -fR %{buildroot}%{_datadir}/%{name}/phpunit.xml
rm -fR %{buildroot}%{_datadir}/%{name}/README.md
rm -fR %{buildroot}%{_datadir}/%{name}/test
rm -fR %{buildroot}%{_datadir}/%{name}/Vagrantfile
rm -fR %{buildroot}%{_datadir}/%{name}/tools/vagrant*
rm -fR %{buildroot}%{_datadir}/%{name}/tools/pkg-*
rm -fR %{buildroot}%{_datadir}/%{name}/composer*
rm -fR %{buildroot}%{_datadir}/%{name}/debian
rm -fR %{buildroot}%{_datadir}/%{name}/tools/major-updater.sh
rm -fR %{buildroot}%{_datadir}/%{name}/tools/minor-updater.sh

# Build css files from less
%{buildroot}%{_datadir}/%{name}/tools/build-less-css.sh

# Build default config paths for packaged install
%{buildroot}%{_datadir}/%{name}/tools/generate-dir-sql.sh %{_localstatedir}/lib/%{name} %{_datadir}/%{name} > %{buildroot}%{_datadir}/%{name}/sql/%{name}-package-config.sql

%post
# Fix ownership and permissions of existing configuration.
if [ -f %{_sysconfdir}/%{name}/conf.php ]; then
	chown root:apache %{_sysconfdir}/%{name}/conf.php
	chmod 0644 %{_sysconfdir}/%{name}/conf.php
fi
chown -R alaress:alaress %{_datadir}/%{name}
/usr/share/%{name}/tools/create-data-dirs.sh /%{_localstatedir}/lib/%{name};
/usr/share/%{name}/tools/set-data-dir-perms.sh /%{_localstatedir}/lib/%{name} apache;
/usr/share/%{name}/tools/update-sql.sh;
/usr/share/%{name}/tools/clean-cache-dir.sh;
rm -f /usr/share/%{name}/www/.htaccess;

%post leave
/usr/share/%{name}/tools/module.sh install leave;

%post mobile
/usr/share/%{name}/tools/module.sh install mobile;

%files
%{_datadir}/%{name}
%{_sysconfdir}/%{name}
%{_sysconfdir}/cron.d/*
%{_sysconfdir}/logrotate.d/*
%attr(755,apache,apache) %{_localstatedir}/lib/%{name}
%exclude %{_datadir}/%{name}/tools/apache-vhost-*-sso
%exclude %{_datadir}/%{name}/templates/*/modules/leave
%exclude %{_datadir}/%{name}/www/modules/leave.php
%exclude %{_datadir}/%{name}/library/Leave
%exclude %{_datadir}/%{name}/sql/modules/leave
%exclude %{_datadir}/%{name}/templates/*/m
%exclude %{_datadir}/%{name}/sql/modules/mobile
%exclude %{_datadir}/%{name}/app
%exclude %{_datadir}/%{name}/www/include/permission.php

%files sso
%{_datadir}/%{name}/tools/apache-vhost-*-sso

%files leave
%{_datadir}/%{name}/templates/*/modules/leave
%{_datadir}/%{name}/www/modules/leave.php
%{_datadir}/%{name}/library/Leave
%{_datadir}/%{name}/sql/modules/leave

%files mobile
%{_datadir}/%{name}/templates/*/m
%{_datadir}/%{name}/sql/modules/mobile
%{_datadir}/%{name}/app

%files mgssport
%{_datadir}/%{name}/www/include/permission.php
