import { mount } from "@vue/test-utils";
import McViewNodeActions from "../mc-view-node-actions";
import { createTestingPinia } from "@pinia/testing";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McViewNodeActions, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            node: {
                id: 0,
                name: "My Node",
                children: [],
            },
            title: "My Node",
            isViewing: false,
            isUpdating: false,
            isAdding: false,
        },
    });
});

test("update button is disabled when prop isAdding is true", async () => {
    await wrapper.setProps({ isAdding: true });
    expect(wrapper.find("[data-test-update]").element).toBeDisabled();
    expect(wrapper.find("[data-test-add]").element).not.toBeDisabled();
});

test("add button is disabled when prop isUpdating is true", async () => {
    await wrapper.setProps({ isUpdating: true });
    expect(wrapper.find("[data-test-update]").element).not.toBeDisabled();
    expect(wrapper.find("[data-test-add]").element).toBeDisabled();
});
