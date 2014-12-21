<?php
/**
 * Source selector for tiles components.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Component_Config_TileSource extends Component_Config_Abstract {

    public function parse(SitePage $page, $key, $keyblock, $configValue, Component_ControllerAbstract $component = null)
    {
        // a dropdown of tiles in the current alt source.
        // If no alt source is selected, this doesn't get shown.
    }
}
