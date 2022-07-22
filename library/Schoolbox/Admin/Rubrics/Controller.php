<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Rubrics;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Exception;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * Controller constructor.
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Load templates/index.twig using data obtained in this function.
     * @throws RouteNotFound
     */
    public function getRubricResults(): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubrics/index.twig',
                'data'     => [
                    'pageCurrent'          => $this->router->generate('adminRubrics.getRubricResults'),
                    'rubricsByResultSetId' => $this->mapper->findSets(),
                    'allBreadcrumbs'       => (
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubrics.getRubricResults'))
                    )
                ]
            ]
        );
    }

    /**
     * Save $_POSTed data to database, then redirect to getRubricResults().
     * @throws Exception
     */
    public function postRubricResults(): void
    {
        if ($this->getRequest()->post->get('action') === 'set') {
            $this->mapper->saveRubricResults($this->getRequest()->post->get('items'));
        }

        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubrics.getRubricResults'));
    }
}
