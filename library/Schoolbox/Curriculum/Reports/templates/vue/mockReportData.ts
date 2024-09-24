export const mockReportData = {
    data: {
        courses: [
            {
                id: 5,
                name: "Year 9 English",
                units: [
                    {
                        id: 108,
                        name: "Romeo and Juliet",
                    },
                    {
                        id: 109,
                        name: "Persuasion and the Media",
                    },
                    {
                        id: 110,
                        name: "To Kill a Mockingbird",
                    },
                ],
            },
            {
                id: 13,
                name: "Year 9 Humanities",
                units: [
                    {
                        id: 170,
                        name: "Geography",
                    },
                    {
                        id: 171,
                        name: "History",
                    },
                    {
                        id: 172,
                        name: "Civics and Citizenship",
                    },
                    {
                        id: 173,
                        name: "Economics and Business",
                    },
                ],
            },
        ],
        nodes: [
            {
                id: 3104,
                name: "Communication skills",
                code: "E-ATL-CO",
                usage: {
                    courses: [
                        {
                            id: 5,
                            name: "Year 9 English",
                            usage: {
                                course: { count: 1 },
                                rubric: { count: 2 },
                            },
                            units: [
                                {
                                    id: 108,
                                    name: "Romeo and Juliet",
                                    usage: {
                                        unit: { count: 1 },
                                        activity: { count: 4 },
                                        lessonPlan: { count: 1 },
                                        rubric: { count: 5 },
                                    },
                                },
                                {
                                    id: 109,
                                    name: "Persuasion and the Media",
                                    usage: {
                                        unit: { count: 2 },
                                        lessonPlan: { count: 2 },
                                    },
                                },
                                {
                                    id: 110,
                                    name: "To Kill a Mockingbird",
                                    usage: {
                                        unit: { count: 5 },
                                        activity: { count: 2 },
                                        rubric: { count: 5 },
                                    },
                                },
                            ],
                        },
                        {
                            id: 13,
                            name: "Year 9 Humanities",
                            units: [
                                {
                                    id: 170,
                                    name: "Geography",
                                    usage: {
                                        unit: { count: 2 },
                                        lessonPlan: { count: 3 },
                                        rubric: { count: 2 },
                                    },
                                },
                                {
                                    id: 171,
                                    name: "History",
                                    usage: {
                                        activity: { count: 2 },
                                        lessonPlan: { count: 1 },
                                        rubric: { count: 3 },
                                    },
                                },
                                {
                                    id: 172,
                                    name: "Civics and Citizenship",
                                    usage: {
                                        activity: { count: 2 },
                                        lessonPlan: { count: 3 },
                                        rubric: { count: 2 },
                                    },
                                },
                            ],
                        },
                    ],
                },
                children: [
                    {
                        id: 3105,
                        name: "Exchanging information",
                        code: "E-ATL-COEX",
                        usage: {
                            courses: [
                                {
                                    id: 5,
                                    name: "Year 9 English",
                                    units: [
                                        {
                                            id: 108,
                                            name: "Romeo and Juliet",
                                            usage: {
                                                unit: { count: 1 },
                                                activity: { count: 4 },
                                                lessonPlan: { count: 3 },
                                                rubric: { count: 2 },
                                            },
                                        },
                                        {
                                            id: 109,
                                            name: "Persuasion and the Media",
                                            usage: {
                                                unit: { count: 2 },
                                                activity: { count: 4 },
                                                rubric: { count: 2 },
                                            },
                                        },
                                        {
                                            id: 110,
                                            name: "To Kill a Mockingbird",
                                            usage: {
                                                unit: { count: 2 },
                                                activity: { count: 2 },
                                                lessonPlan: { count: 3 },
                                                rubric: { count: 3 },
                                            },
                                        },
                                    ],
                                },
                                {
                                    id: 13,
                                    name: "Year 9 Humanities",
                                    units: [
                                        {
                                            id: 170,
                                            name: "Geography",
                                            usage: {
                                                unit: { count: 2 },
                                                lessonPlan: { count: 3 },
                                                rubric: { count: 2 },
                                            },
                                        },
                                        {
                                            id: 171,
                                            name: "History",
                                            usage: {
                                                activity: { count: 2 },
                                                lessonPlan: { count: 1 },
                                                rubric: { count: 1 },
                                            },
                                        },
                                        {
                                            id: 172,
                                            name: "Civics and Citizenship",
                                            usage: {
                                                unit: { count: 4 },
                                                activity: { count: 2 },
                                                lessonPlan: { count: 3 },
                                                rubric: { count: 2 },
                                            },
                                        },
                                        {
                                            id: 173,
                                            name: "Economics and Business",
                                            usage: {
                                                activity: { count: 3 },
                                                lessonPlan: { count: 1 },
                                                rubric: { count: 1 },
                                            },
                                        },
                                    ],
                                },
                            ],
                        },
                        children: [
                            {
                                id: 3106,
                                name: "Listening - Listen to information",
                                code: "E-ATL-COEX-a",
                                usage: {
                                    courses: [
                                        {
                                            id: 13,
                                            name: "Year 9 Humanities",
                                            units: [
                                                {
                                                    id: 170,
                                                    name: "Geography",
                                                    usage: {
                                                        unit: { count: 2 },
                                                        lessonPlan: { count: 1 },
                                                    },
                                                },
                                                {
                                                    id: 171,
                                                    name: "History",
                                                    usage: {
                                                        activity: { count: 1 },
                                                        rubric: { count: 1 },
                                                    },
                                                },
                                                {
                                                    id: 172,
                                                    name: "Civics and Citizenship",
                                                    usage: {
                                                        unit: { count: 3 },
                                                        activity: { count: 1 },
                                                        lessonPlan: { count: 1 },
                                                    },
                                                },
                                                {
                                                    id: 173,
                                                    name: "Economics and Business",
                                                    usage: {
                                                        activity: { count: 2 },
                                                    },
                                                },
                                            ],
                                        },
                                    ],
                                },
                            },
                            {
                                id: 3107,
                                name: "Listening - Listen actively and respectfully to others’ ideas",
                                code: "E-ATL-COEX-b",
                                usage: {
                                    courses: [
                                        {
                                            id: 5,
                                            name: "Year 9 English",
                                            units: [
                                                {
                                                    id: 108,
                                                    name: "Romeo and Juliet",
                                                    usage: {
                                                        unit: { count: 1 },
                                                        activity: { count: 1 },
                                                        lessonPlan: { count: 2 },
                                                        rubric: { count: 2 },
                                                    },
                                                },
                                                {
                                                    id: 109,
                                                    name: "Persuasion and the Media",
                                                    usage: {
                                                        unit: { count: 2 },
                                                        activity: { count: 3 },
                                                        rubric: { count: 4 },
                                                    },
                                                },
                                                {
                                                    id: 110,
                                                    name: "To Kill a Mockingbird",
                                                    usage: {
                                                        unit: { count: 1 },
                                                        activity: { count: 2 },
                                                        lessonPlan: { count: 2 },
                                                        rubric: { count: 3 },
                                                    },
                                                },
                                            ],
                                        },
                                        {
                                            id: 13,
                                            name: "Year 9 Humanities",
                                            units: [
                                                {
                                                    id: 170,
                                                    name: "Geography",
                                                    usage: {
                                                        unit: { count: 2 },
                                                        lessonPlan: { count: 2 },
                                                        rubric: { count: 2 },
                                                    },
                                                },
                                                {
                                                    id: 171,
                                                    name: "History",
                                                    usage: {
                                                        activity: { count: 1 },
                                                        lessonPlan: { count: 1 },
                                                        rubric: { count: 2 },
                                                    },
                                                },
                                                {
                                                    id: 172,
                                                    name: "Civics and Citizenship",
                                                    usage: {
                                                        unit: { count: 2 },
                                                        activity: { count: 1 },
                                                        lessonPlan: { count: 2 },
                                                        rubric: { count: 2 },
                                                    },
                                                },
                                                {
                                                    id: 173,
                                                    name: "Economics and Business",
                                                    usage: {
                                                        activity: { count: 2 },
                                                        lessonPlan: { count: 1 },
                                                        rubric: { count: 1 },
                                                    },
                                                },
                                            ],
                                        },
                                    ],
                                },
                            },
                            {
                                id: 3107,
                                name: "Listening - Ask for clarifications",
                                code: "E-ATL-COEX-c",
                            },
                        ],
                    },
                ],
            },
        ],
    },
};
