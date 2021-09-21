<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use lsolesen\pel\PelDataWindow;
use lsolesen\pel\PelException;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTag;
use Schoolbox\IConfigLoader;

/**
 * Sanitizer for HTML files.
 */
class Exif implements ISanitizer
{
    /**
     * Which mime types does this sanitizer accept?
     */
    private const ACCEPTED_MIMETYPE = ['image/jpeg', 'image/tiff'];

    private const GPS_TAGS = [
        PelTag::GPS_VERSION_ID,
        PelTag::GPS_LATITUDE_REF,
        PelTag::GPS_LATITUDE,
        PelTag::GPS_LONGITUDE_REF,
        PelTag::GPS_LONGITUDE,
        PelTag::GPS_ALTITUDE_REF,
        PelTag::GPS_ALTITUDE,
        PelTag::GPS_TIME_STAMP,
        PelTag::GPS_SATELLITES,
        PelTag::GPS_STATUS,
        PelTag::GPS_MEASURE_MODE,
        PelTag::GPS_DOP,
        PelTag::GPS_SPEED_REF,
        PelTag::GPS_SPEED,
        PelTag::GPS_TRACK_REF,
        PelTag::GPS_TRACK,
        PelTag::GPS_IMG_DIRECTION_REF,
        PelTag::GPS_IMG_DIRECTION,
        PelTag::GPS_MAP_DATUM,
        PelTag::GPS_DEST_LATITUDE_REF,
        PelTag::GPS_DEST_LATITUDE,
        PelTag::GPS_DEST_LONGITUDE_REF,
        PelTag::GPS_DEST_LONGITUDE,
        PelTag::GPS_DEST_BEARING_REF,
        PelTag::GPS_DEST_BEARING,
        PelTag::GPS_DEST_DISTANCE_REF,
        PelTag::GPS_DEST_DISTANCE,
        PelTag::GPS_PROCESSING_METHOD,
        PelTag::GPS_AREA_INFORMATION,
        PelTag::GPS_DATE_STAMP,
        PelTag::GPS_DIFFERENTIAL,
    ];

    /**
     * @var IConfigLoader
     */
    private IConfigLoader $config;

    /**
     * Constructor
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function accepts(string $mimetype): bool
    {
        return $this->config->get('remove_image_location_data')
            && in_array($mimetype, self::ACCEPTED_MIMETYPE);
    }

    /**
     * @inheritdoc
     */
    public function sanitize(string $mimetype, string $contents): ?string
    {
        if (!$this->accepts($mimetype)) {
            return null;
        }

        // quickly check whether this image contains gps info
        $image = new \Imagick();
        try {
            $image->readImageBlob($contents);
            $gpsProps = $image->getImageProperties('*GPS*');
            $image->clear();
            if (empty($gpsProps)) {
                return null;
            }
        } catch (\ImagickException $e) {
            return null;
        }

        // use PelJpeg to strip GPS data
        $pelImage = new PelJpeg();
        try {
            $pelImage->load(new PelDataWindow($contents));
        } catch (PelException $e) {
            return null;
        }

        $exif = $pelImage->getExif();
        if ($exif) {
            $tiff    = $exif->getTiff();
            $ifd     = $tiff->getIfd();
            $subIfds = $ifd->getSubIfds();
            foreach ($subIfds as $subIfd) {
                foreach (self::GPS_TAGS as $gpsTag) {
                    if ($subIfd->offsetExists($gpsTag)) {
                        $subIfd->offsetUnset($gpsTag);
                    }
                }
                $ifd->addSubIfd($subIfd);
            }
            $tiff->setIfd($ifd);
            $exif->setTiff($tiff);
            $pelImage->setExif($exif);
        }
        return $pelImage->getBytes();
    }
}
