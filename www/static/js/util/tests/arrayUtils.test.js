import { moveArrayItem, isArrayOfNumbers } from "../arrayUtils";

describe("moveArrayItem()", () => {
    test("two to be at the end of the array", () => {
        expect(moveArrayItem(["one", "two", "three"], 1, 2)).toEqual(["one", "three", "two"]);
    });
    test("three to be at the start of the array", () => {
        expect(moveArrayItem(["one", "two", "three"], 2, 0)).toEqual(["three", "one", "two"]);
    });
    test("invalid index to not change the array", () => {
        expect(moveArrayItem(["one", "two", "three"], 5, 2)).toEqual(["one", "two", "three"]);
    });
    test("invalid index to not change the array", () => {
        expect(moveArrayItem(["one", "two", "three"], 1, 3)).toEqual(["one", "two", "three"]);
    });
});

describe("isArrayOfNumbers()", () => {
    test("array of numbers to return true", () => {
        expect(isArrayOfNumbers([0, 1, 2])).toBe(true);
    });
    test("not arry of numbers to return false", () => {
        expect(isArrayOfNumbers([0, 1, "2"])).toBe(false);
    });
    test("not array to return false", () => {
        expect(isArrayOfNumbers("2")).toBe(false);
    });
});
