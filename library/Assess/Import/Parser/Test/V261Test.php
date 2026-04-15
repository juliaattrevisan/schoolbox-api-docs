<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Assess\Import\Parser\Test;

use Assess\Import\Parser\V261;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Assessment\IAssessable;

class V261Test extends TestCase
{
    private function makeParser(string $markVisibility): V261
    {
        $xml = new \SimpleXMLElement(
            "<quiz><markvisibility>{$markVisibility}</markvisibility></quiz>"
        );
        return new V261($xml);
    }

    public function testGetMarkVisibilityReturnsPrivate(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_PRIVATE);
        $this->assertSame(IAssessable::MARK_VISIBILITY_PRIVATE, $parser->getMarkVisibility());
    }

    public function testGetMarkVisibilityReturnsStudent(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_STUDENT);
        $this->assertSame(IAssessable::MARK_VISIBILITY_STUDENT, $parser->getMarkVisibility());
    }

    public function testGetMarkVisibilityReturnsParent(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_PARENT);
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT, $parser->getMarkVisibility());
    }

    public function testGetMarkVisibilityReturnsParentStudent(): void
    {
        $parser = $this->makeParser(IAssessable::MARK_VISIBILITY_PARENT_STUDENT);
        $this->assertSame(IAssessable::MARK_VISIBILITY_PARENT_STUDENT, $parser->getMarkVisibility());
    }
}
