import { getKeyValuePairs, getSearchString, getFormData } from "../queryUtils";

describe("getKeyValuePairs()", () => {
    test("converts simple object", () => {
        expect(
            getKeyValuePairs({
                query: "test",
                id: 1,
                isActive: true,
            }),
        ).toStrictEqual({
            query: "test",
            id: "1",
            isActive: "true",
        });
    });
    test("converts nested object", () => {
        expect(
            getKeyValuePairs({
                name: "John Doe",
                children: {
                    age: "5",
                    name: {
                        firstName: "Jane",
                        lastName: "Doe",
                    },
                },
            }),
        ).toStrictEqual({
            name: "John Doe",
            "children[age]": "5",
            "children[name][firstName]": "Jane",
            "children[name][lastName]": "Doe",
        });
    });
    test("converts number array", () => {
        expect(
            getKeyValuePairs({
                userIds: [13, 14],
            }),
        ).toStrictEqual({
            "userIds[0]": "13",
            "userIds[1]": "14",
        });
    });
    test("converts null values", () => {
        expect(
            getKeyValuePairs({
                filters: {
                    22: {
                        1: null,
                    },
                    24: null,
                },
            }),
        ).toStrictEqual({
            "filters[22][1]": "",
            "filters[24]": "",
        });
    });
});

describe("getSearchString()", () => {
    test("converts simple object", () => {
        expect(
            getSearchString({
                query: "test",
                id: 1,
                isActive: true,
            }),
        ).toBe("query=test&id=1&isActive=true");
    });
    test("converts nested object", () => {
        expect(
            getSearchString({
                name: "John Doe",
                children: {
                    age: "5",
                    name: {
                        firstName: "Jane",
                        lastName: "Doe",
                    },
                },
            }),
        ).toBe(
            "name=John+Doe&children%5Bage%5D=5&children%5Bname%5D%5BfirstName%5D=Jane&children%5Bname%5D%5BlastName%5D=Doe",
        );
    });
    test("converts number array", () => {
        expect(
            getSearchString({
                userIds: [13, 14],
            }),
        ).toBe("userIds%5B0%5D=13&userIds%5B1%5D=14");
    });
    test("converts null values", () => {
        expect(
            getSearchString({
                filters: {
                    22: {
                        1: null,
                    },
                    24: null,
                },
            }),
        ).toBe("filters%5B22%5D%5B1%5D=&filters%5B24%5D=");
    });
});

describe("getFormData()", () => {
    test("converts simple object", () => {
        const formData = getFormData({ query: "test", id: 1 });
        expect(formData.get("id")).toBe("1");
    });
    test("converts nested object", () => {
        const formData = getFormData({
            name: "John Doe",
            children: {
                age: "5",
                name: {
                    firstName: "Jane",
                    lastName: "Doe",
                },
            },
        });
        expect(formData.get("children[age]")).toBe("5");
    });
    test("converts number array", () => {
        const formData = getFormData({ userIds: [13, 14] });
        expect(formData.get("userIds[0]")).toBe("13");
    });
    test("converts null values", () => {
        const formData = getFormData({
            filters: {
                22: {
                    1: null,
                },
                24: null,
            },
        });
        expect(formData.get("filters[22][1]")).toBe("");
    });
});
