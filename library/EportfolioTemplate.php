<?php

/* Template management for eportfolio */

class EportfolioTemplate extends DeprecatedModel
{

    public function __construct()
    {
        $this->_table = 'eportfolioTemplate';
        $this->_index = 'id';

        parent::__construct();
    }
}
