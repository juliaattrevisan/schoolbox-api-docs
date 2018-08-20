<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Web\Controller\Base as BaseController;
use Alaress\Web\Template\DebugTwig;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Group;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\GroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionUsageMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as File;
use Url;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;

class Controller extends BaseController
{

    /** @var Factory */
    private $factory;

    /** @var RubricMapper */
    private $mapper;


    /**
     * @return Factory
     */
    public function getFactory()
    {
        return $this->factory;
    }


    /**
     * @param Factory $factory
     * @return self
     */
    public function setFactory(Factory $factory)
    {
        $this->factory = $factory;
        return $this;
    }


    /**
     * @return RubricMapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }


    /**
     * @param RubricMapper $mapper
     * @return self
     */
    public function setMapper(RubricMapper $mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * AJAX method for creating a new group or criterion
     *
     * @api POST /rubric
     * @level 156
     */
    public function create()
    {
        $post = $this->getRequest()->post;

        $formField = $post->get('formField', 'rb');

        $context = [
            DebugTwig::DEBUG_WRAP => false,
            'formField' => $formField,
            'rubric' => [
                'id' => $post->get('rubricId'),
                'levels' => $post->get('levelCount') ? array_fill(0, $post->get('levelCount'), ':)') : [],
            ],
            'markPerCapability' => $post->getBool('markPerCapability', false),
        ];

        $folderId = $post->get('folderId');
        if(!$folderId){
            $this->sendJSONError('Must supply a folder Id');
            return;
        }

        if ($post->get('type') == 'criterion') {

            $group = $post->get('groupName');
            $name  = $post->get('name');
            if(!$group){
                $this->sendJSONError('Must supply a Group for new Criterion');
                return;
            }

            $criteria      = CriterionMapper::getInstance()->findForFolder($folderId);
            $proposedGroup = $this->mapper->normaliseForComparison($group);
            $proposedName  = $this->mapper->normaliseForComparison($name);
            foreach ($criteria as $criterion){
                $normGroup = $this->mapper->normaliseForComparison($criterion->getGroup()->getName());
                $normName = $this->mapper->normaliseForComparison($criterion->getName());
                if($normGroup == $proposedGroup && $normName == $proposedName){
                    // TODO localise!!!!!
                    $this->sendJSONError("Indicator already exists in this Group");
                    return;
                }
            }

            $context['rowspan'] = null;
            $context['group'] = [
                'id' => $post->get('groupId')
            ];
            $context['crit'] = $this->saveNewCriterion($folderId, $group, $name);

            $template = 'group-row-editable';
        }

        elseif ($post->get('type') == 'group') {

            // Ensure the proposed Group name is not already in use.
            $criteria = CriterionMapper::getInstance()->findForFolder($folderId);
            $proposed = $this->mapper->normaliseForComparison($post->get('name'));
            foreach ($criteria as $criterion){
                $grouping = $this->mapper->normaliseForComparison($criterion->getGroup()->getName());
                if($proposed == $grouping){
                    // TODO localise
                    $this->sendJSONError("Group name already exists in this context");
                    return;
                }
            }

            $context['rowspan'] = 1;
            $context['group'] = [
                'id'   => rand(1, PHP_INT_MAX), // this is just so the inputs on the page are unique
                'name' => $post->get('name'),
                'used' => false,
            ];

            $template = 'group-editable';

        } else {
            return;
        }


        $this->getResponse()->content->set([
            'data'     => $context,
            'format'   => 'nochrome',
            'template' => "@rubric/$template.twig"
        ]);

    }

    /**
     * Sends a JSON error.
     *
     * @param mixed $data
     * @param int $statusCode
     */
    protected function sendJSONError($data, $statusCode = 400)
    {
        $this->getResponse()->status->set($statusCode);
        $this->getResponse()->content->set([
            'data'     => $data,
            'format'   => 'json',
        ]);
    }


    /**
     * Return a Twigged shard generated from the file ref given
     *
     * @api POST /rubric/import/{storageTuple}
     * @level 156
     *
     * @param File $file
     */
    public function import(File $file)
    {
        $post = $this->getRequest()->post;

        try {
            if ($file->getMime(true) != 'text/plain') {
                throw new \Schoolbox\Resource\Exception("Not a plaintext CSV file, cannot parse");
            }

            if ($boxId = $post->get('boxId')) {
                $box = SubmissionBoxMapper::getInstance()->findById($boxId);
                $folderId = $box->getCategory()->getId();

            } elseif ($folderId = $post->get('folderId')) {
                $box = SubmissionBoxMapper::getInstance()->createModel()->setId(0);

            } else {
                // can't work with that
                throw new \Schoolbox\Mapper\Exception('No folder or box ID set');
            }

            // attempt to build the grid
            $maxLevels = $this->getDi()->get('config')->get('rubric_max_levels');
            [$grid, $usingMarkPerCapability, $usingMarkRange] = $this->getMapper()->buildFromCSV(
                $file,
                $folderId,
                $maxLevels
            );
            $grid->setSubmissionBox($box);

        } catch (\Exception $e) {

            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => $e->getMessage()
            ]);
            return;
        }

        // TODO inject new rubric grid here
        $context = [
            'formField'              => $post->get('formField', 'rb'),
            'rubric'                 => $grid->toContext(),
            'usingMarkPerCapability' => $usingMarkPerCapability,
            'usingMarkRange'         => $usingMarkRange,
        ];

        $this->getResponse()->content->set([
            'data'     => $context,
            'format'   => 'nochrome',
            'template' => '@rubric/grid-editable.twig'
        ]);
    }

    /**
     * Download the given rubric as a CSV
     *
     * @api POST /rubric/csv
     * @level 156
     */
    public function download()
    {
        $post     = $this->request->post->get('rb');
        $boxId    = key($post);
        $box      = SubmissionBoxMapper::getInstance()->findById($boxId);
        $grid     = (new Factory())->buildFromPostData($post, $box->isMarkedPerRubricGroup(), $box->isMarkedUsingRange(), false);
        $basename = (new Url($box->getName()))->getSlug();
        $content  = $this->array2csv(
            $grid->toGrid(
                $this->request->post->getBool('markPerCapability') ?? false,
                $this->request->post->getBool('useMarkRange') ?? false
            )
        );

        $headers  = $this->getResponse()->headers;

        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', strlen($content));
        $headers->set('Content-Disposition', "attachment; filename=\"$basename-rubric.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array)
    {
        $fp = fopen('php://temp', 'r+b');
        foreach ($array as $row) {
            $row = array_map('trim', $row);
            fputcsv($fp, $row);
        }
        rewind($fp);
        $result = stream_get_contents($fp);
        fclose($fp);
        return $result;
    }


    /**
     * @param int    $folderId
     * @param string $grouping
     * @param string $name
     * @return array
     */
    protected function saveNewCriterion($folderId, $grouping, $name)
    {
        $model = CriterionMapper::getInstance()->findOrCreateOneForFolderByKey($folderId, $name, $grouping);

        return [
            'name'        => $model->getName(),
            'id'          => $model->getId(),
            'descriptors' => [],
            'used'        => false,
        ];
    }

}
