<?php

namespace Evaluation\Answer\Test;

use Evaluation\Answer\Comparison;
use PHPUnit\Framework\TestCase;

class ComparisionTest extends TestCase
{

    /**
     * @param string $a
     * @param string $b
     * @param bool   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataStrings')]
    public function testCompareString(string $a, string $b, bool $expected)
    {
        $comparison = $this->getObjectForTrait(Comparison::class);
        $this->assertEquals($expected, $comparison->compareStrings($a, $b));
    }

    public static function dataStrings(): array
    {
        return [
            ['Ich werde am Samstag Tennis spielen.','Ich werde am Samstag Tennis spielen.', true],
            ['Pourriez-vous parler plus lentement, s’il vous plaît ?', "Pourriez-vous parler plus lentement, s'il vous plait ?", false],
            ['s’il vous plaît',"s'il vous plaît",true],
            ['"ˮʺ','"""',true],
            ['Glück','gluck', false],
            ['français','francais',false],
            ['5÷2','5/2', true],
            ['—-','--',true],
            ['true','false',false],
        ];
    }
}
