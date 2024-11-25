import { isArrayOfNumbers } from "../arrayUtils";

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
