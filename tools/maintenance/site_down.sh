#!/usr/bin/env bash
# Simple script to place schoolbox into maintenance mode.
# IMPORTANT - IF YOU CHANGE THIS FILE, YOU MUST PORT THE SAME LOGICAL CHANGE TO debian/preinst
php site_down.php
