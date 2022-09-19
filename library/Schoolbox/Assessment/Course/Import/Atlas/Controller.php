<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import\Atlas;

use Alaress\Web\Controller\Base as BaseController;
use GuzzleHttp\Exception as HttpException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Assessment\Course\Import\Atlas\Api;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Controller extends BaseController
{
    /**
     * @var Api\Bridge
     */
    private $apiBridge;


    /**
     * @var Importer
     */
    private $importer;


    /**
     * @var FolderMapper
     */
    private $folderMapper;


    /**
     * @var CourseSpecMapper
     */
    private $courseSpecMapper;


    /**
     * Constructor
     *
     * @param Api\Bridge       $apiBridge
     * @param Importer         $importer
     * @param FolderMapper     $folderMapper
     * @param CourseSpecMapper $courseSpecMapper
     */
    public function __construct(
        Api\Bridge $apiBridge,
        Importer $importer,
        FolderMapper $folderMapper,
        CourseSpecMapper $courseSpecMapper
    ) {
        $this->apiBridge        = $apiBridge;
        $this->importer         = $importer;
        $this->folderMapper     = $folderMapper;
        $this->courseSpecMapper = $courseSpecMapper;
    }


    /**
     * Render form for the import page.
     */
    public function getImport(): void
    {
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@courseImportAtlas/import.twig',
                [
                    'enableAttachmentImport' => Module::ENABLE_ATTACHMENT_IMPORT,
                    'isValidFacultyRoot'     => $this->courseSpecMapper->isConfiguredFacultyRootValid(),
                    'returnUrl'              => [
                        'key'   => Redirect::RETURN_URL,
                        'value' => [
                            'raw'     => Redirect::decode($returnUrl),
                            'encoded' => $returnUrl,
                        ]
                    ],
                ],
                'Atlas Import',
                'course'
            )
        );
    }


    /**
     * Action for getting a list of all curricula.
     *
     * @throws \Throwable
     */
    public function getCurriculumList(): void
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->apiBridge->getCurriculumList()));
    }


    /**
     * Action for getting a curriculum and contents in a single request.
     *
     * @param int $curriculumId
     * @throws \Throwable
     */
    public function getCurriculum(int $curriculumId): void
    {
        $this->getResponse()->content->set(
            $this->buildJsonOutput(
                $this->apiBridge->getCurriculum($curriculumId, Module::ENABLE_ATTACHMENT_IMPORT)
            )
        );
    }


    /**
     * POST action for `getImport` mage form.
     */
    public function postImport(): void
    {
        // Part 1: I have chosen a curriculum plan, now get its contents
        if ($this->request->post->getBool('part-1')) {
            $this->postImportPart1();
            return;
        }

        // Part 2: I have chosen units, now prepare to import them
        if ($this->request->post->getBool('part-2')) {
            $this->postImportPart2();
            return;
        }

        // Part 3: I have chosen the folder to import into, now complete the import
        if ($this->request->post->getBool('part-3')) {
            $this->postImportPart3();
            return;
        }
    }


    /**
     * POST part 1: I have chosen a plan, now show the parts
     */
    private function postImportPart1(): void
    {
        // Which plan did I choose?
        $curriculumPlanId = $this->request->post->getInt('curriculum-plan', 0);

        // None: render an error
        if ($curriculumPlanId === 0) {
            $this->throwNotSelectedError('You must select a curriculum plan.');
            return;
        }

        // Get the curriculum if possible
        try {
            $curriculumPlan = $this->apiBridge->getCurriculum($curriculumPlanId, Module::ENABLE_ATTACHMENT_IMPORT);
        } catch (\Throwable $e) {
            $this->throwCurriculumPlanFetchError($e);
            return;
        }

        $previousPost = $this->request->post->getArrayCopy();
        unset($previousPost['part-1']);
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@courseImportAtlas/import/plan.twig',
                [
                    'curriculum'             => $curriculumPlan,
                    'previousPost'           => $previousPost,
                    'attachmentDefaults'     => $this->importer->buildDefaultSettingsForAttachments($curriculumPlan),
                    'enableAttachmentImport' => Module::ENABLE_ATTACHMENT_IMPORT,
                ]
            )
        );
    }


    /**
     * POST part 2: I have chosen parts of a plan, now import them
     */
    private function postImportPart2(): void
    {
        // Which curriculum plan got selected?
        $curriculumPlanId = $this->request->post->getInt('curriculum-plan', 0);

        // None: render an error
        if ($curriculumPlanId === 0) {
            $this->throwNotSelectedError('You must select a curriculum plan.');
            return;
        }

        // Get the curriculum if possible
        try {
            $curriculumPlan = $this->apiBridge->getCurriculum($curriculumPlanId, Module::ENABLE_ATTACHMENT_IMPORT);
        } catch (\Throwable $e) {
            $this->throwCurriculumPlanFetchError($e);
            return;
        }

        $unit = $this->request->post->get('unit');
        if (null === $unit) {
            $this->throwNotSelectedError('You must select a unit.');
            return;
        }

        $previousPost = $this->request->post->getArrayCopy();
        unset($previousPost['part-2']);
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                '@courseImportAtlas/import/facultySelector.twig',
                [
                    'curriculum'             => $curriculumPlan,
                    'isValidFacultyRoot'     => $this->courseSpecMapper->isConfiguredFacultyRootValid(),
                    'previousPost'           => $previousPost,
                    'enableAttachmentImport' => Module::ENABLE_ATTACHMENT_IMPORT,
                ]
            )
        );
    }

    /**
     * Part 3: I have chosen the folder to import into, now complete the import
     */
    private function postImportPart3(): void
    {
        // Which curriculum plan got selected?
        $curriculumPlanId = $this->request->post->getInt('curriculum-plan', 0);

        // None: render an error
        if ($curriculumPlanId === 0) {
            $this->throwNotSelectedError('You must select a curriculum plan.');
            return;
        }

        // Get the curriculum if possible
        try {
            $curriculumPlan = $this->apiBridge->getCurriculum($curriculumPlanId, Module::ENABLE_ATTACHMENT_IMPORT);
        } catch (\Throwable $e) {
            $this->throwCurriculumPlanFetchError($e);
            return;
        }

        $unit = $this->request->post->get('unit');
        if (null === $unit) {
            $this->throwNotSelectedError('You must select a unit.');
            return;
        }

        // Get the folder the course is to be imported into
        $parentFolderId = $this->request->post->getInt('parent');
        //the UI is marking the folder field as required, so lets check that here
        if (0 === $parentFolderId) {
            $this->throwNotSelectedError('You must select a folder.');
            return;
        }
        $baseFolder = $this->folderMapper->findById($parentFolderId);

        // Import:
        // Pass through plan, folder, selected units
        try {
            $this->importer->import(
                $curriculumPlan,
                $baseFolder,
                $unit
            );
            $this->response->content->set($this->buildJsonOutput([]));
            // SHUP.
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->response->status->setCode(204);
        } catch (SchoolboxException $e) {
            if ($this->request->isXhr()) {
                $this->response->status->set(400);
                $this->response->content->set(
                    $this->buildJsonOutput($e->getMessage())
                );
            } else {
                throw $e;
            }
        }
    }


    /**
     * Render an error if no plan was selected when I needed one.
     */
    private function throwNotSelectedError(string $message): void
    {
        // SHUP.
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->response->status->setCode(400);
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                'sub/mage-error.twig',
                [
                    'error' => [
                        'problem' => $message,
                    ],
                ]
            )
        );
    }


    /**
     * Render an error if retrieving the plan failed for some reason.
     *
     * @param \Throwable $e The error that was raised on attempting to retrieve.
     */
    private function throwCurriculumPlanFetchError(\Throwable $e): void
    {
        $message = 'Something went wrong while retrieving the curriculum plan.';
        if ($e instanceof HttpException\ServerException) {
            // Something went wrong with Atlas
            $message .= ' Please try again later.';
        } elseif ($e instanceof HttpException\ClientException) {
            // Something went wrong with us, probably configuration
            $message .= ' Please contact your system administrator.';
        } elseif ($e instanceof HttpException\RequestException) {
            // Something went wrong somewhere; probably a timeout
            $message .= ' Please try again later.';
        } else {
            // Something unpredictable went wrong
            $message .= ' Please try again later.';
        }


        // SHUP.
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->response->status->setCode($e->getCode() ?: 400);
        $this->response->content->set(
            $this->buildUnthemedHtmlOutput(
                'sub/mage-error.twig',
                [
                    'error' => [
                        'problem' => $message,
                    ],
                ]
            )
        );
    }
}
