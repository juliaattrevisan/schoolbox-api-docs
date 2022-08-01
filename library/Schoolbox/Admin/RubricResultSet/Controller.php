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
use Exception;
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
     */
    public function getDelete($resultSetId): void
    {
        $this->mapper->deleteRubricResultSet((int)$resultSetId);
        $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubricResultSet.getList'));
    }

    /**
     * Load templates/index.twig using data obtained in this function.
     * @throws RouteNotFound
     */
    public function getCreate(): void
    {
        $this->getResponse()->content->set(
            [
                // Same template and data schema as for getList().
                'template' => '@adminRubricResultSet/create.twig',
                'data'     => [
                    'MAX_ROWS_PER_RESULT_SET' => self::MAX_ROWS_PER_RESULT_SET,
                    'pageCurrent'             => $this->router->generate('adminRubricResultSet.getCreate'),
                    'resultSetId'             => null,
                    'rubricResultRows'        => [],
                    'toastPopupMessage'       => '',
                    'toastPopupMessageType'   => '',
                    'allBreadcrumbs'          => array_merge(
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubricResultSet.getList')),
                        [['Create', '']]
                    )
                ]
            ]
        );
    }

    // Same as postUpdate()?
    public function postCreate(): void
    {
        $this->postUpdate();
    }

    /**
     * Load templates/index.twig using data obtained in this function.
     * @toastPopupMessageType string Expected values 'success', 'alert', ''.  Blank means do not display.
     * @toastPopupMessage string
     * @serverProcessSucceeded ?bool
     * @throws RouteNotFound
     */
    public function getUpdate(int $resultSetId, ?string $toastPopupMessageType = null, ?string $toastPopupMessage = null): void
    {
        $this->getResponse()->content->set(
            [
                'template' => '@adminRubricResultSet/create.twig',
                'data'     => [
                    'MAX_ROWS_PER_RESULT_SET' => self::MAX_ROWS_PER_RESULT_SET,
                    'pageCurrent'             => $this->router->generate('adminRubricResultSet.getUpdate'),
                    'resultSetId'             => $resultSetId,
                    'rubricResultRows'        => $this->mapper->findRubricResultRows((int)$resultSetId),
                    'toastPopupMessage'       => $toastPopupMessage,
                    'toastPopupMessageType'   => $toastPopupMessageType,
                    'allBreadcrumbs'          => array_merge(
                        (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminRubricResultSet.getList')),
                        [['Update', '']]
                    )
                ]
            ]
        );
    }

    /**
     * Save $_POSTed data to database, then redirect to getList().
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

        $boolRequestSucceeded = true;
        $resultSetIdOrNull    = (($postedData['resultSetIdOrBlank'] === '') ? null : (int)$postedData['resultSetIdOrBlank']);

        try {
            $resultSetId = $this->mapper->createOrOverwriteRubricResultSet($resultSetIdOrNull, $indexedLabels);
        } catch (Exception $e) {
            // TODO: Should I log the exception message somewhere?
            $boolRequestSucceeded = false;
            $resultSetId          = $resultSetIdOrNull;
        }

        if ($resultSetId === null) {
            // This may happen on a create request if an exception is caught in the try block above.
            // Since we do not have a resultSetId in this case, all we can do is redirect to the result sets page.
            $this->getResponse()->redirect->to($this->getRouter()->generate('adminRubricResultSet.getList'));
        } else {
            // TODO: Redirect instead of calling $this->getUpdate().
            $this->getUpdate(
                $resultSetId,
                (($boolRequestSucceeded) ? 'success' : 'alert'    ),
                (($boolRequestSucceeded) ? 'Success!' : 'Failure.')
            );
        }
    }
}
