<?php

/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\DebugBar\Instance as DebugBar;

/**
 * Class Alaress_Probe
 *
 * Now outputs to the debugbar if it is enabled!
 *
 * Multiple columns get individual scroll capability so you can compare columns
 * of varying lengths without having to fight with just one scrollbar
 */
class Alaress_Probe
{

    /**
     * Alaress_Probe constructor.
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
            $width = floor(100 / $numArgs);

            echo "<div style='width:100%;height:0px;clear:both;'></div>";

            /* Multiple args get individual scrollys
             */
            $height = '';

            if ($numArgs > 1) {
                $height = 'max-height:100%;';
            }

            for ($i = 0; $i < $numArgs; $i++) {
                $contents = func_get_arg($i);

                echo "<div style='width:{$width}%;float:left;display:block;overflow:auto;{$height}'>";
                echo "<pre>";

                /* Also accommodate bools/nulls
                 */
                if (is_bool($contents) || $contents === null) {
                    ob_start();
                    var_dump($contents);
                    $contents = ob_get_contents();
                    ob_end_clean();
                }

                print_r($contents);

                echo "</pre>";
                echo "</div>";
            }

            echo "<div style='width:100%;height:0px;clear:both;'></div>";
        }
    }
}
