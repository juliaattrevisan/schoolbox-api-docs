import { mount } from "@vue/test-utils";
import McViewHeaderBreadcrumb from "../mc-view-header-breadcrumb.vue";
import { createTestingPinia } from "@pinia/testing";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McViewHeaderBreadcrumb, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            breadcrumb: {
                name: "My Breadcrumb",
                id: 0,
                depth: 0,
            },
            isDisabled: false,
            isLast: false,
        },
    });
});

test("link to be visible when prop isDisabled is false", async () => {
    expect(wrapper.find("a").exists()).toBe(true);
    expect(wrapper.find("span").exists()).toBe(false);
});

test("link to be hidden when prop isDisabled is true", async () => {
    await wrapper.setProps({ isDisabled: true });
    expect(wrapper.find("a").exists()).toBe(false);
    expect(wrapper.find("span").exists()).toBe(true);
});

test("name is shortened when exceeds 50 characters", async () => {
    await wrapper.setProps({
        breadcrumb: {
            name: "Phasellus varius est lorem, luctus eleifend turpis volutpat nec. Mauris non enim lectus.",
        },
    });
    expect(wrapper.find("a").text()).toContain("Phasellus varius est lorem, luctus eleifend turpis...");
});
