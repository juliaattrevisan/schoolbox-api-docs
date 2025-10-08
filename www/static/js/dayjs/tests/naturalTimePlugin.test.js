import dayjs from "dayjs";
import naturalTimePlugin from "../naturalTimePlugin"; // adjust the path as needed

dayjs.extend(naturalTimePlugin);

describe("dayjsnaturalTimePlugin", () => {
    test("attaches the parseNatural method to dayjs", () => {
        expect(typeof dayjs.parseNatural).toBe("function");
    });

    test("parses 'in 5 minutes' correctly", () => {
        const result = dayjs.parseNatural("in 5 minutes");
        const expected = dayjs().add(5, "minutes");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'in 2 hours' correctly", () => {
        const result = dayjs.parseNatural("in 2 hours");
        const expected = dayjs().add(2, "hour");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'in 1 day' correctly", () => {
        const result = dayjs.parseNatural("in 1 day");
        const expected = dayjs().add(1, "day");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses '5 days ago' correctly", () => {
        const result = dayjs.parseNatural("5 days ago");
        const expected = dayjs().subtract(5, "day");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses '27/04/2025' correctly", () => {
        const result = dayjs.parseNatural("27/04/2025");
        const expected = dayjs("2025-04-27");
        expect(result).not.toBeFalsy();
        expect(result.unix()).toBe(expected.unix());
    });

    test("parses 'an hour' correctly", () => {
        const result = dayjs.parseNatural("an hour");
        const expected = dayjs().add(1, "hour");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("parses 'an hour and two minutes' correctly", () => {
        const result = dayjs.parseNatural("an hour and two minutes");
        const expected = dayjs().add(1, "hour").add(2, "minutes");
        expect(result).not.toBeFalsy();
        expect(Math.abs(result.unix() - expected.unix())).toBeLessThanOrEqual(1);
    });

    test("returns false for an unparsable input", () => {
        const result = dayjs.parseNatural("snake");
        expect(result).toBe(false);
    });
});
