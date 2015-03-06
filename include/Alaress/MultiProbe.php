<?php
/**
 * ThreeProbe
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress\DebugBar\Instance as DebugBar;

class Alaress_MultiProbe {

	/**
	 * Constructor
     *
     * Accepts multiple input vars
	 *
	 * @internal param $dataStructure
	 */
	public function __construct() {
		if (DebugBar::isEnabled()) {
		}
		else {
            $numArgs = func_num_args();

            $width = floor(100/$numArgs);

            for ($i = 0; $i < $numArgs; $i++) {
                echo "<div style='width:{$width}%;float:left;display:block;overflow:scroll;'>";
                echo "<pre>";
                print_r(func_get_arg($i));
                echo "</pre>";
                echo "</div>";
            }
            
            echo "<div class='clearfix'></div>";
		}
	}
} 