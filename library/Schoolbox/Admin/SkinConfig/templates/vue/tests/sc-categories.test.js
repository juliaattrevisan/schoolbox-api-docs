import { mount } from "@vue/test-utils";
import ScCategories from "../sc-categories.vue";

function buildConfigs() {
    return {
        contrastTokens: {},
        toggle: {
            enabled: true,
            title: "Enable Sonar UI",
            description: null,
        },
        existing: {},
        sonar: {
            Colours: [
                {
                    name: null,
                    options: [
                        {
                            id: 101,
                            name: "skin_primary_colour",
                            value: "#0055aa",
                            title: "Primary Colour",
                            description: "Set the primary brand colour.",
                            customProperty: "--sbx-config-primary-colour",
                            contrast: null,
                            type: "colour",
                            selectOptions: [],
                            editable: true,
                            hidden: false,
                        },
                    ],
                },
                {
                    name: "Accent",
                    options: [
                        {
                            id: 102,
                            name: "skin_accent_colour",
                            value: "#ff6600",
                            title: "Accent Colour",
                            description: "Set the accent brand colour.",
                            customProperty: "--sbx-config-accent-colour",
                            contrast: null,
                            type: "colour",
                            selectOptions: [],
                            editable: true,
                            hidden: false,
                        },
                    ],
                },
            ],
            "Corner Radius": [
                {
                    name: null,
                    options: [
                        {
                            id: 99,
                            name: "skin_field_corner_radius",
                            value: "0.5rem",
                            title: "Field Corner Radius",
                            description: "Adjust the corner radius of form fields.",
                            customProperty: "--sbx-config-field-corner-radius",
                            contrast: null,
                            type: "select",
                            selectOptions: [
                                { name: "None", value: "0" },
                                { name: "Small", value: "0.25rem" },
                                { name: "Medium", value: "0.5rem" },
                                { name: "Large", value: "0.75rem" },
                            ],
                            editable: true,
                            hidden: false,
                        },
                    ],
                },
            ],
            "Drop Shadow": [
                {
                    name: null,
                    options: [
                        {
                            id: 100,
                            name: "skin_container_drop_shadow",
                            value: "var(--sbx-shadow-m)",
                            title: "Container Drop Shadow",
                            description: "Add a drop shadow to primary container elements.",
                            customProperty: "--sbx-config-container-drop-shadow",
                            contrast: null,
                            type: "select",
                            selectOptions: [
                                { name: "None", value: "none" },
                                { name: "Small", value: "var(--sbx-shadow-s)" },
                                { name: "Medium", value: "var(--sbx-shadow-m)" },
                                { name: "Large", value: "var(--sbx-shadow-l)" },
                            ],
                            editable: true,
                            hidden: false,
                        },
                    ],
                },
            ],
        },
    };
}

test("renders the Drop Shadow select options for Sonar configs", () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    expect(wrapper.text()).toContain("Drop Shadow");
    expect(wrapper.text()).toContain("Container Drop Shadow");

    const select = wrapper.get('select[name="skin_container_drop_shadow"]');
    expect(select.findAll("option").map((option) => option.text())).toEqual(["None", "Small", "Medium", "Large"]);
    expect(select.element.value).toBe("var(--sbx-shadow-m)");
});

test("renders the Corner Radius select options for Sonar configs", () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    expect(wrapper.text()).toContain("Corner Radius");
    expect(wrapper.text()).toContain("Field Corner Radius");

    const select = wrapper.get('select[name="skin_field_corner_radius"]');
    expect(select.findAll("option").map((option) => option.text())).toEqual(["None", "Small", "Medium", "Large"]);
    expect(select.element.value).toBe("0.5rem");
});

test("renders the category headings using the updated terminology", () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    expect(wrapper.findAll("h2").map((heading) => heading.text())).toEqual(["Colours", "Corner Radius", "Drop Shadow"]);
});

test("groups subcategories in one island per category with dividers between subcategories", () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    const categories = wrapper.findAll('[data-test="skin-config-category"]');
    expect(categories).toHaveLength(3);
    expect(categories[0].classes()).not.toContain("l-pad-m");

    const colourSubcategories = categories[0].findAll('[data-test^="skin-config-subcategory"]');
    expect(colourSubcategories).toHaveLength(2);
    expect(colourSubcategories[0].classes()).toContain("skin-config-subcategory--divided");
    expect(colourSubcategories[1].classes()).not.toContain("skin-config-subcategory--divided");
});

test("emits preview refresh when the Drop Shadow selection changes", async () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    await wrapper.get('select[name="skin_container_drop_shadow"]').setValue("var(--sbx-shadow-l)");

    expect(wrapper.emitted("preview-refresh")).toHaveLength(1);
});

test("emits preview refresh when the Corner Radius selection changes", async () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    await wrapper.get('select[name="skin_field_corner_radius"]').setValue("0.75rem");

    expect(wrapper.emitted("preview-refresh")).toHaveLength(1);
});

test("emits preview refresh when a colour changes", async () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    const input = wrapper.get('input[name="skin_primary_colour"]');
    input.element.value = "#ff6600";
    await input.trigger("input");

    expect(wrapper.emitted("preview-refresh")).toHaveLength(1);
});
