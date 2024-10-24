import { mount } from "@vue/test-utils";
import { timeout } from "@js/util/jsUtils";
import SearchSelect from "../search-select.vue";
import { DEBOUNCE_AMOUNT } from "../composables/useSearch";

const defaultProps = {
    label: "Search Select",
    labelId: "search-select",
    endpoint: "",
};

test("selected items are visible", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [
                { id: 1, name: "Item 1" },
                { id: 2, name: "Item 2" },
                { id: 3, name: "Item 3" },
            ],
        },
    });
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(3); // validate the selected items are visible
    const selectedItem = wrapper.get(`[data-test="ss-selected-item-container-1"]`); // get the first selected item
    expect(selectedItem.html()).toContain("Item 1"); // validate the selected item contains the correct text
});

test("search results can be added", async () => {
    const itemToAdd = { id: 4, name: "Search result item 1" };
    const mockSearchList = [itemToAdd, { id: 5, name: "Search result item 2" }];
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [{ id: 1, name: "Item 1" }],
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="ss-query-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="ss-results-item-container-"]')).toHaveLength(2); // validate the search results are visible
    /**
     * Add a search result
     */
    const searchResult = wrapper.get(`[data-test="ss-results-item-checkbox-4"]`);
    searchResult.trigger("click"); // add the search result to the selected items
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(2); // validate the item has been added to the UI

    global.fetch.mockClear();
});

test("selected items can be removed", async () => {
    const itemToRemove = { id: 2, name: "Item 2" };
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [{ id: 1, name: "Item 1" }, itemToRemove, { id: 3, name: "Item 3" }],
        },
    });
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(3); // validate the expected number of selected items
    wrapper.get(`[data-test="ss-selected-list-item-remove-2"]`).trigger("click");
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(2); // validate the item has been removed from the UI
});

test("select all can be used to add/remove all search results", async () => {
    const mockSearchList = [
        { id: 4, name: "Search result item 1" },
        { id: 5, name: "Search result item 2" },
    ];
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [{ id: 1, name: "Item 1" }],
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="ss-query-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="ss-results-item-container-"]')).toHaveLength(2); // validate the search results are visible
    /**
     * Add all search results
     */
    const selectAll = wrapper.get(`[data-test="ss-results-select-all"]`);
    selectAll.trigger("click"); // add the search result to the selected items
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(3); // validate the items have been added to the UI
    /**
     * Remove all search results
     */
    selectAll.trigger("click"); // click again to remove the search results from the selected items
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(1); // validate the results have been removed from the UI

    global.fetch.mockClear();
});

test("select all is not visible when only one search result is found", async () => {
    const mockSearchList = [{ id: 4, name: "Search result item 1" }];
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [{ id: 1, name: "Item 1" }],
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="ss-query-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="ss-results-item-container-"]')).toHaveLength(1); // validate the search results are visible

    expect(wrapper.findAll(`[data-test="ss-results-select-all"]`)).toHaveLength(0); // validate the select all button is not visible

    global.fetch.mockClear();
});

test("search results are removed when search query is cleared", async () => {
    const mockSearchList = [
        { id: 4, name: "Search result item 1" },
        { id: 5, name: "Search result item 2" },
    ];
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [],
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="ss-query-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="ss-results-item-container-"]')).toHaveLength(2); // validate the search results are visible
    searchInput.setValue(""); // set the search input value, which will trigger the search
    await wrapper.vm.$nextTick(); // allow the DOM to update after search cleared
    expect(wrapper.findAll('[data-test^="ss-results-item-container-"]')).toHaveLength(0); // validate the search results are no longer visible

    global.fetch.mockClear();
});

test("default results are visible when search is not active", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [],
            defaultResults: [
                { id: 1, name: "Default Item 1" },
                { id: 2, name: "Default Item 2" },
            ],
        },
    });
    expect(wrapper.findAll('[data-test^="ss-results-item-container-"]')).toHaveLength(2); // validate the default results is visible
});

test("only one selection allowed when isSingleSelect is true", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [],
            defaultResults: [
                { id: 1, name: "Default Item 1" },
                { id: 2, name: "Default Item 2" },
            ],
            isSingleSelect: true,
        },
    });
    expect(wrapper.findAll('[data-test^="ss-results-item-checkbox-"]')).toHaveLength(0); // validate the checkboxes on results are not visible
    /**
     * Add a search result
     */
    const searchResult = wrapper.get(`[data-test="ss-results-item-radio-1"]`);
    searchResult.trigger("click"); // add the search result to the selected items
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(1); // validate only one selected result
    /**
     * Now choose another search result
     */
    const anotherSearchResult = wrapper.get(`[data-test="ss-results-item-radio-2"]`);
    anotherSearchResult.trigger("click"); // add another search result to the selected items
    await wrapper.vm.$nextTick();
    /**
     * Validate the second item is the only selected item
     */
    expect(wrapper.findAll('[data-test^="ss-selected-item-container-"]')).toHaveLength(1); // validate only one selected result
    const selectedItem = wrapper.get(`[data-test="ss-selected-item-container-2"]`); // get the selected item
    expect(selectedItem.html()).toContain("Default Item 2"); // validate the selected item contains the correct text
});

test("query input is not shown if isReadOnly is true", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            ...defaultProps,
            selected: [],
            isReadOnly: true,
        },
    });
    expect(wrapper.find(`[data-test="ss-query-input"]`).exists()).toBe(false);
});
