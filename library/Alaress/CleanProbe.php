<?php
/**
 * Alaress_Probe_Clean
 *
 * Now outputs to the debugbar if it is enabled!
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

class Alaress_CleanProbe {

    /**
     * Alaress_Probe constructor.
     * @param null $_
     */
	public function __construct($_ = null) {
        $numArgs = func_num_args();

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

            print_r($contents . "\t");
        }

        echo "\n\n";
    }
}
