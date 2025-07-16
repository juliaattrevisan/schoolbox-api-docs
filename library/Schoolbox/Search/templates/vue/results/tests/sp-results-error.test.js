import { mount } from "@vue/test-utils";
import SearchPage from "../../search-page.vue";
import { createTestingPinia } from "@pinia/testing";
import { useSearchStore } from "../../store/spSearchStore";

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
            resultPageSize: 5,
        },
    });
});

test("if there is a load error, load error should be displayed", async () => {
    const store = useSearchStore();
    await store.$patch({ hasNotSearched: false, hasLoadError: true });
    expect(wrapper.get('[data-test="sp-results-error"]').isVisible()).toBe(true);
});
