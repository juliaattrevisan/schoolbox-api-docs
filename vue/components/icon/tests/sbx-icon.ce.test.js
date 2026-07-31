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

test("all built-in icons use the standard viewBox without transforms", () => {
    const source = fs.readFileSync(path.resolve(__dirname, "../sbx-icon.ce.vue"), "utf8");
    const svgOpeningTags = source.match(/<svg\b[^>]*>/g) || [];

    expect(svgOpeningTags.length).toBeGreaterThan(0);
    svgOpeningTags.forEach((tag) => {
        expect(tag).toContain('viewBox="0 0 32 32"');
    });
    expect(source).not.toMatch(/\btransform=/);
});
