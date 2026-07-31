import dayjs from "dayjs";
import naturalTimePlugin from "../naturalTimePlugin"; // adjust the path as needed

dayjs.extend(naturalTimePlugin);

describe("dayjsnaturalTimePlugin", () => {
    const dateTimeFormat = "YYYY-MM-DD HH:mm:ss";
    const referenceTime = dayjs("2026-01-15T12:00:00");

    afterEach(() => {
        jest.useRealTimers();
    });

    test("attaches the parseNatural method to dayjs", () => {
        expect(typeof dayjs.parseNatural).toBe("function");
    });

    test("parses 'in 5 minutes' correctly without a reference time", () => {
        jest.useFakeTimers();
        jest.setSystemTime(referenceTime.toDate());

        const result = dayjs.parseNatural("in 5 minutes");
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-15 12:05:00");
    });

    test("parses 'in 5 minutes' correctly", () => {
        const result = dayjs.parseNatural("in 5 minutes", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-15 12:05:00");
    });

    test("parses 'in 2 hours' correctly", () => {
        const result = dayjs.parseNatural("in 2 hours", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-15 14:00:00");
    });

    test("parses 'in 1 day' correctly", () => {
        const result = dayjs.parseNatural("in 1 day", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-16 12:00:00");
    });

    test("parses '5 days ago' correctly", () => {
        const result = dayjs.parseNatural("5 days ago", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-10 12:00:00");
    });

    test("parses '27/04/2025' correctly", () => {
        const result = dayjs.parseNatural("27/04/2025", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format("YYYY-MM-DD")).toBe("2025-04-27");
    });

    test("parses 'an hour' correctly", () => {
        const result = dayjs.parseNatural("an hour", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-15 13:00:00");
    });

    test("parses 'an hour and two minutes' correctly", () => {
        const result = dayjs.parseNatural("an hour and two minutes", referenceTime);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2026-01-15 13:02:00");
    });

    test("normalizes 'in 1 year' from a leap day", () => {
        const now = dayjs("2028-02-29T12:00:00");
        const result = dayjs.parseNatural("in 1 year", now);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2029-03-01 12:00:00");
    });

    test("normalizes '3 years ago' from a leap day", () => {
        const now = dayjs("2028-02-29T12:00:00");
        const result = dayjs.parseNatural("3 years ago", now);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2025-03-01 12:00:00");
    });

    test("normalizes '4 years 2 months and 7 days' from a month-end date", () => {
        const now = dayjs("2026-07-31T12:00:00");
        const result = dayjs.parseNatural("4 years 2 months and 7 days", now);
        expect(result).not.toBeFalsy();
        expect(result.format(dateTimeFormat)).toBe("2030-10-08 12:00:00");
    });

    test("returns false for an unparsable input", () => {
        const result = dayjs.parseNatural("snake", referenceTime);
        expect(result).toBe(false);
    });
});
