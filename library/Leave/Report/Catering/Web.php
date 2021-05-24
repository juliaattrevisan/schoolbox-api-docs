<?php

/**
 * Leave_Report_Catering_Web
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Report_Catering_Web extends Leave_Report_Catering_Abstract
{
    protected $templateFile = 'modules/leave/cateringReport.html';
    protected $houseMapper = null;

    /**
     * Setup
     */
    protected function setup()
    {
        $this->setHouseMapper(HouseResidentialMapper::getInstance());
    }

    /**
     * Render
     */
    public function render()
    {
        $data = $this->getData();

        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setBlock(TEMPLATE_content, 'no_houses', 'no_houses_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'house', 'house_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'request_date_header', 'request_date_header_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'request_period_total', 'request_period_total_block');
        $this->getPage()->setBlock('house', 'request_period', 'request_period_block');


        $this->getPage()->setVar(array(
            'REPORT_DATE' => $data['date']
        ));

        /* For each day, render a heading
         * Exclude initial Friday -_-
         */
        for ($i = 0; $i < count($data['dateList']); $i++) {
            $currentDate = $data['dateList'][$i];

            $this->getPage()->setVar(array(
                'REQUEST_DATE_HEADER_DATE' => $currentDate->getLongDateOnly()
            ));

            $this->getPage()->parse('request_date_header_block', 'request_date_header', true);
        }

        /* For each day, render totals
         */
        for ($i = 0; $i < count($data['totals']); $i++) {
            $currentSegment = $data['totals'][$i];

            $this->getPage()->setVar(array(
                'REQUEST_PERIOD_TOTAL_MORNING' => $currentSegment['present_morning'],
                'REQUEST_PERIOD_TOTAL_DAY' => $currentSegment['present_day'],
                'REQUEST_PERIOD_TOTAL_NIGHT' => $currentSegment['present_night'],
            ));

            $this->getPage()->parse('request_period_total_block', 'request_period_total', true);
        }

        /* For each house
         */
        if (is_array($data['houseList'][0]) && count($data['houseList'][0])) {
            for ($i = 0; $i < count($data['houseList'][0]); $i++) {
                /* For each day
                 * (Exclude Friday -_-)
                 */
                for ($j = 0; $j < count($data['dateList']); $j++) {
                    $this->getPage()->setVar(array(
                        'REQUEST_PERIOD_MORNING' => (int)$data['houseList'][$j][$i]['present_morning'],
                        'REQUEST_PERIOD_DAY' => (int)$data['houseList'][$j][$i]['present_day'],
                        'REQUEST_PERIOD_NIGHT' => (int)$data['houseList'][$j][$i]['present_night'],
                    ));

                    $this->getPage()->parse('request_period_block', 'request_period', true);
                }

                /* Render house row, with alternating background colours
                 */
                $this->getPage()->setVar(array(
                    'HOUSE_ID' => $data['houseList'][0][$i]['houseId'],
                    'HOUSE_NAME' => Scrub::text($data['houseList'][0][$i]['name']),
                    'CLASS' => $i % 2 ? 'dark' : '',
                ));

                $this->getPage()->parse('house_block', 'house', true);

                /* Clear children after parsing out the parent
                 */
                $this->getPage()->setVar('request_period_block', '');
            }

            $this->getPage()->setVar('no_houses_block', '');
        } else {
            $this->getPage()->parse('no_houses_block', 'no_houses');
        }
    }

    /* Getters/Setters
     */

    /**
     * Get template file
     *
     * @return string
     */
    protected function getTemplateFile()
    {
        return $this->templateFile;
    }

    /**
     * Set house mapper
     *
     * @param HouseResidentialMapper $houseMapper
     */
    protected function setHouseMapper($houseMapper)
    {
        $this->houseMapper = $houseMapper;
    }

    /**
     * Get house mapper
     *
     * @return HouseResidentialMapper
     */
    protected function getHouseMapper()
    {
        return $this->houseMapper;
    }
}
