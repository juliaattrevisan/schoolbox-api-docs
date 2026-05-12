<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Greenhouse;

use Alaress\Json\Decorator\LinkDecorator;
use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\ValidGridSet;
use Twig\Error\Error;

/**
 * The Greenhouse lets you experiment with twig templates and contents without
 * needing a backend dev to do it for you. You can also experiment with other
 * front-end stuff in an isolated manner
 */
class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Load $templateName
     *
     * @param string $templateName
     */
    public function getGreenhouse(string $templateName): void
    {
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        $currentAssessment = $assessmentDataSource->findByIdForReview(38);

        $rubricMapper = $this->getDi()->get('mapperFactory')->get(RubricDataSource::class);
        $rubricInstanceMapper = $this->getDi()->get('mapperFactory')->get(\Schoolbox\Assessment\Rubric\Instance\Mapper::class);
        $markTypeMapper = $this->getDi()->get('mapperFactory')->get(MarkTypeMapper::class);

        $post[11] = '{"level":[{"id":42,"name":"Beyond+Standard"},{"id":43,"name":"At+Standard"},{"id":44,"name":"Progressing+Toward+Standard"},{"id":45,"name":"Developing+Standard"},{"id":46,"name":"Not+Shown"}],"group":[{"id":19,"name":"Grammar","maxValue":4},{"id":20,"name":"Knowledge+and+Understanding+of+the+Text","maxValue":8},{"id":24,"name":"Understanding+Text+Structure+-+Essays","maxValue":14},{"id":257,"name":"Approaches+to+Learning","maxValue":null},{"id":17,"name":"Communication","maxValue":null},{"id":18,"name":"Context","maxValue":null},{"id":258,"name":"Essay+Requirements","maxValue":null},{"id":21,"name":"Oral+Presentation","maxValue":null},{"id":22,"name":"Text+Structure+-+Understanding+of+the+chosen+form.","maxValue":null},{"id":23,"name":"Understanding+of+persuasion","maxValue":null},{"id":25,"name":"Working+in+Groups","maxValue":null}],"criterion":{"34":{"id":34,"groupId":17,"name":"6.1"},"35":{"id":35,"groupId":17,"name":"6.1.2"},"36":{"id":36,"groupId":18,"name":"Understanding+of+Historical+Context+and+its+impacts+on+the+text"},"37":{"id":37,"groupId":19,"name":"Coherence","on":1},"38":{"id":38,"groupId":20,"name":"Knowledge+of+the+Text","on":1},"39":{"id":39,"groupId":20,"name":"Use+of+Evidence","on":1},"40":{"id":40,"groupId":21,"name":"Speaking"},"41":{"id":41,"groupId":22,"name":"Language+choices"},"42":{"id":42,"groupId":23,"name":"Analysis+of+persuasive+techniques"},"43":{"id":43,"groupId":23,"name":"Persuasive+language+choices"},"44":{"id":44,"groupId":24,"name":"Body","on":1},"45":{"id":45,"groupId":24,"name":"Conclusion","on":1},"46":{"id":46,"groupId":24,"name":"Development+of+ideas","on":1},"47":{"id":47,"groupId":24,"name":"Introduction","on":1},"48":{"id":48,"groupId":25,"name":"Communication"},"49":{"id":49,"groupId":25,"name":"Participation"},"728":{"id":728,"groupId":257,"name":"Engaged"},"729":{"id":729,"groupId":257,"name":"Persistent"},"730":{"id":730,"groupId":257,"name":"Reflective"},"731":{"id":731,"groupId":257,"name":"Self-directed"},"736":{"id":736,"groupId":258,"name":"Ideas"},"737":{"id":737,"groupId":258,"name":"Evidence"},"738":{"id":738,"groupId":258,"name":"Structure"},"739":{"id":739,"groupId":258,"name":"Language"}},"descriptor":{"34":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"35":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"36":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"37":[{"id":211,"text":"All+ideas+and+arguments+are+expressed+concisely+with+vocabulary+appropriate+to+the+context.","points":"4"},{"id":216,"text":"Sentences+are+well+constructed+and+are+linked+together+to+form+a+coherent+discussion.","points":"3"},{"id":223,"text":"Grammar+and+mechanics+of+sentences+is+mostly+correct.","points":"2"},{"id":230,"text":"Basic+idea+of+sentences+is+understood.","points":"1"},{"id":237,"text":"Not+Shown","points":"0"}],"38":[{"id":212,"text":"Demonstrates+an+understanding+of+the+complexity+of+the+text+through+elaboration,+insights+and+reflections+of+the+main+ideas+and+themes+relevant+to+the+essay+question.","points":"4"},{"id":217,"text":"Examines+how+relevant+themes+and+ideas+relate+to+essay+topic+through+examples+and+evidence.","points":"3"},{"id":224,"text":"Identifies+and+discusses+themes+and+ideas+relevant+to+essay+topic.","points":"2"},{"id":231,"text":"Refers+to+main+ideas+and+themes+of+text.","points":"1"},{"id":238,"text":"Not+Shown","points":"0"}],"39":[{"id":213,"text":"Examples+and+evidenced+are+linked+back+to+the+topic+and+the+writer’s+contention.","points":"4"},{"id":218,"text":"Examples+and+evidence+are+explained+and+justified.","points":"3"},{"id":225,"text":"Examples+and+evidence+are+used+to+support+the+writer’s+contention.","points":"2"},{"id":232,"text":"Examples+and+evidence+are+used.","points":"1"},{"id":239,"text":"Not+Shown","points":"0"}],"40":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"41":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"42":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"43":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"44":[{"id":214,"text":"Evidence+and+examples+are+used+to+justify+the+writer\'s+point+of+view+and+are+linked+to+the+topic.","points":"4"},{"id":220,"text":"Evidence+and+examples+are+used+to+support+main+idea+of+paragraph","points":"3"},{"id":227,"text":"Topic+sentences+outline+main+idea+being+discussed+in+paragraph","points":"2"},{"id":234,"text":"Paragraph+has+one+main+idea","points":"1"},{"id":241,"text":"Not+Shown","points":"0"}],"45":[{"id":"","text":"","points":""},{"id":221,"text":"Ideas+and+arguments+presented+in+essay+are+summarised+and+linked+to+contention.","points":"3"},{"id":228,"text":"Contention+is+restated.","points":"2"},{"id":235,"text":"Main+arguments+presented+in+essay+are+restated.","points":"1"},{"id":242,"text":"Not+Shown","points":"0"}],"46":[{"id":215,"text":"Ideas+are+evaluated+and+related+to+the+topic,+themes+and+contention+of+the+essay.","points":"4"},{"id":222,"text":"Ideas+between+paragraphs+are+linked+and+new+ideas+do+not+disrupt+the+flow+of+the+essay.","points":"3"},{"id":229,"text":"Ideas+are+discussed+and+linked+to+each+other.","points":"2"},{"id":236,"text":"Ideas+are+stated.","points":"1"},{"id":243,"text":"Not+Shown","points":"0"}],"47":[{"id":"","text":"","points":""},{"id":219,"text":"Introduction+summarises+main+ideas+and+examples+that+will+be+discussed+in+essay.+Does+not+retell+the+story.","points":"3"},{"id":226,"text":"Introduction+outlines+writer\'s+contention+for+essay","points":"2"},{"id":233,"text":"Introduction+restates+topic+of+essay","points":"1"},{"id":240,"text":"Not+Shown","points":"0"}],"48":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"49":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"728":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"729":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"730":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"731":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"736":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"737":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"738":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}],"739":[{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""},{"id":"","text":"","points":""}]},"title":"Essay+-+Final+Assessment","weight":100,"useMarkRange":false,"capabilityMarkType":"percent","capabilityGradeGroup":1}';

        $response = (new ValidGridSet(
            new LinkDecorator($this->routeGenerator, $this->authorizer),
            $rubricMapper,
            $rubricInstanceMapper,
            $markTypeMapper
        ))->isValid($post, $currentAssessment);

        new \Alaress_Probe($response);

        $this->response = $this->responseFromTemplate('@greenhouse/' . $templateName . '.twig', [
        ]);
    }
}
