<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 */

namespace Schoolbox\Video\Test;

use Schoolbox\Video\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{




    /**
     * Tests the thumbnail cover function to ensure that the video is resized to at least 64 on one of the aspects
     * @dataProvider resolutionContainProvider
     * @var array $input Resolution to test
     */
    public function testCoverForThumbnail($input)
    {
        $thumbnailResolution = [64, 64];
        $queue = new Queue();

        $result = $this->invokeMethod($queue, 'cover', [$input, $thumbnailResolution]);
        $this->assertContains(64, $result, "{$result[0]}x{$result[1]}");
    }


    /**
     * Tests the contain function to ensure that it returns numbers that are divisible by 2
     * and maintain the aspect ratio
     * @dataProvider resolutionContainProvider
     * @var array $input Input resolution
     * @var array $output Resolution after constraint
     */
    public function testContainForVideo($input, $output)
    {
        $videoResolution = [1280, 720];
        $queue = new Queue();

        $result = $this->invokeMethod($queue, 'contain', [$input, $videoResolution]);
        $this->assertEquals($output, $result, "{$result[0]}x{$result[1]} does not equal {$output[0]}x{$output[1]}");
    }

    /**
     * List of standard and odd video resolutions, to be contained within 1280x720
     * @return array
     */
    public function resolutionContainProvider() {
        return [
            [[1280, 720],   [1280, 720]],
            [[1920, 1080],  [1280, 720]],
            [[2560, 1440],  [1280, 720]],
            [[3840, 2160],  [1280, 720]],
            [[800, 600],    [800, 600]],
            [[960, 720],    [960, 720]],
            [[1440, 1080],  [960, 720]],
            [[1280, 960],   [960, 720]],
            [[1856, 1392],  [960, 720]],
            [[1920, 1080],  [1280, 720]],
            [[1680, 1050],  [1152, 720]],
            [[1080, 768],   [1014, 720]],
            [[1644, 856],   [1280, 668]],
            [[1242, 2208],  [406, 720]],
            [[414, 736],    [406, 720]],
            [[720, 1280],   [406, 720]],
            [[768, 1080],   [512, 720]],
            [[576, 1024],   [406, 720]],
        ];
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

}
