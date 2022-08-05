import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import CsSelectedCodes from '../cs-selected-codes';
import { createTestingPinia } from '@pinia/testing';
import { useStore } from '../store/curriculumSelectorStore';
import { useSelectedCodesStore } from '../store/csSelectedCodesStore';

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSelectedCodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
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
    expect(wrapper.find('[data-test-remove-button]').exists()).toBe(false);
});

test('selecting code should add active code', async () => {
    const selectedCodesStore = useSelectedCodesStore();
    expect(selectedCodesStore.hasActiveCode).toBe(false);
    selectedCodesStore.setActiveCode( { id: 1, code: 'C1', name: 'Code 1' } );
    expect(selectedCodesStore.hasActiveCode).toBe(true);
});