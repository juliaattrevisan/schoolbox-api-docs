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
                            field: "Email",
                            displayAs: "Email Address",
                        },
                        {
                            field: "StudentYearLevelCoordinator",
                            displayAs: "Year Level Coorindator",
                        },
                    ],
                    // These are implicitly ordered by sequence any time front- and back-end
                    // exchange payload data
                    selected: [
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
                displayAs: "Appears on",
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
                displayAs: "Visible for",
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
