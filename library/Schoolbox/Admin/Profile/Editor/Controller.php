<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Profile\Editor;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct(
        private Field\Mapper $fieldMapper,
        private Tab\Mapper $tabMapper,
        private \Schoolbox\CampusMapper $campusMapper,
        private \Schoolbox\Config\ConfigMapper $configMapper,
        private \Schoolbox\Profile\Button\Mapper $buttonMapper
    ) {
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
        if (!isset(Payload::CONFIG_ROLETYPE[$roleType])) {
            $roleType = \Schoolbox\Role::STUDENT;
        }

        /* Frontend wanted the full payload for all roletypes when we arrive at
         * the index screen, rather than have the $roleType's payload specifically.
         */
        $payload = (new Payload(
            $this->getConfigLoader(),
            $this->routeGenerator,
            $this->fieldMapper,
            $this->tabMapper,
            $this->campusMapper,
            $this->buttonMapper,
        ))->generateAll();

        $this->response = $this->responseFromTemplate('@adminProfileEditor/index.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminProfileEditor.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminProfileEditor.getIndex')),
            /* Profile Fields
             */
            'payload' => $payload,
        ], 'Profile Editor - ' . ucwords($roleType));
    }


    /**
     * Update the name format for users' names on their profile screens.
     *
     * This only applies to student roletype for now, because of EIM's custom work,
     * but it could potentially spread to other roletypes once people get wind of it.
     *
     * @param string $roleType
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function postNameFormat(string $roleType): void
    {
        try {
            /* Figure out which roletype config to update
             */
            $configName = Payload::CONFIG_ROLETYPE[$roleType];
            $nameFormat = $this->requestPost->get('nameFormat');

            /* No format? No trabajo; exit early
             */
            if (null === $nameFormat) {
                return;
            }

            $config = $this->configMapper->findOneByColumn('name', $configName)
                ->setValue($nameFormat);

            $this->configMapper->save($config);

            $this->response = $this->responseAsJson(['success' => true]);
        } catch (\Exception $e) {
            $this->response = $this->responseAsJson(
                ['success' => false, 'message' => $e->getMessage()]
            )->withStatus(500);
        }
    }
}
