<?php
/**
 * Resets the entire database for automated test purposes.
 *
 * Please remove this when packaging Schoolbox!!!!!
 */
?>
<html>
    <head><title>Database reset</title></head>
    <body>
        <h1>Database reset - if this is a live server, you're screwed!</h1>
        <pre><?php echo shell_exec(realpath(__DIR__.'/../../tools/vagrant-rebuild-database.sh')); ?></pre>
    </body>
</html>
