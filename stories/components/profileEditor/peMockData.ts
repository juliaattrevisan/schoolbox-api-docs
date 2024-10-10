import type { ProfileEditorPayload } from "../../../library/Schoolbox/Admin/Profile/Editor/templates/vue/types/profileEditorTypes";

export const mockData: ProfileEditorPayload = {
    data: {
        roleTypes: [
            {
                roleType: "student", // only ever "student", "staff", or "parent" in real life
                fields: {
                    // Available Fields
                    // These are in alphabetical order and represent what could be dragged onto
                    // the profile screen's palette
                    available: [
                        {
                            field: "MedicalAlert",
                            displayAs: "Medical Alert",
                            icon: "icon-alert",
                            css: "alert",
                        },
                        {
                            field: "House",
                            displayAs: "House",
                            icon: "icon-alert",
                            css: "alert",
                        },
                        {
                            field: "StudentYearLevelCoordinator",
                            displayAs: "Year Level Coordinator",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "Pronoun",
                            displayAs: "Pronoun",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "EnrolmentDate",
                            displayAs: "Enrolment Date",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "LegalAlert",
                            displayAs: "Legal Alert",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "HeadOfHouse",
                            displayAs: "Head of House",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "Tutor",
                            displayAs: "Tutor",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "Gender",
                            displayAs: "Gender",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "IBFlag",
                            displayAs: "IB Flag",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "Email",
                            displayAs: "Email Address",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "PastoralFlags",
                            displayAs: "Pastoral Flags",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "TutorGroup",
                            displayAs: "Tutor Group",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "Campus",
                            displayAs: "Campus",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "Role",
                            displayAs: "Role",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "ExternalID",
                            displayAs: "External ID",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "SpecialNeedsFlag",
                            displayAs: "Special Needs Flag",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "ILPFlag",
                            displayAs: "ILP Flag",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "StudiesCode",
                            displayAs: "Studies Code",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "ResidentialHouse",
                            displayAs: "Residential House",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "YearLevel",
                            displayAs: "Year Level",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "ESLFlag",
                            displayAs: "ESL Flag",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "StudentType",
                            displayAs: "Student Type",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "ClassTeacher",
                            displayAs: "Class Teacher",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "DateOfBirth",
                            displayAs: "Date of Birth",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "PositionTitle",
                            displayAs: "Position Title",
                            icon: null,
                            css: null,
                        },
                    ],
                    // These are implicitly ordered by sequence any time front- and back-end
                    // exchange payload data
                    selected: [
                        {
                            field: "Email",
                            displayAs: "Email Address",
                            icon: null,
                            css: null,
                            fieldSettings: {
                                appearsOn: [1, 2],
                                visibleFor: ["student", "staff"],
                            },
                        },
                        {
                            field: "StudentYearLevelCoordinator",
                            displayAs: "Year Level Coordinator",
                            icon: null,
                            css: null,
                            fieldSettings: {
                                appearsOn: [2],
                                visibleFor: ["student", "staff", "parent"],
                            },
                        },
                    ],
                    mandatory: [],
                },
            },
            {
                roleType: "staff", // only ever "student", "staff", or "parent" in real life
                fields: {
                    // Available Fields
                    // These are in alphabetical order and represent what could be dragged onto
                    // the profile screen's palette
                    available: [
                        {
                            field: "Email",
                            displayAs: "Email Address",
                            icon: null,
                            css: null,
                        },
                        {
                            field: "PositionTitle",
                            displayAs: "Position Title",
                            icon: null,
                            css: null,
                        },
                    ],
                    // These are implicitly ordered by sequence any time front- and back-end
                    // exchange payload data
                    selected: [
                        {
                            field: "PositionTitle",
                            displayAs: "Position Title",
                            icon: null,
                            css: null,
                            fieldSettings: {
                                appearsOn: [2],
                                visibleFor: ["student", "staff", "parent"],
                            },
                        },
                    ],
                    mandatory: [],
                },
            },
        ],
        fieldSettings: {
            appearsOn: {
                displayAs: "Appears On",
                tooltip: "Will appear on the selected campuses",
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
            visibleFor: {
                displayAs: "Visible For",
                tooltip: "Will appear for profile viewers in the selected role types",
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
        },
    },
};
