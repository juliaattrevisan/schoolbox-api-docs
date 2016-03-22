<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Terms;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Assessment\Term;

/**
 * Controller for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terms
 */
class Controller extends Base
{
    /**
     * @var Term\Mapper
     */
    protected $termMapper;

    /**
     * Constructor
     *
     * @param Term\Mapper $termMapper
     */
    public function __construct(Term\Mapper $termMapper)
    {
        $this->termMapper = $termMapper;
    }

    /**
     * GET action for viewing list of terms
     */
    public function getIndex()
    {
        // TODO make this use the new way of creating responses
        $this->getResponse()->content->set([
            'template' => '@adminTerms/index.twig',
            'data'     => [
                'pageCurrent' => basename($this->getRequest()->query->get('page')),
                'terms'       => $this->termMapper->findAll(['order' => ['start' => 'desc']]),
            ],
            'title' => 'Admin - Terms'
        ]);
    }

    /**
     * POST action for creating a new term
     */
    public function postCreateTerm()
    {
        $term = $this->termMapper->createModel();
        $term
            ->setTitle($this->getRequest()->post->get('title'))
            ->setCode($this->getRequest()->post->get('code'))
            ->setStart(Alaress_Date_Format::newLocalShort($this->getRequest()->post->get('start')))
            ->setFinish(Alaress_Date_Format::newLocalShort($this->getRequest()->post->get('finish')))
        ;
        try {
            $this->termMapper->save($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action for updating an existing term
     *
     * @param Term\Model $term
     */
    public function postUpdateTerm(Term\Model $term)
    {
        $term
            ->setTitle($this->getRequest()->post->get('title'))
            ->setCode($this->getRequest()->post->get('code'))
            ->setStart(Alaress_Date_Format::newLocalShort($this->getRequest()->post->get('start')))
            ->setFinish(Alaress_Date_Format::newLocalShort($this->getRequest()->post->get('finish')))
        ;
        try {
            $this->termMapper->save($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action for archiving an existing term
     *
     * @param Term\Model $term
     */
    public function postArchiveTerm(Term\Model $term)
    {
        $term->setArchived(true);
        try {
            $this->termMapper->save($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action for restoring an existing archived term
     *
     * @param Term\Model $term
     */
    public function postRestoreTerm(Term\Model $term)
    {
        $term->setArchived(false);
        try {
            $this->termMapper->save($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action for un-archiving an existing term
     *
     * @param Term\Model $term
     */
    public function postDeleteTerm(Term\Model $term)
    {
        try {
            $this->termMapper->delete($term);
            $this->outputPostSuccess($term);
        } catch (\Exception $e) {
            $this->outputPostError($term, $e);
        }
    }

    /**
     * POST action success method.
     *
     * @param Term\Model $term
     */
    protected function outputPostSuccess(Term\Model $term)
    {
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set([
                'template' => "@adminTerms/listItem.twig",
                'format'   => 'nochrome',
                'data'     => ['term' => $term,]
            ]);
        } else {
            $this->getResponse()->redirect->to($this->getRouter()->generate('adminTerms.getIndex'));
        }
    }

    /**
     * Outputs a POST error, perhaps via AJAX
     *
     * @param Term\Model $term
     * @param \Exception $e    The exception which caused the error.
     *
     * @throws \Exception If not an AJAX request, pass $e down the chain.
     */
    protected function outputPostError(Term\Model $term, \Exception $e)
    {
        if ($this->getRequest()->isXhr()) {
            // Dump out an AJAX error
            $this->getResponse()->status->setCode(500);
            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => $e->getMessage(),
            ]);
        } else {
            // ¯\_(ツ)_/¯
            throw $e;
        }
    }
}
