import type { ProfileEditorPayload } from "./types/profilePayloadTypes";

export const mockData: ProfileEditorPayload = {
    data: {
        roleTypes: [
            {
                name: "student", // only ever "student", "staff", or "parent" in real life
                fields: {
                    // Available Fields
                    // These are in alphabetical order and represent what could be dragged onto
                    // the profile screen's palette
                    available: [
                        {
                            field: "MedicalAlert",
                            displayAs: "Medical Alert",
                        },
                        {
                            field: "House",
                            displayAs: "House",
                        },
                        {
                            field: "StudentYearLevelCoordinator",
                            displayAs: "Year Level Coordinator",
                        },
                        {
                            field: "Pronoun",
                            displayAs: "Pronoun",
                        },
                        {
                            field: "EnrolmentDate",
                            displayAs: "Enrolment Date",
                        },
                        {
                            field: "LegalAlert",
                            displayAs: "Legal Alert",
                        },
                        {
                            field: "HeadOfHouse",
                            displayAs: "Head of House",
                        },
                        {
                            field: "Tutor",
                            displayAs: "Tutor",
                        },
                        {
                            field: "Gender",
                            displayAs: "Gender",
                        },
                        {
                            field: "IBFlag",
                            displayAs: "IB Flag",
                        },
                        {
                            field: "Email",
                            displayAs: "Email Address",
                        },
                        {
                            field: "PastoralFlags",
                            displayAs: "Pastoral Flags",
                        },
                        {
                            field: "TutorGroup",
                            displayAs: "Tutor Group",
                        },
                        {
                            field: "Campus",
                            displayAs: "Campus",
                        },
                        {
                            field: "Role",
                            displayAs: "Role",
                        },
                        {
                            field: "ExternalID",
                            displayAs: "External ID",
                        },
                        {
                            field: "SpecialNeedsFlag",
                            displayAs: "Special Needs Flag",
                        },
                        {
                            field: "ILPFlag",
                            displayAs: "ILP Flag",
                        },
                        {
                            field: "StudiesCode",
                            displayAs: "Studies Code",
                        },
                        {
                            field: "ResidentialHouse",
                            displayAs: "Residential House",
                        },
                        {
                            field: "YearLevel",
                            displayAs: "Year Level",
                        },
                        {
                            field: "ESLFlag",
                            displayAs: "ESL Flag",
                        },
                        {
                            field: "StudentType",
                            displayAs: "Student Type",
                        },
                        {
                            field: "ClassTeacher",
                            displayAs: "Class Teacher",
                        },
                        {
                            field: "DateOfBirth",
                            displayAs: "Date of Birth",
                        },
                        {
                            field: "PositionTitle",
                            displayAs: "Position Title",
                        },
                    ],
                    // These are implicitly ordered by sequence any time front- and back-end
                    // exchange payload data
                    selected: [
                        {
                            field: "Email",
                            displayAs: "Email Address",
                            fieldSettings: {
                                appearsOn: [1, 2],
                                visibleFor: ["student", "staff"],
                            },
                        },
                        {
                            field: "StudentYearLevelCoordinator",
                            displayAs: "Year Level Coordinator",
                            fieldSettings: {
                                appearsOn: [2],
                                visibleFor: ["student", "staff", "parent"],
                            },
                        },
                    ],
                },
            },
            {
                name: "staff", // only ever "student", "staff", or "parent" in real life
                fields: {
                    // Available Fields
                    // These are in alphabetical order and represent what could be dragged onto
                    // the profile screen's palette
                    available: [
                        {
                            field: "Email",
                            displayAs: "Email Address",
                        },
                        {
                            field: "PositionTitle",
                            displayAs: "Position Title",
                        },
                    ],
                    // These are implicitly ordered by sequence any time front- and back-end
                    // exchange payload data
                    selected: [
                        {
                            field: "PositionTitle",
                            displayAs: "Position Title",
                            fieldSettings: {
                                appearsOn: [2],
                                visibleFor: ["student", "staff", "parent"],
                            },
                        },
                    ],
                },
            },
        ],
        fieldSettings: [
            {
                key: "appearsOn",
                displayAs: "Appears On",
                options: [
                    // A list of campus ids displayed as a list of checkboxes
                    {
                        type: "campus",
                        id: 1,
                        name: "Junior Campus",
                    },
                    {
                        type: "campus",
                        id: 2,
                        name: "Donny Secret Campus",
                    },
                    {
                        type: "role",
                        id: 3,
                        name: "Year 10 students",
                    },
                ],
            },
            {
                key: "visibleFor",
                displayAs: "Visible For",
                options: [
                    // These ids come from the hardcoded consts we already use in backend world
                    // displayed as a list of checkboxes
                    {
                        type: "roletype",
                        id: "student",
                        name: "Student",
                    },
                    {
                        type: "roletype",
                        id: "staff",
                        name: "Staff",
                    },
                    {
                        type: "roletype",
                        id: "parent",
                        name: "Parent",
                    },
                ],
            },
        ],
    },
};
