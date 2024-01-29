import { getIndex, removeAllFromElement } from "../domUtils";

describe("getIndex()", () => {
    test("first child to return an index of 1", () => {
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

describe("removeAllFromElement()", () => {
    test("all children removed from targeted element", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        const secondChild = document.createElement("div");
        const thirdChild = document.createElement("div");
        div.appendChild(firstChild);
        div.appendChild(secondChild);
        div.appendChild(thirdChild);
        expect(div.children.length).toBe(3);
        removeAllFromElement(div, "div");
        expect(div.children.length).toBe(0);
    });
});
