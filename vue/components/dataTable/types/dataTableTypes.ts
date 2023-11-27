/**
 * The properties linked to each column
 */
export interface ColumnConfig {
    key: string; // the key within the row data to display in each column
    heading: string; // the heading of each column
    class?: string; // to allow any custom styling, such as hiding a column on smaller screens
    isSortable?: boolean; // to disable sorting of a column, by default all columns are sortable
    isHidden?: boolean; // to hide a column, typically used to add extra search data to the table
    template?: TemplateConfig; // to allow custom templates to be used in the column
}

/**
 * To specifiy that a template is to be used for a column
 */
export interface TemplateConfig {
    key: string; // the key to know which column to apply the template to
    component: string; // the component to use for the template, for example "image" will use "data-table-image"
    componentData: LinkComponentData | ImageComponentData; // any data to use within the component
}

/**
 * The properties to use within the data-table-link component
 */
export interface LinkComponentData {
    text: string; // the text to display, typically a data key
    href: string; // the href to link to, typically a data key
    title: string; // the title text for the link
    prependHref?: string; // to prepend the href with something like mailto: or tel:
    hasNoWrap?: boolean; // to disable wrapping of the text
}

/**
 * The properties to use within the data-table-image component
 */
export interface ImageComponentData {
    src: string; // the src of the image, typically a data key
    alt: string; // alternative text for the image
    href?: string; // to link the image, typically a data key
    title?: string; // the title text for the link
    imgClass?: string; // to allow any custom styling of the image
}

/**
 * The json data thats loaded into the table,
 * as this can vary, its just a key value pair of strings
 */
export interface Data {
    [key: string]: unknown;
}
