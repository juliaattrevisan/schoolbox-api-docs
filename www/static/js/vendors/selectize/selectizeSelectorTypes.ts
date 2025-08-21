/// <reference types="selectize" />

/** The default type for an item in a selectize selector */
export type SelectizeSelectorItem = {
    id: number;
    name: string;
}

/**
 * Options for jquery.selectize-selector.js
 * @template TData The data type of the items
 * @template TValue The type of the property to use as the "value" when an item is selected
 */
export type SelectizeSelectorOptions<TData = SelectizeSelectorItem, TValue = SelectizeSelectorItem["id"]> = {
    ajax?: JQuery.AjaxSettings;
    selectize?: Omit<Selectize.IOptions<TValue, TData>, 'labelField' | 'valueField'> & {
        searchField?: keyof TData | (keyof TData)[];
        labelField?: keyof TData;
        valueField?: keyof TData;
    };
    value?: TValue[];
    multiple?: boolean;
};
