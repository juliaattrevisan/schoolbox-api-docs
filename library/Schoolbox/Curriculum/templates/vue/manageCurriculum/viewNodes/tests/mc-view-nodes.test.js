
import { mount } from '@vue/test-utils';
import McViewNodes from '../mc-view-nodes';
import { createTestingPinia } from '@pinia/testing';
import { useNodesStore } from '../../store/mcNodesStore';
import { useViewNodesStore } from '../store/mcViewNodesStore';
import { data } from '../../tests/data';

let wrapper; // so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(McViewNodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
    });
});

test('should hide disabled nodes when show disabled nodes is set to hidden', async () => {
    const nodesStore = useNodesStore();
    await nodesStore.$patch({
        nodes: data, // contains two disabled nodes
        isLoading: false,
    });
    const allNodes = wrapper.findAllComponents('[data-test-view-node-name]');
    expect(allNodes).toHaveLength(6); // as we are showing all nodes
    const viewNodesStore = useViewNodesStore();
    await viewNodesStore.$patch({ showDisabled: false });
    await new Promise((r) => setTimeout(r, 1000));
    const onyEnabledNodes = wrapper.findAllComponents('[data-test-view-node-name]');
    expect(onyEnabledNodes).toHaveLength(4); // and now as two are disabled, they should not be visible
});