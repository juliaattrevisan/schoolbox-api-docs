import { mount } from "@vue/test-utils";
import { timeout } from "@js/util/jsUtils";
import { useSelected } from "@schoolboxVue/composables/store/useSelected";
import SearchSelect from "../search-select.vue";
import { DEBOUNCE_AMOUNT } from "../composables/useSearch";

test("selected items are visible", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: [
                { id: 1, name: "Item 1" },
                { id: 2, name: "Item 2" },
                { id: 3, name: "Item 3" },
            ],
        },
    });
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(3); // validate the selected items are visible
    const selectedItem = wrapper.get(`[data-test="search-select-selected-item-container-1"]`); // get the first selected item
    expect(selectedItem.html()).toContain("Item 1"); // validate the selected item contains the correct text
});

test("search results can be added", async () => {
    const itemToAdd = { id: 4, name: "Search result item 1" };
    const mockSearchList = [itemToAdd, { id: 5, name: "Search result item 2" }];
    const { selected, add } = useSelected();
    add([{ id: 1, name: "Item 1" }]);
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="search-select-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(2); // validate the search results are visible
    /**
     * Add a search result
     */
    const searchResult = wrapper.get(`[data-test="search-select-result-container-4"]`);
    searchResult.trigger("click"); // add the search result to the selected items
    const addEvent = wrapper.emitted("add"); // validate the event has been emitted
    expect(addEvent[0]).toEqual([[itemToAdd]]); // validate the event has the correct payload
    add(itemToAdd); // add the result to the selected items
    await wrapper.setProps({ selectedItems: selected.value }); // update the selected items (this is needed because the prop is apparent not reactive in this context)
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(2); // validate the item has been added to the UI

    global.fetch.mockClear();
});

test("selected items can be removed", async () => {
    const { selected, add, remove } = useSelected();
    const itemToRemove = { id: 2, name: "Item 2" };
    add([{ id: 1, name: "Item 1" }, itemToRemove, { id: 3, name: "Item 3" }]);
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
        },
    });
    wrapper.get(`[data-test="search-select-selected-item-remove-2"]`).trigger("click");
    const removeEvent = wrapper.emitted("remove"); // validate the event has been emitted
    expect(removeEvent[0]).toEqual([[itemToRemove]]); // validate the event has the correct payload
    remove(itemToRemove); // remove the item from the selected items
    expect(selected.value).toHaveLength(2); // validate the item has been removed
    await wrapper.setProps({ selectedItems: selected.value }); // update the selected items (this is needed because the prop is apparent not reactive in this context)
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(2); // validate the item has been removed from the UI
});

test("select all can be used to add/remove all search results", async () => {
    const mockSearchList = [
        { id: 4, name: "Search result item 1" },
        { id: 5, name: "Search result item 2" },
    ];
    const { selected, add, remove } = useSelected();
    add([{ id: 1, name: "Item 1" }]);
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="search-select-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(2); // validate the search results are visible
    /**
     * Add all search results
     */
    const selectAll = wrapper.get(`[data-test="search-select-select-all"]`);
    selectAll.trigger("click"); // add the search result to the selected items
    const addEvent = wrapper.emitted("add"); // validate the event has been emitted
    expect(addEvent[0]).toEqual([mockSearchList]); // validate the event has the correct payload
    add(mockSearchList); // add the result to the selected items
    await wrapper.setProps({ selectedItems: selected.value }); // update the selected items (this is needed because the prop is apparent not reactive in this context)
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(3); // validate the item has been added to the UI
    /**
     * Remove all search results
     */
    selectAll.trigger("click"); // click again to remove the search results from the selected items
    const removeEvent = wrapper.emitted("remove"); // validate the event has been emitted
    expect(removeEvent[0]).toEqual([mockSearchList]); // validate the event has the correct payload
    remove(mockSearchList); // add the result to the selected items
    await wrapper.setProps({ selectedItems: selected.value }); // update the selected items (this is needed because the prop is apparent not reactive in this context)
    expect(wrapper.findAll('[data-test^="search-select-selected-item-container-"]')).toHaveLength(1); // validate the results have been removed to the UI

    global.fetch.mockClear();
});

test("select all is not visible when only one search result is found", async () => {
    const mockSearchList = [{ id: 4, name: "Search result item 1" }];
    const { selected, add } = useSelected();
    add([{ id: 1, name: "Item 1" }]);
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="search-select-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(1); // validate the search results are visible

    expect(wrapper.findAll(`[data-test="search-select-select-all"]`)).toHaveLength(0); // validate the select all button is not visible

    global.fetch.mockClear();
});

test("search results are removed when search query is cleared", async () => {
    const mockSearchList = [
        { id: 4, name: "Search result item 1" },
        { id: 5, name: "Search result item 2" },
    ];
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: [],
        },
    });
    /**
     * Search for results
     */
    jest.spyOn(global, "fetch").mockImplementation(setupFetchStub(mockSearchList)); // setup the fetch stub
    const searchInput = wrapper.get(`[data-test="search-select-input"]`);
    searchInput.setValue("search this"); // set the search input value, which will trigger the search
    await timeout(DEBOUNCE_AMOUNT + 150); // allow the search to kick in after debounce complete
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(2); // validate the search results are visible
    searchInput.setValue(""); // set the search input value, which will trigger the search
    await wrapper.vm.$nextTick(); // allow the DOM to update after search cleared
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(0); // validate the search results are no longer visible

    global.fetch.mockClear();
});

test("default results are visible when search is not active", async () => {
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: [],
            defaultResults: [
                { id: 1, name: "Default Item 1" },
                { id: 2, name: "Default Item 2" },
            ],
        },
    });
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(2); // validate the default results is visible
});

test("only one selection allowed when isSingleSelect is true", async () => {
    const { selected, add } = useSelected();
    const wrapper = mount(SearchSelect, {
        props: {
            label: "Search Select",
            labelId: "search-select",
            endpoint: "",
            selectedItems: selected.value,
            defaultResults: [
                { id: 1, name: "Default Item 1" },
                { id: 2, name: "Default Item 2" },
            ],
            isSingleSelect: true,
        },
    });
    expect(wrapper.findAll('[data-test^="search-select-result-checkbox-"]')).toHaveLength(0); // validate the checkboxes on results are not visible
    add([{ id: 1, name: "Default Item 1" }]);
    await wrapper.setProps({ selectedItems: selected.value }); // update the selected items (this is needed because the prop is apparent not reactive in this context)
    expect(wrapper.findAll('[data-test^="search-select-result-container-"]')).toHaveLength(0); // validate the default results are no longer visible
    const selectedItem = wrapper.get(`[data-test="search-select-selected-item-container-1"]`); // get the first selected item
    expect(selectedItem.html()).toContain("Default Item 1"); // validate the selected item contains the correct text
});
