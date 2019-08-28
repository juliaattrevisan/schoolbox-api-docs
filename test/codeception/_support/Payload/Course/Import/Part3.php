<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload\Course\Import;

use Payload\PostTrait;
use Payload\Request;

class Part3 extends Request
{
    use PostTrait;

    private $returnUrl;

    public function __construct(
        int $initialCourseSpecId,
        int $initialFolderId,
        int $componentInstanceId,
        string $returnUrl,
        int $selectedCourseSpecId,
        string $selectedImportFolderIds,
        ?int $courseImplId,
        array $selectedUnits
    ) {
        $this->returnUrl = $returnUrl;
        $this->setReferer("/course/import/$initialCourseSpecId/folder/$initialFolderId/$componentInstanceId?returnUrl=$returnUrl");

        $this->setFormParam('action', $courseImplId === null ? 'import' : 'modify');
        $this->setFormParam('course_spec_id', $selectedCourseSpecId);
        $this->setFormParam('import_folders', $selectedImportFolderIds);
        $this->setFormParam('course_impl_id', $courseImplId === null ? '' : (string)$courseImplId);
        $this->setFormParam('component_id', $componentInstanceId);

        foreach ($selectedUnits as $unit) {
            $this->setFormParam("unit[{$unit['spec_id']}][unit_spec_id]", $unit['spec_id']);
            $this->setFormParam("unit[{$unit['spec_id']}][unit_impl_id]", $unit['impl_id']);
            $this->setFormParam("unit[{$unit['spec_id']}][open_date]", $unit['open']);
            $this->setFormParam("unit[{$unit['spec_id']}][close_date]", $unit['close']);
        }

        $this->setFormParam('part-3', 1);
    }

    public function setAssessmentImport(int $unitSpecId, ?int $assSpecId, ?int $assImplId, bool $checked, ?int $publish, ?int $open, ?int $due, ?int $close): void
    {
        $aId = $assSpecId ?? $assImplId;
        $this->setFormParam("unit[$unitSpecId][a][$aId][spec_id]", $assSpecId === null ? '' : (string)$assSpecId);
        $this->setFormParam("unit[$unitSpecId][a][$aId][impl_id]", $assImplId === null ? '' : (string)$assImplId);
        if ($checked) {
            $this->setFormParam("unit[$unitSpecId][a][$aId][checked]", 1);
        }
        $dates = [
            'publish' => $publish,
            'open'    => $open,
            'due'     => $due,
            'close'   => $close,
        ];
        foreach ($dates as $dateLabel => $timestamp) {
            if ($timestamp === null) {
                $this->unsetFormParam("unit[$unitSpecId][a][$aId][dates][$dateLabel]");
            } else {
                $this->setFormParam("unit[$unitSpecId][a][$aId][dates][$dateLabel]", date('d/m/Y g:ia', $timestamp));
            }
        }
    }

    public function setSubAssessmentImport(int $unitSpecId, int $projectSpecId, ?int $assSpecId, ?int $assImplId, bool $checked, ?int $publish, ?int $open, ?int $due, ?int $close): void
    {
        $aId = $assSpecId ?? $assImplId;
        $this->setFormParam("unit[$unitSpecId][a][$projectSpecId][a][$aId][spec_id]", $assSpecId === null ? '' : (string)$assSpecId);
        $this->setFormParam("unit[$unitSpecId][a][$projectSpecId][a][$aId][impl_id]", $assImplId === null ? '' : (string)$assImplId);
        if ($checked) {
            $this->setFormParam("unit[$unitSpecId][a][$projectSpecId][a][$aId][checked]", 1);
        }
        $dates = [
            'publish' => $publish,
            'open'    => $open,
            'due'     => $due,
            'close'   => $close,
        ];
        foreach ($dates as $dateLabel => $timestamp) {
            if ($timestamp === null) {
                $this->unsetFormParam("unit[$unitSpecId][a][$projectSpecId][a][$aId][dates][$dateLabel]");
            } else {
                $this->setFormParam("unit[$unitSpecId][a][$projectSpecId][a][$aId][dates][$dateLabel]", date('d/m/Y g:ia', $timestamp));
            }
        }
    }

    protected function getUrl(): string
    {
        return "/course/import/activities?returnUrl={$this->returnUrl}";
    }
}
