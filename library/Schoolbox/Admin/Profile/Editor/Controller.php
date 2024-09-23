<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Web\Controller\Base;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(private \Schoolbox\Admin\Profile\Editor\Field\Mapper $fieldMapper)
    {
    }


    /**
     * Edit the layout of each profile page's fields, buttons, etc.
     */
    public function getIndex(): void
    {
        /* Figure out which roletype to display. By default, it loads teh Student
         * roletype as it tends to be the most heavily configured.
         */
        $roleType = (string)$this->requestQuery->get('roleType');

        /* Check that $roletype was valid; if not, default to Student
         */
        if (!\in_array($roleType, \Schoolbox\Role::getRoleTypes(), true)) {
            $roleType = \Schoolbox\Role::STUDENT;
        }

        $payload = (new Payload($this->fieldMapper))->generate($roleType);

        $this->response = $this->responseFromTemplate('@profileEditor/index.twig', [
            'payload' => $payload
        ], 'Profile Editor - ' . ucwords($roleType));
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
