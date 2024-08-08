import { getLetterGrade, getLowerBound } from "../markUtils";
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
    test("8/14 to be a C", () => {
        expect(getLetterGrade(8, 14, letterGradeSet)).toBe("C");
    });
    test("77/77 to be an A+", () => {
        expect(getLetterGrade(77, 77, letterGradeSet)).toBe("A+");
    });
    test("100/50 to be undefined, as value is higher than total", () => {
        expect(getLetterGrade(100, 50, letterGradeSet)).toBe(undefined);
    });
    test("not passing numbers to be undefined", () => {
        expect(getLetterGrade("100", 50, letterGradeSet)).toBe(undefined);
    });
    test("not passing numbers to be undefined", () => {
        expect(getLetterGrade(100, "50", letterGradeSet)).toBe(undefined);
    });
    test("not passing numbers to be undefined", () => {
        expect(getLetterGrade("100", "50", letterGradeSet)).toBe(undefined);
    });
    test("passing an empty letterGradeSet to be undefined", () => {
        expect(getLetterGrade(45, 100, [])).toBe(undefined);
    });
    const undefinedLetterGradeSet = [
        { grade: "A", min: 80, max: 100 },
        { grade: "B", min: 60, max: 79 },
        { grade: "C", min: 40, max: 59 },
        { grade: "D", min: 20, max: 39 },
    ];
    test("passing an undefined letterGradeSet to be undefined", () => {
        expect(getLetterGrade(45, 100, undefinedLetterGradeSet)).toBe(undefined);
    });
    const anotherInvalidLetterGradeSet = [
        { caption: "A", lowerBound: 91.11 },
        { caption: "B", lowerBound: 77.77 },
        { caption: "C" },
        { caption: "D", lowerBound: 37.77 },
    ];
    test("passing an undefined letterGradeSet to be undefined", () => {
        expect(getLetterGrade(45, 100, anotherInvalidLetterGradeSet)).toBe(undefined);
    });
});

describe("getLowerBound()", () => {
    test("C- to return 44.44", () => {
        expect(getLowerBound("C-", letterGradeSet)).toBe(44.44);
    });
    test("Z to return undefined", () => {
        expect(getLowerBound("Z", letterGradeSet)).toBe(undefined);
    });
    test("not passing a string for the caption to return undefined", () => {
        expect(getLowerBound(0, letterGradeSet)).toBe(undefined);
    });
    test("passing an empty letterGradeSet to return undefined", () => {
        expect(getLowerBound("A+", [])).toBe(undefined);
    });
    const undefinedLetterGradeSet = [
        { grade: "A", min: 80, max: 100 },
        { grade: "B", min: 60, max: 79 },
        { grade: "C", min: 40, max: 59 },
        { grade: "D", min: 20, max: 39 },
    ];
    test("passing an undefined letterGradeSet to return undefined", () => {
        expect(getLowerBound("A+", undefinedLetterGradeSet)).toBe(undefined);
    });
    const anotherInvalidLetterGradeSet = [
        { caption: "A", lowerBound: 91.11 },
        { caption: "B", lowerBound: 77.77 },
        { caption: "C" },
        { caption: "D", lowerBound: 37.77 },
    ];
    test("passing an undefined letterGradeSet to return undefined", () => {
        expect(getLowerBound("A+", anotherInvalidLetterGradeSet)).toBe(undefined);
    });
});
