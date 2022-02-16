/** 
 * The various homepage events that can occur
 *
 */

export default Object.freeze({
    COMPONENT_ADDED: 'UPDATE_HOMEPAGE__COMPONENT_ADDED', //to set up the new component, dispatched with the { Element } newComponent that's been added
    COMPONENT_REMOVED: 'UPDATE_HOMEPAGE__COMPONENT_REMOVED', //to set up the new component, dispatched with the { Element } component that's been removed
    COMPONENT_UPDATED: 'UPDATE_HOMEPAGE__COMPONENT_UPDATED', //to remove the component, dispatched with the { Element } component that's been updated
    EXISTING_COMPONENT_SORT_START: 'UPDATE_HOMEPAGE__EXISTING_COMPONENT_SORT_START', //to show/hide 'add component here' placeholders
    EXISTING_COMPONENT_SORT_UPDATE: 'UPDATE_HOMEPAGE__EXISTING_COMPONENT_SORT_UPDATE', //to show/hide 'add component here' placeholders
    EXISTING_COMPONENT_SORT_END: 'UPDATE_HOMEPAGE__EXISTING_COMPONENT_SORT_END', //to show/hide 'add component here' placeholders
    NEW_COMPONENT_SORT_UPDATE: 'UPDATE_HOMEPAGE__NEW_COMPONENT_SORT_UPDATE', //to show/hide 'add component here' placeholders
    NEW_COMPONENTS_TABS_OPENED: 'UPDATE_HOMEPAGE__NEW_COMPONENTS_TABS_OPENED', //to show/hide 'add component here' placeholders
    NEW_COMPONENTS_TABS_CLOSED: 'UPDATE_HOMEPAGE__NEW_COMPONENTS_TABS_CLOSED', //to show/hide 'add component here' placeholders
    EDIT_COLUMNS_RESET: 'UPDATE_HOMEPAGE__EDIT_COLUMNS_RESET', //reset all column settings back to defaults
    EDIT_COLUMNS_UNDO_CHANGES: 'UPDATE_HOMEPAGE__EDIT_COLUMNS_UNDO_CHANGES', //undo any previously saved column setting changes
    EDIT_COLUMNS_SAVE: 'UPDATE_HOMEPAGE__EDIT_COLUMNS_SAVE', //save all column settings
})
