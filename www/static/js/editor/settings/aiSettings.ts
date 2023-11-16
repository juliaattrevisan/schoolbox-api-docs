
/**
 * Returns the settings for the AI assistant
 * @returns { object } - the font settings
 */
export function getAISettings() {
    return {
        aiAssistant: {
            apiUrl: '/openai',
            removeCommands: [
                'business',
                'legal',
                'journalism',
                'poetic',
            ],
            extraCommandGroups: [
                {
                    groupId: 'ageAppropriate',
                    groupLabel: 'Age Appropriate',
                    commands: [
                        {
                            id: 'junior',
                            label: 'Junior Students',
                            prompt: 'Rewrite this content using age appropriate language that a junior school student could easily understand.'
                        },
                        {
                            id: 'senior',
                            label: 'Senior Students',
                            prompt: 'Rewrite this content using age appropriate language that a senior school student could easily understand.'
                        },
                        {
                            id: 'teachers',
                            label: 'Teachers',
                            prompt: 'Rewrite this content using professional teacher language'
                        },
                    ]
                },
            ]
        }
    };
}
