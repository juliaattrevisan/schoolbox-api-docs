import { mount } from "@vue/test-utils";
import McViewHeaderSearchInput from "../mc-view-header-search-input";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../store/mcSearchStore";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McViewHeaderSearchInput, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            isDisabled: false,
        },
    });
});

test("clear search button should not be visible when search is not active", async () => {
    expect(wrapper.find("[data-test-clear-search]").exists()).toBe(false);
});

test("clear search button should be visible when search is active", async () => {
    await useSearchStore().$patch({ query: "test query" });
    expect(wrapper.find("[data-test-clear-search]").exists()).toBe(true);
});

test("search input is disabled when prop isDisabled is true", async () => {
    await wrapper.setProps({ isDisabled: true });
    expect(wrapper.find('[data-test="search-input"]').element).toBeDisabled();
});
