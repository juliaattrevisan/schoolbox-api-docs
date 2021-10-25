<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Intellischool;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Resource\Folder\Code\Model as ClassCode;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\User;

/**
 * Controller for the Intellischool integration.
 */
class Controller extends Base
{
    /**
     * Factory for creating JWTs for sending to Intellischool.
     */
    private JwtFactory $jwtFactory;

    /**
     * Constructor
     *
     * @param JwtFactory $jwtFactory
     */
    public function __construct(JwtFactory $jwtFactory)
    {
        $this->jwtFactory = $jwtFactory;
    }

    /**
     * Intellischool student dashboard.
     *
     * @api GET /intellischool/student/{studentId}
     * @since 21.1.0
     *
     * @param User $student
     */
    public function getStudentDashboard(User $student): void
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@intellischool/studentDashboard.twig',
                [
                    'student' => $student,
                    'jwt'     => $this->jwtFactory->buildForLearnerProfile($this->getUser(), $student),
                ],
                'Intellischool Student Dashboard - ' . $student->getFullName(),
            )
        );
    }

    /**
     * Intellischool default class dashboard: redirects to the first class code
     * for this folder, or 404s if the folder has no class code.
     *
     * @api GET /intellischool/class/{folderId}
     * @since 21.1.0
     *
     * @param Model $folder
     * @throws NotFound if `$folder` has no class codes
     */
    public function getBaseClassDashboard(Model $folder): void
    {
        $classCodes = $folder->getCodes();
        if (!$classCodes) {
            throw new NotFound();
        }
        $firstClassCode = $classCodes->first();
        if (!$firstClassCode instanceof ClassCode) {
            throw new NotFound();
        }
        $this->response->redirect->to(
            $this->router->generate(
                'intellischool.getClassDashboard',
                ['folderId' => $folder->getId(), 'classCode' => $firstClassCode->getCode()],
            )
        );
    }

    /**
     * Intellischool class dashboard.
     *
     * @api GET /intellischool/class/{folderId}/code/{classCode}
     * @since 21.1.0
     *
     * @param Model $folder
     * @param string $classCode
     * @throws NotFound if `$classCode` isn't a class code in `$folder`
     */
    public function getClassDashboard(Model $folder, string $classCode): void
    {
        // Verify this class code actually belongs to the folder
        $classCodes = $folder->getCodes();
        if (!$classCodes) {
            throw new NotFound();
        }

        $classCodeModel = $classCodes->getBy('code', $classCode);
        if (!$classCodeModel instanceof ClassCode) {
            throw new NotFound();
        }

        // Output the class code
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@intellischool/classDashboard.twig',
                [
                    'folder'    => $folder,
                    'classCode' => $classCode,
                    'jwt'       => $this->jwtFactory->buildForClass($this->getUser(), $classCode),
                ],
                sprintf('Intellischool Class Dashboard - %s (%s)', $folder->getName(), $classCode),
            )
        );
    }
}
