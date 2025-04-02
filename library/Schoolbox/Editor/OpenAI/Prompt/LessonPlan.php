<?php

namespace Schoolbox\Editor\OpenAI\Prompt;

use Schoolbox\Curriculum\Node;
use Schoolbox\GenericCollection;

class LessonPlan
{
    /**
     * A prompt template stolen from SA-19690 comment thread.
     */
    private const PROMPT_TEMPLATE = "
        You are an expert [FOLDER] [YEAR LEVEL] teacher, proficient in creating engaging, well-developed 
        and effective lesson plans for your students. Your task is to come up with ideas for 
        lesson plans in our unit on [UNIT TOPIC]. The lesson plans should be [DURATION] minutes each and 
        should [MODE]. The lessons should focus on: [SPECIFIC UNIT TOPICS]. 
        Each lesson should be engaging and appropriate for [YEAR LEVEL] students. Each lesson should 
        [NODES]include: learning objectives; a list of key vocabulary; a 
        lesson outline that includes direct instruction, an engaging activity, and a closure with a 
        synthesis/summary of student learning; and an assessment of learning.
    ";

    private const PROMPT_TEMPLATE_NODES = 'be aligned to the following: [NODES]. 
        The lesson should ';


    /**
     * @param Node\Mapper $curriculumNodeMapper
     */
    public function __construct(private Node\Mapper $curriculumNodeMapper)
    {
    }


    /**
     * Generate a prompt using Luisa Words
     *
     * @param string $title
     * @param int $duration
     * @param string $folderTitle
     * @param string $unitTitle
     * @param string $yearLevel
     * @param array $curriculumNodeIds
     * @param bool $compounding
     * @return string
     */
    public function generate(
        string $title,
        int $duration,
        string $folderTitle,
        string $unitTitle,
        string $yearLevel,
        array $curriculumNodeIds,
        bool $compounding = false
    ): string {
        /* Specifically craft a node template section, if ones were specified and meaningful
         */
        $nodes = $this->curriculumNodeMapper->findByColumn('id', $curriculumNodeIds);

        $nodeTemplate = '';

        if ($nodes->count()) {
            $replacements = [
                '[NODES]' => $this->compileNodeTitles($nodes)
            ];

            $nodeTemplate = strtr(self::PROMPT_TEMPLATE_NODES, $replacements);
        }

        /* The rest of the prompt's keywords
         */
        $replacements = [
            '[FOLDER]' => $folderTitle,
            '[UNIT TOPIC]' => $unitTitle,
            '[DURATION]' => $duration,
            '[MODE]' => $compounding ? 'build on each other' : 'stand alone',
            '[YEAR LEVEL]' => $yearLevel,
            '[NODES]' => $nodeTemplate,
        ];

        return strtr(self::PROMPT_TEMPLATE, $replacements);
    }


    /**
     * Collect something human-readable via the node identifiers
     *
     * @param GenericCollection $nodes
     * @return string
     */
    private function compileNodeTitles(GenericCollection $nodes): string
    {
        /* A list of unique curriculum node titles
         */
        $titles = [];

        foreach ($nodes as $node) {
            $titles[] = $node->getName();
        }

        return implode('; ', array_filter(array_unique($titles)));
    }
}
