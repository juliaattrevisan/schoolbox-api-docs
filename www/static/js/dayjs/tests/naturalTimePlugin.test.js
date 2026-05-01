import dayjs from "dayjs";
import naturalTimePlugin from "../naturalTimePlugin"; // adjust the path as needed

dayjs.extend(naturalTimePlugin);

describe("dayjsnaturalTimePlugin", () => {
    test("attaches the parseNatural method to dayjs", () => {
        expect(typeof dayjs.parseNatural).toBe("function");
    });

    test("parses 'in 5 minutes' correctly without a reference time", () => {
        const result = dayjs.parseNatural("in 5 minutes");
        const expected = dayjs().add(5, "minutes");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'in 5 minutes' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("in 5 minutes", now);
        const expected = now.add(5, "minutes");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'in 2 hours' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("in 2 hours", now);
        const expected = now.add(2, "hour");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'in 1 day' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("in 1 day", now);
        const expected = now.add(1, "day");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses '5 days ago' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("5 days ago", now);
        const expected = now.subtract(5, "day");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses '27/04/2025' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("27/04/2025", now);
        const expected = dayjs("2025-04-27");
        expect(result).not.toBeFalsy();
        expect(result.unix()).toBe(expected.unix());
    });

    test("parses 'an hour' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("an hour", now);
        const expected = now.add(1, "hour");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'an hour and two minutes' correctly", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("an hour and two minutes", now);
        const expected = now.add(1, "hour").add(2, "minutes");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("returns false for an unparsable input", () => {
        const now = dayjs();
        const result = dayjs.parseNatural("snake", now);
        expect(result).toBe(false);
    });
});
