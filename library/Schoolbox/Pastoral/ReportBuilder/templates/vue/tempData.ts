export const payload = {
    data: {
        tableHeaders: [
            {
                key: "user", // The key will be passed to the backend to sort by this column
                title: "Name",
                columnType: "ColumnUser",
                isSortable: true,
            },
            {
                key: "total",
                title: "Total",
                columnType: "ColumnTotalRecordsAndPoints",
                isSortable: true,
            },
            {
                key: "subtype-22", // When a subtype, the id is of the subtype
                title: "Academic",
                subTitle: "Academic Honesty",
                columnType: "ColumnRecordsAndPoints",
                isSortable: true,
            },
            {
                key: "subtype-1",
                title: "Academic",
                subTitle: "Award/Achievement",
                columnType: "ColumnRecordsAndPoints",
                isSortable: true,
            },
            {
                key: "subtype-3",
                title: "Wellbeing",
                subTitle: "Award Achievement",
                columnType: "ColumnRecordsAndPoints",
                isSortable: true,
            },
            {
                key: "type-8", // When type with no subtype, the id is of the type
                title: "Reporting",
                columnType: "ColumnRecordsAndPoints",
                isSortable: true,
            },
        ],
        totalRows: [
            null, // No total for the user column
            { records: 45, points: 90 }, // Total across all users
            { records: 18, points: 36 }, // Academic > Academic Honesty total
            { records: 11, points: 22 }, // Academic > Award/Achievement total
            { records: 9, points: 18 }, // Wellbeing > Award Achievement total
            { records: 6, points: 12 }, // Reporting total
        ],
        rows: [
            [
                {
                    id: 1,
                    name: "Jana Reed",
                    externalId: 1001,
                    firstname: "Jana",
                    fullName: "Jana Reed",
                    givenName: "Jana",
                    lastname: "Reed",
                    preferredName: null,
                    title: "Ms",
                    pronoun: "she/her",
                    _links: {
                        avatar: "/portrait.php?id=65&size=square64",
                        profile: "/search/user/65",
                    },
                },
                { records: 11, points: 22 },
                { records: 5, points: 10 },
                { records: 3, points: 6 },
                { records: 2, points: 4 },
                { records: 1, points: 2 },
            ],
            [
                {
                    id: 2,
                    name: "John Doe",
                    externalId: 1002,
                    firstname: "John",
                    fullName: "John Doe",
                    givenName: "John",
                    lastname: "Doe",
                    preferredName: "Johnny",
                    title: "Mr",
                    pronoun: "he/him",
                    _links: {
                        avatar: "/portrait.php?id=21&size=square64",
                        profile: "/search/user/21",
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
                    id: 3,
                    name: "Jane Smith",
                    externalId: 1003,
                    firstname: "Jane",
                    fullName: "Jane Smith",
                    givenName: "Jane",
                    lastname: "Smith",
                    preferredName: null,
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
