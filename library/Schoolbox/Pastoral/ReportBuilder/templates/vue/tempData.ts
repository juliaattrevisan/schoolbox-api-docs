export const payload = {
    data: {
        tableHeaders: [
            {
                key: "user", // The key will be passed to the backend to sort by this column
                title: "Name",
                type: "User",
                isSortable: true,
            },
            {
                key: "total",
                title: "Total",
                type: "RecordsAndPoints",
                isSortable: true,
            },
            {
                key: "subtype-22", // When a subtype, the id is of the subtype
                title: "Academic",
                subTitle: "Academic Honesty",
                type: "RecordsAndPoints",
                isSortable: true,
            },
            {
                key: "subtype-1",
                title: "Academic",
                subTitle: "Award/Achievement",
                type: "RecordsAndPoints",
                isSortable: true,
            },
            {
                key: "subtype-3",
                title: "Wellbeing",
                subTitle: "Award Achievement",
                type: "RecordsAndPoints",
                isSortable: true,
            },
            {
                key: "type-8", // When type with no subtype, the id is of the type
                title: "Reporting",
                type: "RecordsAndPoints",
                isSortable: true,
            },
        ],
        totalRow: [
            "Total", // No total for the user column
            { records: 45, points: 90 }, // Total across all users
            { records: 18, points: 36 }, // Academic > Academic Honesty total
            { records: 11, points: 22 }, // Academic > Award/Achievement total
            { records: 9, points: 18 }, // Wellbeing > Award Achievement total
            { records: 6, points: 12 }, // Reporting total
        ],
        rows: [
            [
                {
                    id: 20,
                    name: "Moshe Appleton",
                    externalId: 1003,
                    firstname: "Moshe",
                    fullName: "Moshe Appleton",
                    givenName: "Moshe",
                    lastname: "Appleton",
                    title: null,
                    pronoun: null,
                    _links: {
                        avatar: "/portrait.php?id=20&size=square64",
                        profile: "/search/user/20",
                    },
                },
                { records: 18, points: 36 },
                { records: 6, points: 12 },
                { records: 5, points: 10 },
                { records: 4, points: 8 },
                { records: 3, points: 6 },
            ],
            [
                {
                    id: 17,
                    name: "Mario Izzo",
                    firstname: "Mario",
                    fullName: "Mario Izzo",
                    givenName: "Mario",
                    lastname: "Izzo",
                    preferredName: "Moe",
                    title: "Mr",
                    pronoun: "he/him",
                    _links: {
                        avatar: "/portrait.php?id=17&size=square64",
                        profile: "/search/user/17",
                    },
                },
                { records: 16, points: 32 },
                { records: 7, points: 14 },
                { records: 4, points: 8 },
                { records: 3, points: 6 },
                { records: 2, points: 4 },
            ],
            [
                {
                    id: 13,
                    name: "Jana Reed",
                    firstname: "Jana",
                    fullName: "Jana Reed",
                    givenName: "Jana",
                    lastname: "Reed",
                    preferredName: null,
                    title: "Ms",
                    pronoun: "she/her",
                    _links: {
                        avatar: "/portrait.php?id=13&size=square64",
                        profile: "/search/user/13",
                    },
                },
                { records: 11, points: 22 },
                { records: 5, points: 10 },
                { records: 3, points: 6 },
                { records: 2, points: 4 },
                { records: 1, points: 2 },
            ],
        ],
    },
    metadata: {
        // standard metadata structure used to determine whether more data is available
        count: 20,
        cursor: {
            current: null,
            next: null,
        },
    },
};
