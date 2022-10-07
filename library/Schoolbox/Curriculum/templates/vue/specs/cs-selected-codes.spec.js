import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import CsSelectedNodes from '../cs-selected-nodes';
import { createTestingPinia } from '@pinia/testing';
import { useSelectedNodesStore } from '../store/csSelectedNodesStore';


let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSelectedNodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
    });
});

test('should not display when there is no codes', () => {
    const selectedNodesStore = useSelectedNodesStore();
    expect(selectedNodesStore.hasNodes).toBe(false);
    expect(wrapper.find('[data-test-container]').exists()).toBe(false);
});

test('should display when there is codes', async () => {
    const selectedNodesStore = useSelectedNodesStore();
    selectedNodesStore.add([
        { id: 1, code: 'C1', name: 'Code 1' },
        { id: 2, code: 'C2', name: 'Code 2' },
    ]);
    expect(selectedNodesStore.hasNodes).toBe(true); //should have codes now
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    expect(wrapper.findAll('[data-test-node]')).toHaveLength(2); //should be two items in the dom now
});

test('should not display when had codes but has been emptied', async () => {
    const selectedNodesStore = useSelectedNodesStore();
    selectedNodesStore.add([
        { id: 1, code: 'C1', name: 'Code 1' },
        { id: 2, code: 'C2', name: 'Code 2' },
    ]);
    expect(selectedNodesStore.hasNodes).toBe(true); //should have codes now
    selectedNodesStore.empty();
    expect(selectedNodesStore.hasNoNodes).toBe(true); //and now no codes
    expect(wrapper.find('[data-test-container]').exists()).toBe(false);
});