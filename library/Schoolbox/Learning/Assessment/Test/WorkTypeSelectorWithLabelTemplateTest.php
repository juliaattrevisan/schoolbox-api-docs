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

        self::assertStringContainsString('data-visible-to-learners="0"', $html);
        self::assertStringContainsString('data-visible-to-parents="0"', $html);
        self::assertStringContainsString('data-visible-to-learners="1"', $html);
        self::assertStringContainsString('data-visible-to-parents="1"', $html);
        self::assertStringContainsString('data-work-type-visibility-alert', $html);
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff, students, and parents',
            $html
        );
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff and students. Parents cannot access this activity',
            $html
        );
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff and parents. Students cannot access this activity',
            $html
        );
        self::assertStringContainsString(
            'This work type sets the Activity Audience to staff only',
            $html
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
