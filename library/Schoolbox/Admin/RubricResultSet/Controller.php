<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\RubricResultSet;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
    private const MAX_ROWS_PER_RESULT_SET = 11;

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
    public function getList(): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubricResultSet/index.twig',
                'data'     => [
                    'pageCurrent'          => $this->router->generate('adminRubricResultSet.getList'),
                    'rubricsByResultSetId' => $this->mapper->findSets(),
                    'allBreadcrumbs'       => (
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubricResultSet.getList'))
                    )
                ]
            ]
        );
    }

    /**
     * Delete the result set defined by $resultSetId.
     * @throws RouteNotFound
     */
    public function postDelete($resultSetId): void
    {
        $this->mapper->deleteRubricResultSet((int)$resultSetId);
        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubricResultSet.getList'));
    }

    /**
     * @throws RouteNotFound
     */
    public function getCreate(): void
    {
        $this->getUpdate(null);
    }

    /**
     * @throws RouteNotFound
     */
    public function postCreate(): void
    {
        $this->postUpdate();
    }

    /**
     * Load templates/index.twig using data obtained in this function.
     * @throws RouteNotFound
     */
    public function getUpdate(?int $resultSetId): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubricResultSet/create.twig',
                'data'     => [
                    'maxRowsPerResultSet' => self::MAX_ROWS_PER_RESULT_SET,
                    'pageCurrent'         => $this->router->generate('adminRubricResultSet.getList'),
                    'resultSetId'         => $resultSetId,
                    'rubricResultRows'    => (($resultSetId === null) ? [] : $this->mapper->findRubricResultRows($resultSetId)),
                    'allBreadcrumbs'      => array_merge(
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubricResultSet.getList')),
                        [['Update']]
                    )
                ]
            ]
        );
    }

    /**
     * Save $_POSTed data to database, then redirect to getList.
     * @throws RouteNotFound
     */
    public function postUpdate(): void
    {
        $postedData    = $this->getRequest()->post->getArrayCopy();
        $indexedLabels = array();
        $labelsCount   = 0;

        foreach ($postedData['rubricResultLabels'] as $value => $label) {
            $indexedLabels[$value] = $label;

            if (++$labelsCount > self::MAX_ROWS_PER_RESULT_SET) {
                // The client should not send more than self::MAX_ROWS_PER_RESULT_SET to be saved.
                // In any case, we apply this limit at the server, and if it is surpassed we just ignore the excess rows.
                break;
            }
        }

        $resultSetIdOrNull = (($postedData['resultSetIdOrBlank'] === '') ? null : (int)$postedData['resultSetIdOrBlank']);
        $this->mapper->createOrOverwriteRubricResultSet($resultSetIdOrNull, $indexedLabels);

        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubricResultSet.getList'));
    }
}
