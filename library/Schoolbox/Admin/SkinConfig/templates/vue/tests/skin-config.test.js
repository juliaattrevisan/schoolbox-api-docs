import { mount } from "@vue/test-utils";
import SkinConfig from "../skin-config.vue";

function buildColourOption(overrides = {}) {
    return {
        id: 101,
        name: "skin_primary_colour",
        value: "#999999",
        title: "Primary Colour",
        description: "Set the primary brand colour.",
        customProperty: "--sbx-config-primary-colour",
        contrast: "background",
        hasAlpha: true,
        type: "colour",
        selectOptions: [],
        editable: true,
        hidden: false,
        ...overrides,
    };
}

function buildConfigs({ enabled = true, optionOverrides = {} } = {}) {
    return {
        contrastTokens: {
            background: {
                label: "Page background",
                value: "#ffffff",
            },
        },
        toggle: {
            enabled,
            title: "Enable Sonar UI",
            description: null,
        },
        existing: {},
        sonar: {
            Colours: [
                {
                    name: null,
                    options: [buildColourOption(optionOverrides)],
                },
            ],
        },
    };
}

function mountSkinConfig(configs) {
    return mount(SkinConfig, {
        props: {
            routes: {
                post: "/admin/design/skin-config",
                preview: "/admin/design/skin-config/preview",
            },
            configs,
        },
    });
}

test("shows the colour contrast warning label when Sonar has colour errors", () => {
    const wrapper = mountSkinConfig(buildConfigs());

    const warningLabel = wrapper.get("sbx-label");
    expect(warningLabel.attributes("size")).toBe("l");
    expect(warningLabel.attributes("sentiment")).toBe("alert");
    expect(warningLabel.attributes("icon")).toBe("alert");
    expect(warningLabel.attributes("text")).toBe("Resolve all colour contrast issues to save the Skin Config.");
});

test("hides the colour contrast warning label when Sonar colours are readable", () => {
    const wrapper = mountSkinConfig(buildConfigs({ optionOverrides: { value: "#666666" } }));

    expect(wrapper.find("sbx-label").exists()).toBe(false);
});

test("hides the colour contrast warning label when Sonar is disabled", () => {
    const wrapper = mountSkinConfig(buildConfigs({ enabled: false }));

    expect(wrapper.find("sbx-label").exists()).toBe(false);
});
