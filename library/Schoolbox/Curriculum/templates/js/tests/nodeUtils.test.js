import { includeChildren, getOnlyEnabled } from "../nodeUtils";

// describe("includeChildren()", () => {
//     test("adds children", () => {
//         const node = {
//             id: 1,
//             name: "Node 1",
//             code: "C",
//             children: [
//                 { id: 2, name: "Node Child 1", code: "C1", enabled: true },
//                 { id: 3, name: "Node Child 2", code: "C2", enabled: true },
//             ],
//         };
//         const selectedNodes = [node, ...includeChildren(node.children)];
//         expect(selectedNodes).toHaveLength(3);
//     });
//     test("only adds children with code", () => {
//         const node = {
//             id: 1,
//             name: "Node 1",
//             code: "C",
//             children: [
//                 { id: 2, name: "Node Child 1", code: "C1", enabled: true },
//                 { id: 3, name: "Node Child 2", enabled: true },
//                 { id: 4, name: "Node Child 3", code: "C2", enabled: true },
//             ],
//         };
//         const selectedNodes = [node, ...includeChildren(node.children)];
//         expect(selectedNodes).toHaveLength(3);
//     });
//     test("only adds enabled children", () => {
//         const node = {
//             id: 1,
//             name: "Node 1",
//             code: "C",
//             children: [
//                 { id: 2, name: "Node Child 1", code: "C1", enabled: true },
//                 { id: 3, name: "Node Child 2", code: "C1", enabled: false },
//                 { id: 4, name: "Node Child 3", code: "C2", enabled: false },
//             ],
//         };
//         const selectedNodes = [node, ...includeChildren(node.children)];
//         expect(selectedNodes).toHaveLength(2);
//     });
//     test("manages children multi levels deep", () => {
//         const node = {
//             id: 1,
//             name: "Node 1",
//             code: "C",
//             children: [
//                 {
//                     id: 2,
//                     name: "Node Child 1",
//                     code: "C1",
//                     enabled: true,
//                     children: [
//                         {
//                             id: 5,
//                             name: "Node Child 1.1",
//                             code: "C1.1",
//                             enabled: true,
//                             children: [{ id: 6, name: "Node Child 1.1.1", code: "C1.1.1", enabled: true }],
//                         },
//                     ],
//                 },
//                 { id: 3, name: "Node Child 2", enabled: true },
//                 { id: 4, name: "Node Child 3", code: "C2", enabled: true },
//             ],
//         };
//         const selectedNodes = [node, ...includeChildren(node.children)];
//         expect(selectedNodes).toHaveLength(5);
//     });
//     test("can skip over children without codes to grandchildren", () => {
//         const node = {
//             id: 1,
//             name: "Node 1",
//             code: "C",
//             children: [
//                 {
//                     id: 2,
//                     name: "No Code Node Child 1",
//                     enabled: true,
//                     children: [
//                         {
//                             id: 5,
//                             name: "Node Child 1.1",
//                             code: "C1.1",
//                             enabled: true,
//                             children: [{ id: 6, name: "Node Child 1.1.1", code: "C1.1.1", enabled: true }],
//                         },
//                     ],
//                 },
//             ],
//         };
//         const selectedNodes = [node, ...includeChildren(node.children)];
//         expect(selectedNodes).toHaveLength(3);
//     });
// });

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
                ],
            },
            { id: 3, name: "Node 3", enabled: true, children: null },
        ];
        const onlyEnabledNodes = getOnlyEnabled(nodes);
        expect(onlyEnabledNodes[1].children).toHaveLength(1);
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
                        code: "C2.2",
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
        expect(onlyEnabledNodes[1].children[1].children).toHaveLength(1);
    });
    test("remove nodes if no children with codes are enabled", () => {
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
    test("remove deeper nodes if no children with codes are enabled", () => {
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
