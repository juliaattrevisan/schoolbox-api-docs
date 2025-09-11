import { getReturnUrl, isSignificantUrlChange } from "../urlUtils";

describe("isSignificantUrlChange()", () => {
    test("should return true when pathnames are different", () => {
        const oldUrl = "https://example.com/old-path";
        const newUrl = "https://example.com/new-path";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(true);
    });

    test("should return true when hash fragments are different", () => {
        const oldUrl = "https://example.com/path#section1";
        const newUrl = "https://example.com/path#section2";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(true);
    });

    test("should return true when query parameters differ", () => {
        const oldUrl = "https://example.com/path?filter=students";
        const newUrl = "https://example.com/path?filter=teachers";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(true);
    });

    test("should return false when only ignored parameters differ", () => {
        const oldUrl = "https://example.com/path?filter=students&cursor=abc123";
        const newUrl = "https://example.com/path?filter=students&cursor=def456";
        expect(isSignificantUrlChange(oldUrl, newUrl, ["cursor"])).toBe(false);
    });

    test("should return true when non-ignored parameters differ", () => {
        const oldUrl = "https://example.com/path?filter=students&cursor=abc123";
        const newUrl = "https://example.com/path?filter=teachers&cursor=abc123";
        expect(isSignificantUrlChange(oldUrl, newUrl, ["cursor"])).toBe(true);
    });

    test("should ignore multiple parameters when specified", () => {
        const oldUrl = "https://example.com/path?filter=students&cursor=abc&page=1&timestamp=123";
        const newUrl = "https://example.com/path?filter=students&cursor=def&page=2&timestamp=456";
        expect(isSignificantUrlChange(oldUrl, newUrl, ["cursor", "page", "timestamp"])).toBe(false);
    });

    test("should return false when URLs are identical", () => {
        const url = "https://example.com/path?param=value";
        expect(isSignificantUrlChange(url, url)).toBe(false);
    });

    test("should return false when URLs differ only by parameter order", () => {
        const oldUrl = "https://example.com/path?a=1&b=2";
        const newUrl = "https://example.com/path?b=2&a=1";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(false);
    });

    test("should return true when parameter is added", () => {
        const oldUrl = "https://example.com/path?filter=students";
        const newUrl = "https://example.com/path?filter=students&search=test";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(true);
    });

    test("should return true when parameter is removed", () => {
        const oldUrl = "https://example.com/path?filter=students&search=test";
        const newUrl = "https://example.com/path?filter=students";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(true);
    });

    test("should return false when ignored parameter is added", () => {
        const oldUrl = "https://example.com/path?filter=students";
        const newUrl = "https://example.com/path?filter=students&cursor=abc123";
        expect(isSignificantUrlChange(oldUrl, newUrl, ["cursor"])).toBe(false);
    });

    test("should return true when ignored parameter is removed", () => {
        const oldUrl = "https://example.com/path?filter=students&cursor=abc123";
        const newUrl = "https://example.com/path?filter=students";
        expect(isSignificantUrlChange(oldUrl, newUrl, ["cursor"])).toBe(true);
    });

    test("should handle empty query strings", () => {
        const oldUrl = "https://example.com/path";
        const newUrl = "https://example.com/path?";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(false);
    });

    test("should return true for invalid URLs", () => {
        const oldUrl = "invalid-url";
        const newUrl = "also-invalid";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(true);
    });

    test("should handle relative URLs", () => {
        const oldUrl = "/path?filter=students&cursor=abc";
        const newUrl = "/path?filter=students&cursor=def";
        expect(isSignificantUrlChange(oldUrl, newUrl, ["cursor"])).toBe(false);
    });

    test("should handle URLs with different protocols", () => {
        const oldUrl = "http://example.com/path";
        const newUrl = "https://example.com/path";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(false);
    });

    test("should handle URLs with different hosts", () => {
        const oldUrl = "https://old.example.com/path";
        const newUrl = "https://new.example.com/path";
        expect(isSignificantUrlChange(oldUrl, newUrl)).toBe(false);
    });
});
