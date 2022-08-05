import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import CsSearchInput from '../cs-search-input';
import { createTestingPinia } from '@pinia/testing';
import { useStore } from '../store/curriculumSelectorStore'; 

let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSearchInput, {
        global: {
            plugins: [createTestingPinia()],
        },
        props: {
            curriculumSelectorEl: null
        }
    });
});

test('component should not be visible when curriculum selector is read only', () => {
    expect(wrapper.find('input').exists()).toBe(false);
});

test('do not display browse button until curriculum codes are loaded', () => {
    expect(wrapper.find('button').exists()).toBe(false);
});

test('do not display browse button if theres a load error', async () => {
    const store = useStore();
    store.$patch( { isReadOnly: false, isLoading: false, hasLoadError: true } );
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    expect(wrapper.find('button').exists()).toBe(false);
});

test('display browse button when curriculum codes are loaded', async () => {
    const store = useStore();
    store.$patch( { isReadOnly: false, isLoading: false } );
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    expect(wrapper.find('button').exists()).toBe(true);
});

test('search input should be enabled if there is a load error', async () => {
    const store = useStore();
    store.$patch( { isReadOnly: false, isLoading: false } );
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    expect(wrapper.find('input').attributes().readonly).toBeUndefined();
});

test('search input should be readonly if there is a load error', async () => {
    const store = useStore();
    store.$patch( { isReadOnly: false, isLoading: false, hasLoadError: true } );
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    expect(wrapper.find('input').attributes().readonly).toBeDefined();
});