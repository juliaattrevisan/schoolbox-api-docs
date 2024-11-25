import { moveArrayItem, isArrayOfNumbers } from "../arrayUtils";

describe("moveArrayItem()", () => {
    test("two to be at the end of the array", () => {
        expect(moveArrayItem(["one", "two", "three"], 1, 2)).toEqual(["one", "three", "two"]);
    });
    test("two to be at the end of the array", () => {
        expect(moveArrayItem(["one", "two", "three"], 2, 0)).toEqual(["three", "one", "two"]);
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
