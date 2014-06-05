<?php

// retained for backward compatibility

require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\WebRequest');
return new \Schoolbox\Application\WebRequest\Kernel();
