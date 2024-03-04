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

test("check other elements", () => {
    const testString = `<p id="test-1">P</p><h2 id="test-2">H2</h2><h3 id="test-3">H3</h3><h4 id="test-4">H4</h4><h5 id="test-5">H4</h5><span id="test-6">Span</span>`;
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<p id="page-anchor-test-1">P</p><h2 id="page-anchor-test-2">H2</h2><h3 id="page-anchor-test-3">H3</h3><h4 id="page-anchor-test-4">H4</h4><h5 id="page-anchor-test-5">H5</h5><span id="page-anchor-test-6">Span</span>`;
    expect(addPrefixToElementIDs(divElement).innerHTML).toBe(expected);
});

test("check invalid element", () => {
    const testString = `<h6 id="test-1">H5</h6>`;
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<h6 id="test-1">H5</h6>`;
    expect(addPrefixToElementIDs(divElement).innerHTML).toBe(expected);
});
