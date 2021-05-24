<?php

/**
 * Alaress_SilentProbe
 *
 * Now outputs to the debugbar if it is enabled!
 *
 * Silently output dump to a hidden div so you can discreetly diagnose
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\DebugBar\Instance as DebugBar;

class Alaress_Submarine
{

    /**
     * Alaress_SilentProbe constructor.
     * @param null $_
     * @throws \DebugBar\DebugBarException
     */
    public function __construct($_ = null)
    {
        $numArgs = func_num_args();

        if (DebugBar::isEnabled()) {
            for ($i = 0; $i < $numArgs; $i++) {
                DebugBar::getInstance()->getCollector('messages')->addMessage(func_get_arg($i));
            }
        } else {
            echo '<div style="display:none;">';
            echo "\n";

            for ($i = 0; $i < $numArgs; $i++) {
                $contents = func_get_arg($i);

                /* Also accommodate bools/nulls
                 */
                if (is_bool($contents) || $contents === null) {
                    ob_start();
                    var_dump($contents);
                    $contents = ob_get_contents();
                    ob_end_clean();
                }

                print_r($contents);
                echo "\n";
            }

            echo "</div>\n\n";
        }
    }
}
