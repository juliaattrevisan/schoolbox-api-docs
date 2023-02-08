import { mount } from "@vue/test-utils";
import CsSearchInput from "../cs-search-input";
import { createTestingPinia } from "@pinia/testing";
import { useStore } from "../store/curriculumSelectorStore";

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSearchInput, {
        global: {
            plugins: [createTestingPinia()],
        },
        props: {
            curriculumSelectorEl: null, //is a ref() containing a HTMLElement
        },
    });
});

test("component should not be visible when curriculum selector is read only", () => {
    expect(wrapper.find("input").exists()).toBe(false);
});

describe("search input", () => {
    //describe is just used to group tests together

    test("should be disabled if there is a load error", async () => {
        const store = useStore();
        await store.$patch({ isReadOnly: false, isLoading: false, hasLoadError: true });
        expect(wrapper.find("input").attributes().readonly).toBeDefined();
    });

    test("should display when load is successful", async () => {
        const store = useStore();
        await store.$patch({ isReadOnly: false, isLoading: false });
        expect(wrapper.find("input").attributes().readonly).toBeUndefined();
    });
});

describe("browse button", () => {
    test("should not display while loading", () => {
        expect(wrapper.find("button").exists()).toBe(false);
    });

    test("should not display if there is a load error", async () => {
        const store = useStore();
        await store.$patch({ isReadOnly: false, isLoading: false, hasLoadError: true });
        expect(wrapper.find("button").exists()).toBe(false);
    });

    test("should display when load is successful", async () => {
        const store = useStore();
        await store.$patch({ isReadOnly: false, isLoading: false });
        expect(wrapper.find("button").exists()).toBe(true);
    });
});
