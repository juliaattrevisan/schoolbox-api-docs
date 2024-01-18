import { getIndex, removeClasses } from "../domUtils";

describe("getIndex()", () => {
    test("frist child to return an index of 1", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        const secondChild = document.createElement("div");
        const thirdChild = document.createElement("div");
        div.appendChild(firstChild);
        div.appendChild(secondChild);
        div.appendChild(thirdChild);
        expect(getIndex(firstChild)).toBe(0);
    });

    test("third child to return an index of 2", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        const secondChild = document.createElement("div");
        const thirdChild = document.createElement("div");
        div.appendChild(firstChild);
        div.appendChild(secondChild);
        div.appendChild(thirdChild);
        expect(getIndex(thirdChild)).toBe(2);
    });

    test("third child to return an index of 2, disregarding any nested children", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        const firstChildInner = document.createElement("div");
        const secondChildInner = document.createElement("div");
        const secondChild = document.createElement("div");
        const thirdChild = document.createElement("div");
        firstChild.appendChild(firstChildInner);
        firstChild.appendChild(secondChildInner);
        div.appendChild(firstChild);
        div.appendChild(secondChild);
        div.appendChild(thirdChild);
        expect(getIndex(thirdChild)).toBe(2);
    });

    test("if child does not exist index should be -1", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        const secondChild = document.createElement("div");
        const thirdChild = document.createElement("div");
        div.appendChild(firstChild);
        div.appendChild(secondChild);
        expect(getIndex(thirdChild)).toBe(-1);
    });
});

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
