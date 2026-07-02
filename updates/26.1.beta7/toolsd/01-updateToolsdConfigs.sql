# Set toolsd host to something that's correct for on-prem and Vagrant.
# Later, it will be set for cloud via IR.
UPDATE config SET value = 'http://127.0.0.1:8080/' WHERE name = 'toolsd_host';
