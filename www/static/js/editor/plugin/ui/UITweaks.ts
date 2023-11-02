/**
 * Any UI related tweaks that need to be made to the editor to fit
 * within the SB context should be added here.
 */

import { Plugin } from "@ckeditor/ckeditor5-core";
import { getOptimalPosition } from "@ckeditor/ckeditor5-utils";
import { EditorEvent } from "@js/editor/event/editorEvent";

export default class PanelTweaks extends Plugin {
    /**
     * We need to ensure everything has been set up before we can start modifying the panels
     */
    init() {
        this.editor.sourceElement.addEventListener(EditorEvent.EDITOR__CREATED, () => {
            this.updateDropdownPositions();
            this.checkForModal();
        });
    }

    /**
     * Be default the <body> element is used to check if the entire dropown is visible when open.
     * This is problematic when we have either the side menu or the notifications panel open as
     * part of the dropdown may appear behind them. To resolve we pass in the #content element
     * into the getOptimalPosition method used.
     */
    updateDropdownPositions() {
        const toolbarItems = this.editor.ui.view.toolbar.items;
        const dropDownItems = toolbarItems.filter((item) => item.panelPosition); // get only those toolbar items that are dropdowns
        dropDownItems.forEach((item) => {
            this.updateDropdownPosition(item);
        });
    }

    /**
     * When the dropdown is opened, we need to update the position of the dropdown.
     * As these listeners are assigned after the toolbar has been created, they will be executed
     * after the listener assigned within the toolbar item.
     * We just replicate the logic used in https://ckeditor.com/docs/ckeditor5/latest/api/module_ui_dropdown_dropdownview-DropdownView.html#function-render
     * but add .limiter to the getOptimalPosition util method.
     * @param {DropdownButtonView} item - the dropdown item
     */
    updateDropdownPosition(item) {
        item.on("change:isOpen", (eventInfo, name, isOpen) => {
            if (!isOpen) {
                return;
            }
            /**
             * We also need to account for dropdowns within dropdowns.
             */
            if (item.toolbarView) {
                const innerItems = item.toolbarView.itemsView.children;
                const innerDropDownItems = innerItems.filter((item) => item.panelPosition); // get only those toolbar items that are dropdowns
                innerDropDownItems.forEach((item) => {
                    this.updateDropdownPosition(item);
                });
            }
            item.panelView.position = getOptimalPosition({
                element: item.panelView.element,
                target: item.buttonView.element,
                fitInViewport: true,
                limiter: document.getElementById("content"),
                positions: item._panelPositions,
            }).name;
        });
    }

    /**
     * if the editor is inside a reveal modal, we need to make sure
     * the balloon is on top of the modal, otherwise it will be hidden underneath
     */
    checkForModal() {
        // a modal is typically set to 1005, so we need to be higher than that
        const zIndex = "1010";
        if (this.editor.sourceElement.closest("[data-reveal]")) {
            const contextualBalloon = this.editor.plugins.get("ContextualBalloon");
            // fired when the balloon is shown or hidden by any UI
            contextualBalloon.on("change:visibleView", (eventInfo, name, value) => {
                const balloonElement = value?.element.parentElement.parentElement.parentElement;
                if (balloonElement && balloonElement.style.zIndex !== zIndex) {
                    balloonElement.style.zIndex = zIndex;
                    // we also need to add the editor id to the balloon so we can potentially access it later
                    balloonElement.setAttribute("data-baloon-editor-id", this.editor.id);
                }
            });
        }
    }
}
