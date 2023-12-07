import { getGradient } from "../cssUtils";

describe("getGradient()", () => {
    test("current count of 0/10 returns gradient-0", () => {
        expect(getGradient(0, 10)).toBe("gradient-0");
    });
    test("current count of 10/10 returns gradient-10", () => {
        expect(getGradient(10, 10)).toBe("gradient-10");
    });
    test("current count of 6/10 returns gradient-10", () => {
        expect(getGradient(6, 10)).toBe("gradient-6");
    });
    test("current count of 7.8/10 returns gradient-10", () => {
        expect(getGradient(7.8, 10)).toBe("gradient-7");
    });
    test("current count of -5/20 returns gradient-0", () => {
        expect(getGradient(-5, 20)).toBe("gradient-0");
    });
    test("current count of 30/20 returns gradient-0", () => {
        expect(getGradient(30, 20)).toBe("gradient-10");
    });
});
