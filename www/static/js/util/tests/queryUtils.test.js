import { createKeyValuePairs } from "../queryUtils";

describe("createKeyValuePairs()", () => {
    const testObj = {
        name: "John Doe",
        children: {
            age: "5",
            name: {
                firstName: "Jane",
                lastName: "Doe",
            },
        },
    };
    test("converts test object to key value pairs", () => {
        expect(createKeyValuePairs(testObj)).toStrictEqual({
            name: "John Doe",
            "children[age]": "5",
            "children[name][firstName]": "Jane",
            "children[name][lastName]": "Doe",
        });
    });
});
