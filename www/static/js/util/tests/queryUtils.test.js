import { createKeyValuePairs, createQueryString } from "../queryUtils";

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

describe("createKeyValuePairs()", () => {
    test("converts test object to key value pairs", () => {
        expect(createKeyValuePairs(testObj)).toStrictEqual({
            name: "John Doe",
            "children[age]": "5",
            "children[name][firstName]": "Jane",
            "children[name][lastName]": "Doe",
        });
    });
});

describe("createQueryString()", () => {
    test("converts test object to query string using key value pairs", () => {
        expect(createQueryString(testObj)).toBe(
            "John+Doe=name&5=children%5Bage%5D&Jane=children%5Bname%5D%5BfirstName%5D&Doe=children%5Bname%5D%5BlastName%5D",
        );
    });
});
