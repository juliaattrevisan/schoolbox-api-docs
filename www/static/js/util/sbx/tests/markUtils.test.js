import { getLetterGrade } from "../markUtils";
import { letterGradeSet } from "./letterGradeItems";

describe("getLetterGrade()", () => {
    test("45/100 to be an C-", () => {
        expect(getLetterGrade(45, 100, letterGradeSet)).toBe("C-");
    });
    test("45/100 to be an C-", () => {
        expect(getLetterGrade(55, 68, letterGradeSet)).toBe("B+");
    });
    test("0/50 to be an E-", () => {
        expect(getLetterGrade(0, 50, letterGradeSet)).toBe("E-");
    });
    test("77/77 to be an A+", () => {
        expect(getLetterGrade(77, 77, letterGradeSet)).toBe("A+");
    });
    test("100/50 to be invalid, as value is higher than total", () => {
        expect(getLetterGrade(100, 50, letterGradeSet)).toBe("-");
    });
    test("not passing numbers to be invalid", () => {
        expect(getLetterGrade("100", 50, letterGradeSet)).toBe("-");
    });
    test("not passing numbers to be invalid", () => {
        expect(getLetterGrade(100, "50", letterGradeSet)).toBe("-");
    });
    test("not passing numbers to be invalid", () => {
        expect(getLetterGrade("100", "50", letterGradeSet)).toBe("-");
    });
    test("passing an empty letterGradeSet to be invalid", () => {
        expect(getLetterGrade(45, 100, [])).toBe("-");
    });
    const invalidLetterGradeSet = [
        { grade: "A", min: 80, max: 100 },
        { grade: "B", min: 60, max: 79 },
        { grade: "C", min: 40, max: 59 },
        { grade: "D", min: 20, max: 39 },
    ];
    const anotherInvalidLetterGradeSet = [
        { caption: "A", lowerBound: 91.11 },
        { caption: "B", lowerBound: 77.77 },
        { caption: "C" },
        { caption: "D", lowerBound: 37.77 },
    ];
    test("passing an invalid letterGradeSet to be invalid", () => {
        expect(getLetterGrade(45, 100, anotherInvalidLetterGradeSet)).toBe("-");
    });
});
