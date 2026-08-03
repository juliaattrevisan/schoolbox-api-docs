test("no icon to be shown when name does not match", () => {
    const icon = document.createElement("sbx-icon");
    icon.setAttribute("name", "blah");
    document.body.appendChild(icon);
    const innerIcon = icon.shadowRoot.querySelector(`[data-test="sbx-icon"] > slot`);
    expect(innerIcon.children.length).toBe(0);
});

test("icon to be shown when name matches", () => {
    const icon = document.createElement("sbx-icon");
    icon.setAttribute("name", "add");
    document.body.appendChild(icon);
    const innerIcon = icon.shadowRoot.querySelector(`[data-test="sbx-icon"] > slot`);
    expect(innerIcon.children.length).toBe(1);
});

test("Zenda icon renders with its supplied brand colours", () => {
    const icon = document.createElement("sbx-icon");
    icon.setAttribute("name", "zenda");
    document.body.appendChild(icon);

    const circles = icon.shadowRoot.querySelectorAll(`[data-test="sbx-icon"] > slot svg circle`);

    expect(Array.from(circles, (circle) => circle.getAttribute("fill"))).toEqual(["#ed5395", "#33b0ff", "#3ddad8"]);
});
