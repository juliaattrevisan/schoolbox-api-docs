<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     */
    public function __construct(private ErrorMapper $errorMapper) {
    }


    /**
     * Edit the layout of each profile page's fields, buttons, etc.
     */
    public function getIndex(): void
    {
        $this->response = $this->responseFromTemplate('@profileEditor/index.twig', [

        ], 'Profile Editor');
    }


    /**
     * This executes when the superuser adds a field to a profile screen.
     *
     * @return void
     */
    public function postFieldAdd(): void
    {
        new \Alaress_Probe('postFieldAdd', $this->requestPost->getArrayCopy());
    }


    /**
     * This executes when the superuser removes a field from a profile screen.
     *
     * @return void
     */
    public function postFieldRemove(): void
    {
        new \Alaress_Probe('postFieldRemove', $this->requestPost->getArrayCopy());
    }


    /**
     * This executes when the superuser updates availability ("Appears On") and visibility
     * ("Visible For") checkbox settings on a particular field.
     *
     * @return void
     */
    public function postFieldSettings(): void
    {
        new \Alaress_Probe('postFieldSettings', $this->requestPost->getArrayCopy());
    }


    /**
     * This executes when the superuser reorders fields.
     *
     * @return void
     */
    public function postFieldSequence(): void
    {
        new \Alaress_Probe('postFieldSequence', $this->requestPost->getArrayCopy());
    }
}
