<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\IPC;

use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use RuntimeException;

class AlaressIPCProtocol implements ProtocolInterface
{

    /**
     * @return int
     */
    public function chunkLength()
    {
        return 2048;
    }

    /**
     * @param string $command
     * @param array $args
     * @return string
     */
    public function prepareCommand($command, $args = null)
    {
        return $command . DispatchStrategyInterface::STR_RECORD_SEPARATOR . $this->flatten($args);
    }

    /**
     * Prepare data for transmission. Inverse of ProtocolInterface::rebuild()
     *
     * @param array $array
     * @return string
     */
    public function flatten($array)
    {
        // if it's a string (like a serialization or something)
        if (!is_array($array)) {
            $array = array($array);
        }
        foreach ($array as $k => $v) {
            $array[$k] = base64_encode(serialize($v));
        }
        return implode(DispatchStrategyInterface::STR_UNIT_SEPARATOR, $array) . DispatchStrategyInterface::STR_END_OF_TRANSMISSION;
    }

    /**
     * Reconstitute flattened date. Inverse of ProtocolInterface::flatten()
     *
     * @param string $string
     * @return array
     */
    public function rebuild($string)
    {

        // trim the EOT character
        $string = trim($string, DispatchStrategyInterface::STR_END_OF_TRANSMISSION);

        // if there's a record separator, separate that first
        if (strpos($string, DispatchStrategyInterface::STR_RECORD_SEPARATOR) !== false) {
            $xpl = explode(DispatchStrategyInterface::STR_RECORD_SEPARATOR, $string);
            if (count($xpl) == 2) {
                list($command, $string) = $xpl;
            }
        }

        $xpl = explode(DispatchStrategyInterface::STR_UNIT_SEPARATOR, $string);

        foreach ($xpl as $k => $v) {
            try {
                $xpl[$k] = unserialize(base64_decode($v));
            } catch (RuntimeException $e) {
                $xpl[$k] = '';
            }
        }

        if (isset($command)) {
            return array($command, $xpl);
        }

        return $xpl;
    }

    /**
     * @return string
     */
    public function reject()
    {
        return DispatchStrategyInterface::STR_NACKNOWLEDGE;
    }

    /**
     * @return string
     */
    public function accept()
    {
        return DispatchStrategyInterface::STR_ACKNOWLEDGE;
    }

    /**
     * @return string
     */
    public function cancel()
    {
        return DispatchStrategyInterface::STR_CANCEL;
    }

    /**
     * @return string
     */
    public function end()
    {
        return DispatchStrategyInterface::STR_END_OF_TRANSMISSION;
    }

    /**
     * @param string $data
     * @return bool
     */
    public function isCancelled($data)
    {
        return $this->isStatus($data, $this->cancel());
    }

    /**
     * @param string $data
     * @return bool
     */
    public function isEnded($data)
    {
        return $this->isStatus($data, $this->end());
    }

    /**
     * @param string $data
     * @return bool
     */
    public function isRejected($data)
    {
        return $this->isStatus($data, $this->reject());
    }

    /**
     * @param string $data
     * @return bool
     */
    public function isAccepted($data)
    {
        return $this->isStatus($data, $this->accept());
    }

    /**
     * Helper function for determining worker status from response string
     *
     * @param string $data
     * @param string $status
     * @return bool
     */
    protected function isStatus($data, $status)
    {
        if (strlen($data)) {
            // test against last, then first, char
            return substr($data, -1, 1) === $status || substr($data, 0, 1) === $status;
        }
        return false;
    }
}
