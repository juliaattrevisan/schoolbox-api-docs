<?php
/**
 * Alaress_Probe
 *
 * Now outputs to the debugbar if it is enabled!
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Alaress\DebugBar\Instance as DebugBar;

class Alaress_Probe {

	/**
	 * Constructor
	 */
	public function __construct() {
        $numArgs = func_num_args();

		if (DebugBar::isEnabled()) {
            for ($i = 0; $i < $numArgs; $i++) {
                DebugBar::getInstance()->getCollector('messages')->addMessage(func_get_arg($i));
            }
		}
		else {
            $width = floor(100/$numArgs);

            echo "<div class='clearfix'></div>";

            for ($i = 0; $i < $numArgs; $i++) {
                echo "<div style='width:{$width}%;float:left;display:block;overflow:auto;'>";
                echo "<pre>";
                print_r(func_get_arg($i));
                echo "</pre>";
                echo "</div>";
            }

            echo "<div class='clearfix'></div>";
		}
	}
}
