#!/bin/sh

# SQL Server ODBC driver install script:
# copied from https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server,
# adapted for sh shell.

# Detect architecture, exit if unsupported
case $(uname -m) in
    x86_64)  architecture="amd64" ;;
    arm64)   architecture="arm64" ;;
    aarch64) architecture="arm64" ;;
    *) architecture="unsupported" ;;
esac
if [ "unsupported" = "$architecture" ];
then
    echo "Alpine architecture $(uname -m) is not currently supported.";
    exit;
fi

# Download the desired package(s)
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_$architecture.apk
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_$architecture.apk

# Verify signature
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_$architecture.sig
curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_$architecture.sig

curl https://packages.microsoft.com/keys/microsoft.asc  | gpg --import -
gpg --verify msodbcsql18_18.4.1.1-1_$architecture.sig msodbcsql18_18.4.1.1-1_$architecture.apk
gpg --verify mssql-tools18_18.4.1.1-1_$architecture.sig mssql-tools18_18.4.1.1-1_$architecture.apk

# Install the package(s)
apk add --allow-untrusted msodbcsql18_18.4.1.1-1_$architecture.apk
apk add --allow-untrusted mssql-tools18_18.4.1.1-1_$architecture.apk
