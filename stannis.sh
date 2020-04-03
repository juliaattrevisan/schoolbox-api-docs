#!/bin/bash

sudo phpdismod -v 7.1 ibm_db2 pdo_sqlsrv;
vendor/bin/phpstan analyze;
sudo phpdismod -v 7.1 ibm_db2 pdo_sqlsrv;
