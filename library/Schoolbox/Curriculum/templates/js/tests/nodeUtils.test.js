import { includeChildren } from "../nodeUtils";

describe("includeChildren()", () => {
    test("adds children", () => {
        const node = {
            id: 1,
            name: "Node 1",
            children: [
                { id: 2, name: "Node Child 1", code: "C1" },
                { id: 3, name: "Node Child 2", code: "C2" },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(3);
    });
    test("only adds children with code", () => {
        const node = {
            id: 1,
            name: "Node 1",
            children: [
                { id: 2, name: "Node Child 1", code: "C1" },
                { id: 3, name: "Node Child 2" },
                { id: 4, name: "Node Child 3", code: "C2" },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(3);
    });
    test("manages children multi levels deep", () => {
        const node = {
            id: 1,
            name: "Node 1",
            children: [
                {
                    id: 2,
                    name: "Node Child 1",
                    code: "C1",
                    children: [
                        {
                            id: 5,
                            name: "Node Child 1.1",
                            code: "C1.1",
                            children: [{ id: 6, name: "Node Child 1.1.1", code: "C1.1.1" }],
                        },
                    ],
                },
                { id: 3, name: "Node Child 2" },
                { id: 4, name: "Node Child 3", code: "C2" },
            ],
        };
        const selectedNodes = [node, ...includeChildren(node.children)];
        expect(selectedNodes).toHaveLength(5);
    });
});
