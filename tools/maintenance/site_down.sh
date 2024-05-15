#!/usr/bin/env bash
# Simple script to place schoolbox into maintenance mode.
# IMPORTANT - IF YOU CHANGE THIS FILE, YOU MUST PORT THE SAME LOGICAL CHANGE TO upgrade-instances.sh
php /usr/share/schoolbox/tools/maintenance/site_down.php
