/* Update component definitions to fit in display box */
UPDATE homepage_comp SET description='Connect and feed Twitter posts directly on to your homepage. Search users, hashtags or keywords to create dynamic streams.' WHERE name='outputTwitter';
UPDATE homepage_comp SET description='Create tests and quizzes for your students. Results show in the class marksheet. Build resources using the school question bank.' WHERE method='outputAssess';

/* Update would need to look like this if run AFTER homepage refactoring
UPDATE component_definition SET description='Connect and feed Twitter posts directly on to your homepage. Search users, hashtags or keywords to create dynamic streams.' WHERE name='Twitter';
UPDATE component_definition SET description='Create tests and quizzes for your students. Results show in the class marksheet. Build resources using the school question bank.' WHERE name='Online Assessment';
*/