import { includeChildren } from "../nodeUtils";

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
