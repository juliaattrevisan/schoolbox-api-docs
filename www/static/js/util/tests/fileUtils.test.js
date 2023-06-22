/**
* npm run test www/static/js/util/tests/fileUtils.test.js
*/

import { filesize } from "filesize";
import { humanFileSize } from "../fileUtils";

beforeEach(() => {});

test("formats bytes, kilobyes, and megabytes with 3 decimal places", () => {
    expect(humanFileSize(512, 3)).toBe(filesize(512, {round: 3}));
    expect(humanFileSize(1074, 3)).toBe(filesize(1074, {round: 3}));
    expect(humanFileSize(1048576, 3)).toBe(filesize(1048576, {round: 3}));
});

test("formats bytes, kilobyes, and megabytes with 1 decimal place", () => {
    expect(humanFileSize(512, 1)).toBe(filesize(512, {round: 1}));
    expect(humanFileSize(1024, 1)).toBe(filesize(1024, {round: 1}));
    expect(humanFileSize(1048576, 1)).toBe(filesize(1048576, {round: 1}));
});

test("formats megabytes and gigabytes with 2 decimal places", () => {
    expect(humanFileSize(524288000, 2)).toBe(filesize(524288000, {round: 2}));
    expect(humanFileSize(1073741824, 2)).toBe(filesize(1073741824, {round: 2}));
    expect(humanFileSize(1610612736, 2)).toBe(filesize(1610612736, {round: 2}));
});

test("formats gigabytes and terrabytes with no decimal places", () => {
    expect(humanFileSize(3221225472, 0)).toBe(filesize(3221225472, {round: 0}));
    expect(humanFileSize(1099511627776, 0)).toBe(filesize(1099511627776, {round: 0}));
    expect(humanFileSize(2097152000000, 0)).toBe(filesize(2097152000000, {round: 0}));
});

test("formats the input when an int string is submitted", () => {
    expect(humanFileSize("512")).toBe(filesize("512"));
    expect(humanFileSize("1024")).toBe(filesize("1024"));
    expect(humanFileSize("1048576")).toBe(filesize("1048576"));
});

test("returns the input when a non-int string is submitted", () => {
    expect(humanFileSize("Snek")).toBe("Snek");
    expect(humanFileSize("1.23 kB")).toBe("1.23 kB");
});

test("formats negative bytes with 3 decimal places", () => {
    expect(humanFileSize(-512, 3)).toBe(filesize(-512, {round: 3}));
    expect(humanFileSize(-1024, 3)).toBe(filesize(-1024, {round: 3}));
    expect(humanFileSize(-1048576, 3)).toBe(filesize(-1048576, {round: 3}));
});

test("returns '0 B' for 0 bytes with and without decimal places", () => {
    expect(humanFileSize(0)).toBe(filesize(0));
    expect(humanFileSize(0, 2)).toBe(filesize(0));
    expect(humanFileSize(0, 0)).toBe(filesize(0));
});

test("handles very large numbers", () => {
    const bytes = Number.MAX_SAFE_INTEGER;
    expect(humanFileSize(bytes)).toBe(filesize(bytes));
    expect(humanFileSize(bytes, 2)).toBe(filesize(bytes));
});

test("handles decimal input values", () => {
    expect(humanFileSize(123.456)).toBe(filesize(123.456, {round: 2}));
    expect(humanFileSize(987.654321, 2)).toBe(filesize(987.654321, {round: 2}));
    expect(humanFileSize(0.123456789, 3)).toBe(filesize(0.123456789, {round: 3}));
});
