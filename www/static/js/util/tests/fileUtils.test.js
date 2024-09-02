/**
 * npm run test www/static/js/util/tests/fileUtils.test.js
 */

import { humanFileSize } from "../fileUtils";

beforeEach(() => {});

test("formats bytes, kilobyes, and megabytes with 3 decimal places", () => {
    expect(humanFileSize(512, 3)).toBe("512 B");
    expect(humanFileSize(1074, 3)).toBe("1.049 kB");
    expect(humanFileSize(1048576, 3)).toBe("1 MB");
});

test("formats bytes, kilobyes, and megabytes with 1 decimal place", () => {
    expect(humanFileSize(512, 1)).toBe("512 B");
    expect(humanFileSize(1024, 1)).toBe("1 kB");
    expect(humanFileSize(1084, 1)).toBe("1.1 kB");
    expect(humanFileSize(1048576, 1)).toBe("1 MB");
    expect(humanFileSize(1703326, 1)).toBe("1.6 MB");
});

test("formats megabytes and gigabytes with 2 decimal places", () => {
    expect(humanFileSize(524288000, 2)).toBe("500 MB");
    expect(humanFileSize(1873741824, 2)).toBe("1.75 GB");
    expect(humanFileSize(82610612736, 2)).toBe("76.94 GB");
});

test("formats gigabytes and terrabytes with no decimal places", () => {
    expect(humanFileSize(3221225472, 0)).toBe("3 GB");
    expect(humanFileSize(1099511627776, 0)).toBe("1 TB");
    expect(humanFileSize(2097152000000, 0)).toBe("2 TB");
});

test("formats the input correctly when a numeric string is submitted", () => {
    expect(humanFileSize("512")).toBe("512 B");
    expect(humanFileSize("1024")).toBe("1 kB");
    expect(humanFileSize("1048576")).toBe("1 MB");
});

test("returns the input when a non-int string is submitted", () => {
    expect(humanFileSize("Snek")).toBe("Snek");
    expect(humanFileSize("1.23 kB")).toBe("1.23 kB");
});

test("formats negative bytes with 3 decimal places", () => {
    expect(humanFileSize(-512, 3)).toBe("-512 B");
    expect(humanFileSize(-1074, 3)).toBe("-1.049 kB");
    expect(humanFileSize(-1048576, 3)).toBe("-1 MB");
});

test("returns '0 B' for 0 bytes with and without decimal places", () => {
    expect(humanFileSize(0)).toBe("0 B");
    expect(humanFileSize(-0, 1)).toBe("0 B");
    expect(humanFileSize(0, 0)).toBe("0 B");
});

test("handles very large numbers", () => {
    const bytes = Number.MAX_SAFE_INTEGER;
    expect(humanFileSize(bytes)).toBe("8 PB");
    expect(humanFileSize(bytes, 2)).toBe("8 PB");
});

test("handles decimal input values", () => {
    expect(humanFileSize(123.456)).toBe("123.46 B");
    expect(humanFileSize(987.654321, 2)).toBe("987.65 B");
    expect(humanFileSize(0.123456789, 3)).toBe("0.123 B");
});
