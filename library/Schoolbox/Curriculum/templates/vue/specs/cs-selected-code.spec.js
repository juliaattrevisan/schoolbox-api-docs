import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import CsSelectedCode from '../cs-selected-code';
import { createTestingPinia } from '@pinia/testing';
import { useStore } from '../store/curriculumSelectorStore';
import { useSelectedCodesStore } from '../store/csSelectedCodesStore';

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSelectedCode, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
        props: {
            code: {
                id: 1,
                code: 'C1',
                name: 'Code 1',
                breadcrumb: '',
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
    const selectedCodesStore = useSelectedCodesStore();
    expect(selectedCodesStore.hasActiveCode).toBe(false);
    await wrapper.find('[data-test-title-button]').trigger('click');
    expect(selectedCodesStore.hasActiveCode).toBe(true);
});