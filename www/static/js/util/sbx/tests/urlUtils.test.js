import { isNewUrl } from "../urlUtils";

describe("isNewUrl", () => {
    test("should return true when pathnames are different", () => {
        const oldUrl = "https://example.com/old-path";
        const newUrl = "https://example.com/new-path";
        expect(isNewUrl(oldUrl, newUrl)).toBe(true);
    });

    test("should return false when only query parameters differ", () => {
        const oldUrl = "https://example.com/same-path?param1=value1";
        const newUrl = "https://example.com/same-path?param2=value2";
        expect(isNewUrl(oldUrl, newUrl)).toBe(false);
    });

    test("should return true when hash fragments are different", () => {
        const oldUrl = "https://example.com/path#section1";
        const newUrl = "https://example.com/path#section2";
        expect(isNewUrl(oldUrl, newUrl)).toBe(true);
    });

    test("should return true when URL parsing fails", () => {
        const oldUrl = "invalid-url";
        const newUrl = "also-invalid";
        expect(isNewUrl(oldUrl, newUrl)).toBe(true);
    });
});
