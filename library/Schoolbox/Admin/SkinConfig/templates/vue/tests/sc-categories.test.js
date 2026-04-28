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
            Roundness: [
                {
                    name: null,
                    options: [],
                },
            ],
            "Drop Shadow": [
                {
                    name: null,
                    options: [
                        {
                            id: 100,
                            name: "skin_box_drop_shadow",
                            value: "var(--sbx-shadow-m)",
                            title: "Box Drop Shadow",
                            description: "Add a drop shadow to primary container elements.",
                            customProperty: "--sbx-config-box-drop-shadow",
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
    expect(wrapper.text()).toContain("Box Drop Shadow");

    const select = wrapper.get('select[name="skin_box_drop_shadow"]');
    expect(select.findAll("option").map((option) => option.text())).toEqual(["None", "Small", "Medium", "Large"]);
    expect(select.element.value).toBe("var(--sbx-shadow-m)");
});

test("emits preview refresh when the Drop Shadow selection changes", async () => {
    const wrapper = mount(ScCategories, {
        props: {
            configs: buildConfigs(),
            colourErrors: {},
        },
    });

    await wrapper.get('select[name="skin_box_drop_shadow"]').setValue("var(--sbx-shadow-l)");

    expect(wrapper.emitted("preview-refresh")).toHaveLength(1);
});
