import { scrollToCurrentHashTarget } from "../hashScroll";

describe("scrollToCurrentHashTarget()", () => {
    afterEach(() => {
        document.body.innerHTML = "";
        window.history.pushState({}, "", "/");
    });

    test("scrolls to the current hash target inside the masonry layout", () => {
        document.body.innerHTML = `
            <div id="component-layout">
                <div id="component-title-123"></div>
            </div>
        `;

        const target = document.getElementById("component-title-123");
        target.scrollIntoView = jest.fn();
        window.history.pushState({}, "", "/homepage/1#component-title-123");

        scrollToCurrentHashTarget();

        expect(target.scrollIntoView).toHaveBeenCalledWith({ block: "start" });
    });

    test("does nothing when there is no hash", () => {
        document.body.innerHTML = `
            <div id="component-layout">
                <div id="component-title-123"></div>
            </div>
        `;

        const target = document.getElementById("component-title-123");
        target.scrollIntoView = jest.fn();

        scrollToCurrentHashTarget();

        expect(target.scrollIntoView).not.toHaveBeenCalled();
    });

    test("does nothing when the hash contains malformed percent encoding", () => {
        document.body.innerHTML = `
            <div id="component-layout">
                <div id="component-title-123"></div>
            </div>
        `;

        const target = document.getElementById("component-title-123");
        target.scrollIntoView = jest.fn();
        window.history.pushState({}, "", "/homepage/1#%");

        expect(() => scrollToCurrentHashTarget()).not.toThrow();
        expect(target.scrollIntoView).not.toHaveBeenCalled();
    });

    test("does not scroll to a hash target outside the masonry layout", () => {
        document.body.innerHTML = `
            <div id="component-layout"></div>
            <div id="component-title-123"></div>
        `;

        const target = document.getElementById("component-title-123");
        target.scrollIntoView = jest.fn();
        window.history.pushState({}, "", "/homepage/1#component-title-123");

        scrollToCurrentHashTarget();

        expect(target.scrollIntoView).not.toHaveBeenCalled();
    });
});
