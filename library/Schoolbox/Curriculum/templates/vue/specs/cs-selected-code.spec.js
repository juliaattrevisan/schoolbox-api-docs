import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import CsSelectedNode from '../cs-selected-node';
import { createTestingPinia } from '@pinia/testing';
import { useStore } from '../store/curriculumSelectorStore';
import { useSelectedNodesStore } from '../store/csSelectedNodesStore';

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSelectedNode, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            node: {
                id: 1,
                code: 'C1',
                name: 'Code 1',
            }
        },
    });
});

test('remove button should not be visible when read only', async () => {
    expect(wrapper.find('[data-test-remove-button]').exists()).toBe(false);
});

test('remove button should be visible when not read only', async () => {
    const store = useStore();
    store.$patch( { isReadOnly: false } );
    await nextTick();
    expect(wrapper.find('[data-test-remove-button]').exists()).toBe(true);
});

test('selecting title should add active code', async () => {
    const selectedNodesStore = useSelectedNodesStore();
    expect(selectedNodesStore.hasActiveNode).toBe(false);
    await wrapper.find('[data-test-title-button]').trigger('click');
    expect(selectedNodesStore.hasActiveNode).toBe(true);
});