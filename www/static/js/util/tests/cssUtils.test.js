import { removeClasses, getGradient } from "../cssUtils";

describe("removeClasses()", () => {
    test("class is removed from element", () => {
        const div = document.createElement("div");
        div.classList.add("apple", "orange", "banana");
        removeClasses(div, ["apple"]);
        expect(div.classList.contains("apple")).toBe(false);
        expect(div.className).toBe("orange banana");
    });

    test("multiple classes are removed from element", () => {
        const div = document.createElement("div");
        div.classList.add("apple", "orange", "banana");
        removeClasses(div, ["apple", "orange"]);
        expect(div.classList.contains("apple")).toBe(false);
        expect(div.classList.contains("orange")).toBe(false);
        expect(div.className).toBe("banana");
    });

    test("classes are removed from any children", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        div.appendChild(firstChild);
        const secondChild = document.createElement("div");
        firstChild.appendChild(secondChild);
        const thirdChild = document.createElement("div");
        thirdChild.classList.add("apple", "orange", "banana");
        secondChild.appendChild(thirdChild);
        div.classList.add("apple", "orange", "banana");
        removeClasses(div, ["apple", "orange"]);
        expect(thirdChild.classList.contains("apple")).toBe(false);
        expect(thirdChild.classList.contains("orange")).toBe(false);
        expect(thirdChild.className).toBe("banana");
    });
});

describe("getGradient()", () => {
    beforeEach(() => {
        window.sbProps.gradeGradientEnabled = true;
    });

    test("current count of 0/10 returns gradient-0", () => {
        expect(getGradient(0, 10)).toBe("gradient-0");
    });
    test("current count of 10/10 returns gradient-10", () => {
        expect(getGradient(10, 10)).toBe("gradient-10");
    });
    test("current count of 6/10 returns gradient-6", () => {
        expect(getGradient(6, 10)).toBe("gradient-6");
    });
    test("current count of 7.8/10 returns gradient-7", () => {
        expect(getGradient(7.8, 10)).toBe("gradient-7");
    });
    test("current count of -5/20 returns gradient-0", () => {
        expect(getGradient(-5, 20)).toBe("gradient-0");
    });
    test("current count of 30/20 returns gradient-none", () => {
        expect(getGradient(30, 20)).toBe("gradient-none");
    });
    test("current count of 0/0 returns gradient-none", () => {
        expect(getGradient(0, 0)).toBe("gradient-none");
    });
    test("passing a string to return gradient-none", () => {
        expect(getGradient("0", 0)).toBe("gradient-none");
    });
    test("current count of 5/10 with gradient disabled returns gradient-none", () => {
        window.sbProps.gradeGradientEnabled = false;
        expect(getGradient(5, 10)).toBe("gradient-none");
    });
});
