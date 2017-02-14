<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Terminology;

use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Localisation\Mapper as LocalisationMapper;

class Controller extends Base
{
    const PAGE_CURRENT = 'terminology';

    /**
     * @var LocalisationMapper
     */
    protected $localisationMapper;

    /**
     * Lets build this thing!
     * @param $localisationMapper
     */
    public function __construct($localisationMapper)
    {
        $this->localisationMapper = $localisationMapper;
    }

    /**
     * Display the funky list editor thing
     */
    public function getIndex()
    {
        $this->getResponse()->content->set([
            'template' => '@adminTerminology/index.twig',
            'data'     => [
                'pageCurrent' => self::PAGE_CURRENT,
                'terms' => $this->localisationMapper->findAll()
            ],
            'title' => 'Admin - Terminology'
        ]);
    }

    /**
     * Update the term
     */
    public function postUpdateTerm()
    {
        $post = $this->getRequest()->post;

        try {

            $localisation = $this->localisationMapper->findById($post['term']);
            $localisation->setSingularForm($post['singularForm']);
            $localisation->setPluralForm($post['pluralForm']);
            $localisation->setCapitaliseFirst(!empty($post['capitaliseFirst']));

            $this->localisationMapper->save($localisation);

        } catch (MapperException $e) {
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminTerminology.getIndex'));
    }
}
