import { mount } from "@vue/test-utils";
import SearchPage from "../../search-page.vue";
import { createTestingPinia } from "@pinia/testing";

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    window.schoolboxUser = { id: 0, name: "Test User" };
    wrapper = mount(SearchPage, {
        global: {
            plugins: [createTestingPinia()],
        },
        props: {
            endpoint: "",
            filterTypesAvailable: [],
            filterTypesSelected: [],
            filterDateRange: {
                startDate: "2023-12-31T19:43:20+11:00",
                endDate: "2023-01-01T19:43:20+11:00",
            },
            filterCreator: null,
            resultPageSize: 20,
        },
    });
});

test("if has not searched, should display search prompt", async () => {
    expect(wrapper.get('[data-test="sp-results-prompt"]').isVisible()).toBe(true);
});
