import { isRounded, getPercentage } from "../mathUtils";

describe("isRounded()", () => {
    test("1.23 to be false", () => {
        expect(isRounded(1.23)).toBe(false);
    });
    test("1.00 to be true", () => {
        expect(isRounded(1.0)).toBe(true);
    });
});

describe("getPercentage()", () => {
    test("18/26 to be 69.23%", () => {
        expect(getPercentage(18, 26)).toBe("69.23%");
    });
    test("5/10 to be 50%", () => {
        expect(getPercentage(5, 10)).toBe("50%");
    });
    test("0/26 to be 0%", () => {
        expect(getPercentage(0, 26)).toBe("0%");
    });
    test("26/26 to be 100%", () => {
        expect(getPercentage(26, 26)).toBe("100%");
    });
    test("0/0 to be invalid as both numbers are zero", () => {
        expect(getPercentage(0, 0)).toBe("-");
    });
    test("26/0 to be invalid as value is higher than max", () => {
        expect(getPercentage(26, 0)).toBe("-");
    });
    test("not passing numbers to be invalid", () => {
        expect(getPercentage("26", 0)).toBe("-");
    });
    test("not passing numbers to be invalid", () => {
        expect(getPercentage(26, "0")).toBe("-");
    });
    test("not passing numbers to be invalid", () => {
        expect(getPercentage("26", "0")).toBe("-");
    });
});
