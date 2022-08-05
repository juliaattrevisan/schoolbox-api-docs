import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import CsSelectedCodes from '../cs-selected-codes';
import { createTestingPinia } from '@pinia/testing';
import { useSelectedCodesStore } from '../store/csSelectedCodesStore';


let wrapper; //so we don't need to pass it into every test

beforeEach(() => {
    wrapper = mount(CsSelectedCodes, {
        global: {
            plugins: [createTestingPinia({ stubActions: false })], //By default, all actions are mocked and therefore not executed. This allows you to unit test your store and components separately.
        },
    });
});

test('should not display when there is no codes', () => {
    const selectedCodesStore = useSelectedCodesStore();
    expect(selectedCodesStore.hasCodes).toBe(false);
    expect(wrapper.find('[data-test-container]').exists()).toBe(false);
});

test('should display when there is codes', async () => {
    const selectedCodesStore = useSelectedCodesStore();
    selectedCodesStore.add([ 
        { id: 1, code: 'C1', name: 'Code 1' }, 
        { id: 2, code: 'C2', name: 'Code 2' }, 
    ]);
    expect(selectedCodesStore.hasCodes).toBe(true); //should have codes now
    await nextTick(); //need to ensure the DOM has updated first prior to checking
    expect(wrapper.findAll('[data-test-code]').length).toBe(2); //should be two items in the dom now
});