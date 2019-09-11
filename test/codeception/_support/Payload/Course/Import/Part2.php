<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Course\Import;

use Payload\PostTrait;
use Payload\Request;

class Part2 extends Request
{
    use PostTrait;

    /**
     * @param int      $initialCourseSpecId     Course spec in URL when page first loaded (may be different to selected course spec)
     * @param int      $initialFolderId         Folder in URL when page first loaded (may be different to selected folders)
     * @param int      $componentInstanceId
     * @param string   $returnUrl
     * @param int      $selectedCourseSpecId    Course spec selected in Step1
     * @param string   $selectedImportFolderIds Folders selected in Step1
     * @param int|null $courseImplId
     */
    public function __construct(
        int $initialCourseSpecId,
        int $initialFolderId,
        int $componentInstanceId,
        string $returnUrl,
        int $selectedCourseSpecId,
        string $selectedImportFolderIds,
        ?int $courseImplId
    ) {
        $this->setReferer("/course/import/$initialCourseSpecId/folder/$initialFolderId/$componentInstanceId?returnUrl=$returnUrl");

        $this->setFormParam('returnUrl', $returnUrl);
        $this->setFormParam('action', $courseImplId === null ? 'import' : 'modify');
        $this->setCourseSpecId($selectedCourseSpecId);
        $this->setFormParam('import_folders', $selectedImportFolderIds);
        $this->setFormParam('course_impl_id', $courseImplId === null ? '' : (string)$courseImplId);
        $this->setFormParam('component_id', $componentInstanceId);
        $this->setFormParam('part-1', 1);
    }

    public function setCourseSpecId(int $course_spec_id): void
    {
        $this->setFormParam('course_spec_id', $course_spec_id);
    }


    public function setUnitImport(int $index, int $unitSpecId, ?int $unitImplId, bool $enabled, int $openTimestamp, int $closeTimestamp): void
    {
        $this->setFormParam("unit[$index][unit_spec_id]", $unitSpecId);
        $this->setFormParam("unit[$index][unit_impl_id]", $unitImplId === null ? '' : (string)$unitImplId);
        $this->setFormParam("unit[$index][open_date]", date('d/m/Y', $openTimestamp));
        $this->setFormParam("unit[$index][close_date]", date('d/m/Y', $closeTimestamp));

        if ($enabled) {
            $this->setFormParam("unit[$index][import]", 'on');
        } else {
            $this->unsetFormParam("unit[$index][import]");
        }
    }

    protected function getUrl(): string
    {
        return '/course/import/units';
    }
}
