import { mount } from "@vue/test-utils";
import SearchSelectModal from "../modal/search-select-modal.vue";

jest.mock("@vueuse/core", () => {
    const { ref } = require("vue");

    return {
        onClickOutside: jest.fn(),
        useFocus: jest.fn(() => ({ focused: ref(true) })),
        useResizeObserver: jest.fn(),
    };
});

const defaultProps = {
    label: "Search",
    labelId: "search-select-modal",
    title: "Select Items",
    endpoint: "/test-endpoint",
    selected: [
        { id: 1, name: "Item 1" },
        { id: 2, name: "Item 2" },
    ],
};

function mountSearchSelectModal(props = {}) {
    return mount(SearchSelectModal, {
        props: {
            ...defaultProps,
            ...props,
        },
        global: {
            stubs: {
                "basic-modal": {
                    template: "<div><slot /></div>",
                    props: ["is-open"],
                },
                "ss-query-input": {
                    template: '<div data-test="ss-query-input-stub"></div>',
                },
                "ss-results": {
                    template: '<div data-test="ss-results-stub"></div>',
                },
            },
        },
    });
}

async function flushAsyncUpdates() {
    await new Promise((resolve) => {
        setTimeout(resolve, 0);
    });
}

beforeEach(() => {
    jest.spyOn(global, "fetch").mockResolvedValue({
        ok: true,
        json: async () => [],
    });
});

afterEach(() => {
    jest.restoreAllMocks();
});

test("selected items are hidden by default", async () => {
    const wrapper = mountSearchSelectModal();

    expect(wrapper.find('[data-test="ss-selected"]').exists()).toBe(false);
});

test("selected items are visible when showSelected is true", async () => {
    const wrapper = mountSearchSelectModal({ showSelected: true });

    expect(wrapper.find('[data-test="ss-selected"]').exists()).toBe(true);
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(2);
});

test("selected items can be removed from within the modal", async () => {
    const wrapper = mountSearchSelectModal({ showSelected: true });

    await wrapper.get('[data-test="ss-selected-list-item-remove-2"]').trigger("click");

    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(1);
    expect(wrapper.find('[data-test="ss-selected-item-container-2"]').exists()).toBe(false);
});

test("cancelling restores the saved selection after removing an item in the modal", async () => {
    const wrapper = mountSearchSelectModal({ showSelected: true, "is-open": false });

    await wrapper.setProps({ "is-open": true });
    await flushAsyncUpdates();
    await wrapper.vm.$nextTick();

    await wrapper.get('[data-test="ss-selected-list-item-remove-2"]').trigger("click");
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(1);

    const cancelButton = wrapper.findAll("button").find((button) => button.text() === "Cancel");
    await cancelButton.trigger("click");
    await wrapper.vm.$nextTick();

    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(2);
    expect(wrapper.find('[data-test="ss-selected-item-container-2"]').exists()).toBe(true);
});
