import { isAbortError } from "../errorUtils";

describe("isAbortError()", () => {
    test("null error to be false", () => {
        const error = null;
        expect(isAbortError(error)).toBe(false);
    });
    test("undefined error to be false", () => {
        const error = undefined;
        expect(isAbortError(error)).toBe(false);
    });
    test("empty object to be false", () => {
        const error = {};
        expect(isAbortError(error)).toBe(false);
    });
    test("error with message to be false", () => {
        const error = { message: "This is an error" };
        expect(isAbortError(error)).toBe(false);
    });
    test("error with name to be false", () => {
        const error = { name: "AbortError" };
        expect(isAbortError(error)).toBe(true);
    });
    test("error with 'signal is aborted without reason' message to be true", () => {
        const error = { message: "signal is aborted without reason" };
        expect(isAbortError(error)).toBe(true);
    });
});
