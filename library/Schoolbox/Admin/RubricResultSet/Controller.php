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
use JetBrains\PhpStorm\NoReturn;
use Schoolbox\Admin\Navigation\Subnav;

/**
 *
 */
class Controller extends Base
{
    // The number of rows corresponds to the number of levels in a rubric grid.
    // Our ui limits it arbitrarily to 11, despite the schema design permitting you to have more than that.
    private const MAX_ROWS_PER_RESULT_SET = 11;

    /**
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * Controller constructor.
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Show a list of rubric result sets.
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
     * @param $resultSetId
     */
    #[NoReturn] public function deleteResultSet($resultSetId): void
    {
        $this->mapper->deleteSet((int)$resultSetId);
        header('HTTP/1.0 200 OK');
        die;
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
     * Shows a list of labels belonging to a particular rubric result set.
     * @param int|null $resultSetId
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
                    'rubricResultRows'    => (($resultSetId === null) ? [] : $this->mapper->findRows($resultSetId)),
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
        $this->mapper->createOrOverwriteSet($resultSetIdOrNull, $indexedLabels);

        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubricResultSet.getList'));
    }
}
