import { includeChildren, getOnlyEnabled } from "../nodeUtils";

describe("includeChildren()", () => {
    test("adds children", () => {
        const node = {
            id: 1,
            name: "Node 1",
            code: "C",
            children: [
                { id: 2, name: "Node Child 1", code: "C1", enabled: true },
                { id: 3, name: "Node Child 2", code: "C2", enabled: true },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(3);
    });
    test("only adds children with code", () => {
        const node = {
            id: 1,
            name: "Node 1",
            code: "C",
            children: [
                { id: 2, name: "Node Child 1", code: "C1", enabled: true },
                { id: 3, name: "Node Child 2", enabled: true },
                { id: 4, name: "Node Child 3", code: "C2", enabled: true },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(3);
    });
    test("only adds enabled children", () => {
        const node = {
            id: 1,
            name: "Node 1",
            code: "C",
            children: [
                { id: 2, name: "Node Child 1", code: "C1", enabled: true },
                { id: 3, name: "Node Child 2", code: "C1", enabled: false },
                { id: 4, name: "Node Child 3", code: "C2", enabled: false },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(2);
    });
    test("manages children multi levels deep", () => {
        const node = {
            id: 1,
            name: "Node 1",
            code: "C",
            children: [
                {
                    id: 2,
                    name: "Node Child 1",
                    code: "C1",
                    enabled: true,
                    children: [
                        {
                            id: 5,
                            name: "Node Child 1.1",
                            code: "C1.1",
                            enabled: true,
                            children: [{ id: 6, name: "Node Child 1.1.1", code: "C1.1.1", enabled: true }],
                        },
                    ],
                },
                { id: 3, name: "Node Child 2", enabled: true },
                { id: 4, name: "Node Child 3", code: "C2", enabled: true },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(5);
    });
    test("can skip over children without codes to grandchildren", () => {
        const node = {
            id: 1,
            name: "Node 1",
            code: "C",
            children: [
                {
                    id: 2,
                    name: "No Code Node Child 1",
                    enabled: true,
                    children: [
                        {
                            id: 5,
                            name: "Node Child 1.1",
                            code: "C1.1",
                            enabled: true,
                            children: [{ id: 6, name: "Node Child 1.1.1", code: "C1.1.1", enabled: true }],
                        },
                    ],
                },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(3);
    });
});

describe("getOnlyEnabled()", () => {
    test("check top level nodes", () => {
        const nodes = [
            { id: 1, name: "Node 1", enabled: true, children: null },
            { id: 2, name: "Node 2", enabled: false, children: null },
            { id: 3, name: "Node 3", enabled: true, children: null },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes).toHaveLength(2);
    });
    test("removes children not enabled", () => {
        const nodes = [
            { id: 1, name: "Node 1", enabled: true, children: null },
            {
                id: 2,
                name: "Node 2",
                enabled: true,
                children: [
                    { id: 4, name: "Node Child 2.1", code: "C2.1", enabled: true, children: null },
                    { id: 5, name: "Node Child 2.2", code: "C2.2", enabled: false, children: null },
                    { id: 6, name: "Node Child 2.3", code: "C2.3", enabled: true, children: null },
                ],
            },
            { id: 3, name: "Node 3", enabled: true, children: null },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes[1].children).toHaveLength(2);
    });
    test("removes deeper children not enabled", () => {
        const nodes = [
            { id: 1, name: "Node 1", enabled: true, children: null },
            {
                id: 2,
                name: "Node 2",
                enabled: true,
                children: [
                    { id: 4, name: "Node Child 2.1", code: "C2.1", enabled: true, children: null },
                    {
                        id: 5,
                        name: "Node Child 2.2",
                        enabled: true,
                        children: [
                            {
                                id: 7,
                                name: "Node Child 2.2.1",
                                code: "C2.2.1",
                                enabled: true,
                                children: [
                                    { id: 11, name: "Node Child", code: "C2.2.1.1", enabled: true, children: null },
                                    { id: 12, name: "Node Child", code: "C2.2.1.2", enabled: true, children: null },
                                    { id: 13, name: "Node Child", code: "C2.2.1.3", enabled: true, children: null },
                                    { id: 14, name: "Node Child", code: "C2.2.1.4", enabled: false, children: null },
                                ],
                            },
                            {
                                id: 8,
                                name: "Node Child 2.2.2",
                                enabled: true,
                                children: null,
                            },
                        ],
                    },
                ],
            },
            { id: 3, name: "Node 3", enabled: true, children: null },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes[1].children[1].children[0].children).toHaveLength(3);
    });
    test("removes deeper children not enabled with empty children arrays", () => {
        const nodes = [
            {
                id: 3631,
                name: "Australian Curriculum (v9) - Achievement Standards",
                children: [
                    {
                        id: 3632,
                        name: "English",
                        children: [
                            {
                                id: 3747,
                                name: "Year 9",
                                children: [
                                    {
                                        id: 3748,
                                        name: "Students interact with others, and listen to and create spoken and multimodal texts including literary texts.",
                                        code: "AC9EAS99",
                                        children: [],
                                        enabled: true,
                                    },
                                    {
                                        id: 3749,
                                        name: "With a range of purposes and for audiences, they discuss and expand on ideas, shaping meaning and providing substantiation.",
                                        code: "AC9EAS100",
                                        children: [],
                                        enabled: true,
                                    },
                                    {
                                        id: 3750,
                                        name: "Students select and experiment with text structures to organise and develop ideas.",
                                        code: "AC9EAS101",
                                        children: [],
                                        enabled: true,
                                    },
                                    {
                                        id: 3751,
                                        name: "Students select and experiment with language features including literary devices, and experiment with multimodal features and features of voice.",
                                        code: "AC9EAS102",
                                        children: [],
                                        enabled: true,
                                    },
                                    {
                                        id: 3754,
                                        name: "Students analyse the effects of text structures, and language features including literary devices, intertextual references, and multimodal features.",
                                        code: "AC9EAS105",
                                        children: [],
                                        enabled: false,
                                    },
                                ],
                                enabled: true,
                            },
                        ],
                        enabled: true,
                    },
                ],
                enabled: true,
            },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes[0].children[0].children[0].children).toHaveLength(4);
    });
    test("remove nodes if no direct children are enabled", () => {
        const nodes = [
            { id: 1, name: "Node 1", enabled: true, children: null },
            {
                id: 2,
                name: "Node 2",
                code: "C2",
                enabled: true,
                children: [
                    { id: 4, name: "Node Child 2.1", code: "C2.1", enabled: false, children: null },
                    { id: 5, name: "Node Child 2.2", code: "C2.2", enabled: false, children: null },
                ],
            },
            { id: 3, name: "Node 3", enabled: true, children: null },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes).toHaveLength(2);
    });
    test("remove nodes if no deeper children are enabled", () => {
        const nodes = [
            { id: 1, name: "Node 1", enabled: true, children: null },
            {
                id: 2,
                name: "Node 2",
                enabled: true,
                children: [
                    { id: 4, name: "Node Child 2.1", enabled: false, children: null },
                    {
                        id: 5,
                        name: "Node Child 2.2",
                        enabled: true,
                        children: [
                            {
                                id: 7,
                                name: "Node Child 2.2.1",
                                code: "C2.2.1",
                                enabled: false,
                                children: null,
                            },
                            {
                                id: 8,
                                name: "Node Child 2.2.2",
                                code: "C2.2.2",
                                enabled: false,
                                children: null,
                            },
                        ],
                    },
                ],
            },
            { id: 3, name: "Node 3", enabled: true, children: null },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes).toHaveLength(2);
    });
});
