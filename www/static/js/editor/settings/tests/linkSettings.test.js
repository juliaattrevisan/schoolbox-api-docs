/**
 * npm run test www/static/js/editor/settings/tests/linkSettings.test.js
 */

import { addPrefixToElementIDs } from "../linkSettings";

test("adds prefix", () => {
    const testString = `<div id="test">Hey there</div>`;
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<div id="page-anchor-test">Hey there</div>`;
    expect(addPrefixToElementIDs(divElement).innerHTML).toBe(expected);
});

test("only those with id attribute", () => {
    const testString = `<div>Hey there</div>`;
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<div>Hey there</div>`;
    expect(addPrefixToElementIDs(divElement).innerHTML).toBe(expected);
});

test("adds multiple prefix", () => {
    const testString = `<div id="test">Hey there</div><h2 id="test-2">Hey there again</h2>`;
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<div id="page-anchor-test">Hey there</div><h2 id="page-anchor-test-2">Hey there again</h2>`;
    expect(addPrefixToElementIDs(divElement).innerHTML).toBe(expected);
});

test("only those without the prefix already", () => {
    const testString = `<div id="page-anchor-test">Hey there</div>`;
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<div id="page-anchor-test">Hey there</div>`;
    expect(addPrefixToElementIDs(divElement).innerHTML).toBe(expected);
});
