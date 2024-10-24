import { getIndex, removeAllFromElement, hideAllChildren, addMarks } from "../domUtils";

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

describe("hideAllChildren()", () => {
    test("all children will be hidden in target element", () => {
        const div = document.createElement("div");
        const firstChild = document.createElement("div");
        firstChild.style.display = "block";
        const secondChild = document.createElement("div");
        secondChild.style.display = "block";
        const thirdChild = document.createElement("div");
        thirdChild.style.display = "block";
        div.appendChild(firstChild);
        div.appendChild(secondChild);
        div.appendChild(thirdChild);
        expect(firstChild.style.display).toBe("block");
        expect(thirdChild.style.display).toBe("block");
        hideAllChildren(div);
        expect(firstChild.style.display).toBe("none");
        expect(thirdChild.style.display).toBe("none");
    });
});

describe("addMarks()", () => {
    test("no marks added", () => {
        const marksAdded = addMarks("this is a test", ["apple", "pear"]);
        expect(marksAdded).toBe("this is a test");
    });

    test("single mark added", () => {
        const marksAdded = addMarks("this is a test", ["test"]);
        expect(marksAdded).toBe("this is a <mark>test</mark>");
    });

    test("single mark added", () => {
        const marksAdded = addMarks("this is a test", ["test", "test"]);
        expect(marksAdded).toBe("this is a <mark>test</mark>");
    });

    test("two marks added", () => {
        const marksAdded = addMarks("this is a test", ["this", "test"]);
        expect(marksAdded).toBe("<mark>this</mark> is a <mark>test</mark>");
    });

    test("tags to be escaped", () => {
        const marksAdded = addMarks("<a onmouseover='alert(document.domain)'>Mouse over this</a>", ["this"]);
        expect(marksAdded).toBe(
            "&lt;a onmouseover=&#39;alert(document.domain)&#39;&gt;Mouse over <mark>this</mark>&lt;/a&gt;",
        );
    });

    test("mark used in search words", () => {
        const marksAdded = addMarks("Ms Majorie Michael", ["michael", "mark"]);
        expect(marksAdded).toBe("Ms Majorie <mark>Michael</mark>");
    });

    test("part of mark also used in search words", () => {
        const marksAdded = addMarks("this is cafe del mar, isn't it nice", ["nice", "mar", "mark", "cafe"]);
        expect(marksAdded).toBe("this is <mark>cafe</mark> del <mark>mar</mark>, isn&#39;t it <mark>nice</mark>");
    });

    test("single mark added within words when allowPartialMatches is true", () => {
        const marksAdded = addMarks("Year 9 English", ["eng"], true);
        expect(marksAdded).toBe("Year 9 <mark>Eng</mark>lish");
    });

    test("two marks added within words when allowPartialMatches is true", () => {
        const marksAdded = addMarks("Year 9 English, Advanced English", ["eng"], true);
        expect(marksAdded).toBe("Year 9 <mark>Eng</mark>lish, Advanced <mark>Eng</mark>lish");
    });
});
