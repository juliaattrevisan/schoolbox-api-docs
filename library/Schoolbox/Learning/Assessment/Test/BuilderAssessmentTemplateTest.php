<?php

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;

class BuilderAssessmentTemplateTest extends TestCase
{
    public function testDeleteActionUsesAuthorizedRoute(): void
    {
        $template = file_get_contents(
            __DIR__ . '/../templates/builder/assessment.twig'
        );

        self::assertNotFalse($template);
        self::assertStringContainsString(
            "routeLookupIfAuthorized('learningAssessment.postDeleteAssessment'",
            $template
        );
        self::assertStringNotContainsString(
            "routeLookup('learningAssessment.postDeleteAssessment'",
            $template
        );
        self::assertStringContainsString('{% if deleteRoute %}', $template);
    }
}
