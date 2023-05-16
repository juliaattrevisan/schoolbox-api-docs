/**
 * npm run test www/static/js/editor/settings/tests/tokensSettings.test.js
 */

import { wrapTokens } from "../tokensSettings";

beforeEach(() => {});

test("single token", () => {
    const testString = `<p>[firstname] did very well.</p>`;
    const config = { tokenList: ["[firstname]"] };
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<p><span class="mention" data-mention="[firstname]">[firstname]</span> did very well.</p>`;
    expect(wrapTokens(divElement, config).innerHTML).toBe(expected);
});

test("multiple of the same token", () => {
    const testString = `<p>[firstname] did very well. You should be proud of [firstname].</p>`;
    const config = { tokenList: ["[firstname]"] };
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<p><span class="mention" data-mention="[firstname]">[firstname]</span> did very well. You should be proud of <span class="mention" data-mention="[firstname]">[firstname]</span>.</p>`;
    expect(wrapTokens(divElement, config).innerHTML).toBe(expected);
});

test("various different tokens", () => {
    const testString = `
    <table>
        <tr>
            <td>[fullname]</td>
        </tr>
        <tr>
            <td>[year]</td>
        </tr>
        <tr>
            <td>[house]</td>
        </tr>
        <tr>
            <td>[year]</td>
        </tr>
    </table>`;
    const config = { tokenList: ["[fullname]", "[year]", "[house]"] };
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `
    <table>
        <tr>
            <td><span class="mention" data-mention="[fullname]">[fullname]</span></td>
        </tr>
        <tr>
            <td><span class="mention" data-mention="[year]">[year]</span></td>
        </tr>
        <tr>
            <td><span class="mention" data-mention="[house]">[house]</span></td>
        </tr>
        <tr>
            <td><span class="mention" data-mention="[year]">[year]</span></td>
        </tr>
    </table>`;
    expect(wrapTokens(divElement, config).innerHTML).toBe(expected);
});

test("does not wrap already wrapped tokens", () => {
    const testString = `<p><span class="mention" data-mention="[firstname]">[firstname]</span> did very well.</p>`;
    const config = { tokenList: ["[firstname]"] };
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<p><span class="mention" data-mention="[firstname]">[firstname]</span> did very well.</p>`;
    expect(wrapTokens(divElement, config).innerHTML).toBe(expected);
});

test("only wraps tokens not already wrapped", () => {
    const testString = `<p>[firstname] did very well. You should be proud of <span class="mention" data-mention="[lastname]">[lastname]</span>.</p>`;
    const config = { tokenList: ["[firstname]", "[lastname]"] };
    const divElement = document.createElement("div");
    divElement.innerHTML = testString;
    const expected = `<p><span class="mention" data-mention="[firstname]">[firstname]</span> did very well. You should be proud of <span class="mention" data-mention="[lastname]">[lastname]</span>.</p>`;
    expect(wrapTokens(divElement, config).innerHTML).toBe(expected);
});
