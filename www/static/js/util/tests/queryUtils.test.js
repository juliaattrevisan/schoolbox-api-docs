import { getKeyValuePairs, getSearchString, getFormData } from "../queryUtils";

const simpleObject = {
    query: "test",
    id: 1,
    isActive: true,
    plan: null,
    planName: undefined,
};

const nestedObject = {
    name: "John Doe",
    address: {
        street: {
            number: 5,
            name: "Main St",
        },
        state: "Vic",
    },
    children: [
        {
            age: 5,
            name: {
                firstName: "Jane",
                lastName: "Doe",
            },
        },
    ],
};

const numberArray = {
    userIds: [13, 14],
};

describe("getKeyValuePairs()", () => {
    test("converts simple object", () => {
        expect(getKeyValuePairs(simpleObject)).toStrictEqual({
            query: "test",
            id: "1",
            isActive: "true",
            plan: "",
            planName: "",
        });
    });
    test("converts nested object", () => {
        expect(getKeyValuePairs(nestedObject)).toStrictEqual({
            name: "John Doe",
            "address[street][number]": "5",
            "address[street][name]": "Main St",
            "address[state]": "Vic",
            "children[0][age]": "5",
            "children[0][name][firstName]": "Jane",
            "children[0][name][lastName]": "Doe",
        });
    });
    test("converts number array", () => {
        expect(getKeyValuePairs(numberArray)).toStrictEqual({
            "userIds[0]": "13",
            "userIds[1]": "14",
        });
    });
});

describe("getSearchString()", () => {
    test("converts simple object", () => {
        expect(getSearchString(simpleObject)).toBe("query=test&id=1&isActive=true&plan=&planName=");
    });
    test("converts nested object", () => {
        expect(getSearchString(nestedObject)).toBe(
            "name=John+Doe&address%5Bstreet%5D%5Bnumber%5D=5&address%5Bstreet%5D%5Bname%5D=Main+St&address%5Bstate%5D=Vic&children%5B0%5D%5Bage%5D=5&children%5B0%5D%5Bname%5D%5BfirstName%5D=Jane&children%5B0%5D%5Bname%5D%5BlastName%5D=Doe",
        );
    });
    test("converts array", () => {
        expect(getSearchString(numberArray)).toBe("userIds%5B0%5D=13&userIds%5B1%5D=14");
    });
    test("converts empty object", () => {
        expect(getSearchString({})).toBe("");
    });
    test("encodes html tags", () => {
        expect(getSearchString({ query: "<script>alert('hi')</script>" })).toBe(
            "query=%3Cscript%3Ealert%28%27hi%27%29%3C%2Fscript%3E",
        );
    });
    test("overwrites existing params", () => {
        expect(getSearchString({ query: "test", query: "new" })).toBe("query=new");
    });
});

describe("getFormData()", () => {
    test("converts simple object", () => {
        const formData = getFormData(simpleObject);
        expect(formData.get("id")).toBe("1");
    });
    test("converts nested object", () => {
        const formData = getFormData(nestedObject);
        expect(formData.get("address[street][name]")).toBe("Main St");
    });
    test("converts number array", () => {
        const formData = getFormData(numberArray);
        expect(formData.get("userIds[0]")).toBe("13");
    });
});
