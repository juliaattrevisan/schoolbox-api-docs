<?php
/**
 * Alaress_Image_Processor_Strategy_Interface
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
interface Alaress_Image_Processor_Strategy_Interface {
    /**
     * Manipulate image
     *
     * @param  resource $image
     * @return resource The manipulated image
     */
    public function manipulate($image);

    /**
     * @param  bool  $supports_alpha_channel
     * @return $this
     */
    public function setSupportsAlphaChannel($supports_alpha_channel);

    /**
     * @return bool
     */
    public function getSupportsAlphaChannel();
}
