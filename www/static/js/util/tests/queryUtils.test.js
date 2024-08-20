import { createKeyValuePairs, createQueryString } from "../queryUtils";

const basicTestObj = {
    name: "John Doe",
    children: {
        age: "5",
        name: {
            firstName: "Jane",
            lastName: "Doe",
        },
    },
};

const biggerTestObject = {
    filters: {
        22: {
            1: "0",
            2: "0",
            3: "1",
            4: "0",
            5: "0",
            6: "0",
            7: "0",
            8: "0",
            9: "0",
        },
        23: null,
        24: null,
        26: {
            1: "0",
            2: "0",
            3: "0",
            4: "0",
            5: "0",
            6: "0",
            7: "0",
            8: "0",
            9: "0",
            10: "0",
            11: "0",
            12: "0",
            13: "0",
            14: "0",
            18: "0",
            19: "0",
            none: "0",
        },
    },
};

const basicTestArray = {
    userIds: [13, 14],
};

describe("createKeyValuePairs()", () => {
    test("converts test object to key value pairs", () => {
        expect(createKeyValuePairs(basicTestObj)).toStrictEqual({
            name: "John Doe",
            "children[age]": "5",
            "children[name][firstName]": "Jane",
            "children[name][lastName]": "Doe",
        });
    });
    test("converts basic test array to key value pairs", () => {
        expect(createKeyValuePairs(basicTestArray)).toStrictEqual({
            "userIds[0]": "13",
            "userIds[1]": "14",
        });
    });
});

describe("createQueryString()", () => {
    test("converts test object to query string using key value pairs", () => {
        expect(createQueryString(basicTestObj)).toBe(
            "name=John+Doe&children%5Bage%5D=5&children%5Bname%5D%5BfirstName%5D=Jane&children%5Bname%5D%5BlastName%5D=Doe",
        );
    });
    test("converts test object with null to empty strings", () => {
        expect(createQueryString(biggerTestObject)).toBe(
            "filters%5B22%5D%5B1%5D=0&filters%5B22%5D%5B2%5D=0&filters%5B22%5D%5B3%5D=1&filters%5B22%5D%5B4%5D=0&filters%5B22%5D%5B5%5D=0&filters%5B22%5D%5B6%5D=0&filters%5B22%5D%5B7%5D=0&filters%5B22%5D%5B8%5D=0&filters%5B22%5D%5B9%5D=0&filters%5B23%5D=&filters%5B24%5D=&filters%5B26%5D%5B1%5D=0&filters%5B26%5D%5B2%5D=0&filters%5B26%5D%5B3%5D=0&filters%5B26%5D%5B4%5D=0&filters%5B26%5D%5B5%5D=0&filters%5B26%5D%5B6%5D=0&filters%5B26%5D%5B7%5D=0&filters%5B26%5D%5B8%5D=0&filters%5B26%5D%5B9%5D=0&filters%5B26%5D%5B10%5D=0&filters%5B26%5D%5B11%5D=0&filters%5B26%5D%5B12%5D=0&filters%5B26%5D%5B13%5D=0&filters%5B26%5D%5B14%5D=0&filters%5B26%5D%5B18%5D=0&filters%5B26%5D%5B19%5D=0&filters%5B26%5D%5Bnone%5D=0",
        );
    });
});
