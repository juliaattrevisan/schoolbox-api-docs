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
     */
    public function testCoverForThumbnail()
    {

        $thumbnailResolution = [64, 64];

        $resolutions = [
            [1280, 720],
            [1920, 1080],
            [2560, 1440],
            [3840, 2160],
            [800, 600],
            [960, 720],
            [1440, 1080],
            [1280, 960],
            [1856, 1392],
            [1920, 1080],
            [1680, 1050],
            [1080, 768],
            [1644, 856],
            [1242, 2208],
            [414, 736],
            [720, 1280],
            [768, 1080],
            [576, 1024],
        ];

        $queue = new Queue();


        foreach ($resolutions as $res) {
            $result = $this->invokeMethod($queue, 'cover', [$res, $thumbnailResolution]);
            $this->assertContains(64, $result, "{$result[0]}x{$result[1]}");
        }

    }


    /**
     * Tests the contain function to ensure that it returns numbers that are divisible by 2
     * and maintain the aspect ratio
     */
    public function testContainForVideo()
    {

        $videoResolution = [1280, 720];

        $resolutions = [
            [1280, 720],
            [1920, 1080],
            [2560, 1440],
            [3840, 2160],
            [800, 600],
            [960, 720],
            [1440, 1080],
            [1280, 960],
            [1856, 1392],
            [1920, 1080],
            [1680, 1050],
            [1080, 768],
            [1644, 856],
            [1242, 2208],
            [414, 736],
            [720, 1280],
            [768, 1080],
            [576, 1024],
        ];

        $outputResolutions = [
            [1280, 720],
            [1280, 720],
            [1280, 720],
            [1280, 720],
            [800, 600],
            [960, 720],
            [960, 720],
            [960, 720],
            [960, 720],
            [1280, 720],
            [1152, 720],
            [1014, 720],
            [1280, 668],
            [406, 720],
            [406, 720],
            [406, 720],
            [512, 720],
            [406, 720],
        ];

        $queue = new Queue();
        for ($i = 0; isset($resolutions[$i]); $i++) {
            var_dump($i);
            $result = $this->invokeMethod($queue, 'contain', [$resolutions[$i], $videoResolution]);
            $this->assertEquals($outputResolutions[$i], $result, "{$result[0]}x{$result[1]} does not equal {$outputResolutions[$i][0]}x{$outputResolutions[$i][1]}");
        }
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
