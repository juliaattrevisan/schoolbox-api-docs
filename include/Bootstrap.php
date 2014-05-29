<?php

// retained for backward compatibility

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\WebRequest');
new \Schoolbox\Application\WebRequest\Kernel();
