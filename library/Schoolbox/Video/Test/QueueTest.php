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
     *
     * @dataProvider resolutionCoverProvider
     *
     * @param array $dimensions      Resolution to test
     * @param array $coverDimensions Resolution to cover
     * @param array $output          Resolution after constraint
     */
    public function testCoverForThumbnail($dimensions, $coverDimensions, $output)
    {
        $queue = new Queue();

        $result = $this->invokeMethod($queue, 'cover', [$dimensions, $coverDimensions]);
        $this->assertEquals($output, $result, "{$result[0]}x{$result[1]} does not equal {$output[0]}x{$output[1]}");
    }


    /**
     * Tests the contain function to ensure that it returns numbers that are divisible by 2
     * and maintain the aspect ratio
     *
     * @dataProvider resolutionContainProvider
     *
     * @param array $dimensions    Resolution to test
     * @param array $maxDimensions Resolution to contain within
     * @param array $output        Resolution after constraint
     */
    public function testContainForVideo($dimensions, $maxDimensions, $output)
    {
        $queue = new Queue();

        $result = $this->invokeMethod($queue, 'contain', [$dimensions, $maxDimensions]);
        $this->assertEquals($output, $result, "{$result[0]}x{$result[1]} does not equal {$output[0]}x{$output[1]}");
    }

    /**
     * List of standard and odd video resolutions, to be contained within 1280x720
     * @return array
     */
    public function resolutionCoverProvider()
    {
        return [
            [[1280, 720],  [64, 64], [114, 64]],
            [[1920, 1080], [64, 64], [114, 64]],
            [[2560, 1440], [64, 64], [114, 64]],
            [[3840, 2160], [64, 64], [114, 64]],
            // (800 / 600) * 64 = 85.3, but cover may not be odd: it is rounded up
            [[800, 600],   [64, 64], [86, 64]],
            [[960, 720],   [64, 64], [86, 64]],
            [[1440, 1080], [64, 64], [86, 64]],
            [[1280, 960],  [64, 64], [86, 64]],
            [[1856, 1392], [64, 64], [86, 64]],
            [[1920, 1080], [64, 64], [64, 64]],
            [[1680, 1050], [64, 64], [64, 64]],
            [[1080, 768],  [64, 64], [64, 64]],
            [[1644, 856],  [64, 64], [64, 64]],
            [[1242, 2208], [64, 64], [64, 64]],
            [[414, 736],   [64, 64], [64, 64]],
            [[720, 1280],  [64, 64], [64, 64]],
            [[768, 1080],  [64, 64], [64, 64]],
            [[576, 1024],  [64, 64], [64, 64]],
        ];
    }

    /**
     * List of standard and odd video resolutions, to be contained within 1280x720
     * @return array
     */
    public function resolutionContainProvider()
    {
        return [
            [[1280, 720],  [1280, 720], [1280, 720]],
            [[1920, 1080], [1280, 720], [1280, 720]],
            [[2560, 1440], [1280, 720], [1280, 720]],
            [[3840, 2160], [1280, 720], [1280, 720]],
            [[800, 600],   [1280, 720], [800, 600]],
            [[960, 720],   [1280, 720], [960, 720]],
            [[1440, 1080], [1280, 720], [960, 720]],
            [[1280, 960],  [1280, 720], [960, 720]],
            [[1856, 1392], [1280, 720], [960, 720]],
            [[1920, 1080], [1280, 720], [1280, 720]],
            [[1680, 1050], [1280, 720], [1152, 720]],
            [[1080, 768],  [1280, 720], [1014, 720]],
            [[1644, 856],  [1280, 720], [1280, 668]],
            [[1242, 2208], [1280, 720], [406, 720]],
            [[414, 736],   [1280, 720], [406, 720]],
            [[720, 1280],  [1280, 720], [406, 720]],
            [[768, 1080],  [1280, 720], [512, 720]],
            [[576, 1024],  [1280, 720], [406, 720]],
        ];
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    private function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
