<?php

namespace Schoolbox\Assessment\Rubric\Overview;

use \Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;

class Csv
{
    /**
     * Separator char between rubric title, capability name, indicator name
     */
    private const string SEPARATOR = '>';


    /**
     * Access "draft" or "published" based on whether we want the draft or published marks
     *
     * @var string
     */
    private string $markNode = 'published';


    /**
     * @param bool $showDrafts
     */
    public function __construct(bool $showDrafts)
    {
        $this->markNode = $showDrafts ? 'draft' : 'published';
    }


    /**
     * Generate a csv string of contents
     *
     * @param IAssessable $assessment
     * @param GenericCollection $participantUsers
     * @param array $assessments
     * @param array $studentMarks
     * @return string
     */
    public function generate(
        IAssessable $assessment,
        array $assessments,
        GenericCollection $participantUsers,
        array $studentMarks,
    ): string {
        return $this->array2csv($this->generateContents($assessment, $assessments, $participantUsers, $studentMarks));
    }


    /**
     * Generate an array of contents
     *
     * @param IAssessable $assessment
     * @param GenericCollection<User> $participantUsers
     * @param array $assessments
     * @param array $studentMarks
     * @return array
     */
    public function generateContents(
        IAssessable $assessment,
        array $assessments,
        GenericCollection $participantUsers,
        array $studentMarks,
    ): array {
        /* Header row(s) of the csv: start with these two mandatory columns
         * Everything after that is something derived from each grid's structure
         */
        $headings = ['Name', 'External ID'];

        /* We only care about this particular instance
         */
        $assessmentData = $assessments[$assessment->getId()];

        foreach ($assessmentData['gridData'] as $rubricInstanceId => $rubricInstanceData) {
            foreach ($rubricInstanceData['capabilities'] as $capabilityId => $capabilityData) {
                foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                    /* rubric title - capability name - indicator name
                     */
                    $headings[] = sprintf(
                        '%s %s %s %s %s',
                        $rubricInstanceData['title'],
                        self::SEPARATOR,
                        $capabilityData['name'],
                        self::SEPARATOR,
                        $indicatorData['name'],
                    );

                    /* A descriptor value normally sits in this column so we have to add a placeholder here
                     */
                    $headings[] = '';
                }

                /* Optionally, the capability mark goes here
                 */
                if ($rubricInstanceData['isMarkPerCapability']) {
                    $headings[] = 'Overall';
                }
            }
        }

        $grid = [];
        $grid[] = $headings;

        /* Then slap in some data: note we can perhaps merge this cubic complexity with the one that generated headings
         * TODO think about combining the cubic loops
         */
        foreach ($participantUsers as $participantUser) {
            $row = [sprintf(
                '%s, %s',
                $participantUser->getLastname(),
                $participantUser->getFirstname()
            ), $participantUser->getExternalId()];

            foreach ($assessmentData['gridData'] as $rubricInstanceId => $rubricInstanceData) {
                foreach ($rubricInstanceData['capabilities'] as $capabilityId => $capabilityData) {
                    foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                        /* A shortcut to marks
                         */
                        $markNode = $studentMarks[$participantUser->getId()]['gridData'][$assessment->getId()][$rubricInstanceId]['capabilities'][$capabilityId]['indicators'][$indicatorId][$this->markNode] ?? null;

                        if (null !== $markNode) {
                            /* Descriptor text then descriptor value in two consecutive cells
                             */
                            $row[] = $markNode['selectedDescriptorText'];

                            /* Descriptor values are optional
                             */
                            if (null !== $markNode['selectedDescriptorValue']) {
                                $row[] = round($markNode['selectedDescriptorValue'], 2);
                            } else {
                                $row[] = '';
                            }
                        } else {
                            /* This student doesn't have marks yet
                             */
                            $row[] = '';
                            $row[] = '';
                        }
                    }

                    /* Optionally, the capability mark goes here
                     */
                    if ($rubricInstanceData['isMarkPerCapability']) {
                        $markNode = $studentMarks[$participantUser->getId()]['gridData'][$assessment->getId()][$rubricInstanceId]['capabilities'][$capabilityId][$this->markNode] ?? null;

                        if (null !== $markNode) {
                            $row[] = $markNode['humanReadableMark'];
                        } else {
                            $row[] = '';
                        }
                    }
                }
            }

            $grid[] = $row;
        }

        return $grid;
    }


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    private function array2csv(array $array): string
    {
        $fp = fopen('php://temp', 'w');

        foreach ($array as $row) {
            fputcsv($fp, $row, escape: '');
        }

        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);

        return $contents;
    }
}