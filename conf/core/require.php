<?php
/**
 * File used to require essential autoload definitions.
 * Intentionally lowercase filename so it can't be found by regular autoloaders.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// kernel definitions

// register a special autoloader for loading a kernel
function kernel_load($name) {
	// abstraction dependencies
	require_once __DIR__ . '/../../library/Alaress/Application/Kernel.php';
	require_once __DIR__ . '/../../library/Alaress/Application/Boot/BootstrapInterface.php';

	$name = trim($name, '\\');

	// locate the class
	$kernFile = __DIR__ . '/../../library/' . str_replace('\\','/', $name) . '/Kernel.php';
	$bootFile = __DIR__ . '/../../library/' . str_replace('\\','/', $name) . '/Bootstrap.php';

	if(file_exists($kernFile) && is_readable($kernFile)) {
		require_once $kernFile;
	}
	else {
		return false;
	}

	if(file_exists($bootFile) && is_readable($bootFile)) {
		require_once $bootFile;
	}
	else {
		return false;
	}

	// this only gets used once, so we're finished now
	spl_autoload_unregister('kernel_load');
	return true;
};

spl_autoload_register('kernel_load');