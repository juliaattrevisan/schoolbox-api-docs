<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class WorkTypeSelectorWithLabelTemplateTest extends TestCase
{
    public function testRendersWorkTypeVisibilityAlertDataAndMessages(): void
    {
        $html = $this->renderTemplate([
            'allWorkTypes' => [
                [
                    'id' => 1,
                    'name' => 'Staff only',
                    'isVisibleToLearners' => false,
                    'isVisibleToParents' => false,
                ],
                [
                    'id' => 2,
                    'name' => 'Everyone',
                    'isVisibleToLearners' => true,
                    'isVisibleToParents' => true,
                ],
                [
                    'id' => 3,
                    'name' => 'Students only',
                    'isVisibleToLearners' => true,
                    'isVisibleToParents' => false,
                ],
                [
                    'id' => 4,
                    'name' => 'Parents only',
                    'isVisibleToLearners' => false,
                    'isVisibleToParents' => true,
                ],
            ],
            'assessment' => [
                'familyName' => 'task',
                'workType' => [
                    'id' => 2,
                ],
            ],
            'courseManaged' => [
                'isReadonly' => false,
            ],
            'locale' => [
                'activity' => new class {
                    public string $proper = 'Activity';

                    public function __toString(): string
                    {
                        return 'activity';
                    }
                },
            ],
        ]);

        $decodedHtml = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        self::assertStringContainsString('data-work-type-visibility-message="This work type sets the Activity Audience to staff only', $decodedHtml);
        self::assertStringContainsString('data-work-type-visibility-message="This work type sets the Activity Audience to staff, students, and parents', $decodedHtml);
        self::assertStringNotContainsString('document.currentScript', $decodedHtml);
        self::assertStringNotContainsString('$(function', $decodedHtml);
        self::assertStringContainsString('data-work-type-visibility-alert', $decodedHtml);
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff, students, and parents',
            $decodedHtml
        );
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff and students. Parents cannot access this activity',
            $decodedHtml
        );
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff and parents. Students cannot access this activity',
            $decodedHtml
        );
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff only',
            $decodedHtml
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderTemplate(array $context): string
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 5));
        $twig = new Environment($loader);

        return $twig->render(
            'library/Schoolbox/Learning/Assessment/templates/fragments/workTypeSelectorWithLabel.twig',
            $context
        );
    }
}
