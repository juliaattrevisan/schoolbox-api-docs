test("no icon to be shown when name does not match", () => {
    const icon = document.createElement("sbx-icon");
    icon.setAttribute("name", "blah");
    document.body.appendChild(icon);
    const innerIcon = icon.shadowRoot.querySelector(`[data-test="sbx-icon"]`);
    expect(innerIcon.children.length).toBe(0);
});

test("icon to be shown when name matches", () => {
    const icon = document.createElement("sbx-icon");
    icon.setAttribute("name", "add");
    document.body.appendChild(icon);
    const innerIcon = icon.shadowRoot.querySelector(`[data-test="sbx-icon"]`);
    expect(innerIcon.children.length).toBe(1);
});
