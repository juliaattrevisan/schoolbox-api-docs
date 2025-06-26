/**
 * Any UI related tweaks that need to be made to the editor to fit
 * within the SBX context should be added here.
 */

import {
    Plugin,
    getOptimalPosition,
    ButtonView,
    LinkUI,
    FullscreenEditing,
    type ClassicEditor,
    type InlineEditor,
    type DropdownView,
    type ViewCollection,
    type DomPoint,
} from "ckeditor5";
import { EditorEvent } from "@js/editor/event/editorEvent";
import { insertElementAfter } from "@js/util/domUtils";
import ResourceLinkEditing from "@js/editor/plugin/resourceLink/ResourceLinkEditing";
import InlineEditorHandler from "@js/editor/plugin/ui/InlineEditorHandler";
import type InsertResourceLinkCommand from "@js/editor/plugin/resourceLink/InsertResourceLinkCommand";

export default class UITweaks extends Plugin {
    /**
     * Add in the ResourceLinkEditing command,
     * which gets triggered when the Link Browse button is clicked
     * and is also used to update the Link URL input when a resource is selected
     * @returns - the required plugins
     */
    public static get requires(): readonly (typeof Plugin)[] {
        return [ResourceLinkEditing, FullscreenEditing] as const;
    }

    /**
     * We need to ensure everything has been set up before we can start modifying the panels
     */
    init(): void {
        (this.editor as ClassicEditor).sourceElement?.addEventListener(EditorEvent.EDITOR__CREATED, () => {
            this.updateDropdownPositions();
            this.addLinkBrowseButton();
        });
        /**
         * For the InlineEditor, we need to override the default fullscreen handler
         */
        if ((this.editor.constructor as typeof InlineEditor).editorName === "InlineEditor") {
            const toggleFullscreenCommand = this.editor.commands.get("toggleFullscreen");
            if (toggleFullscreenCommand) {
                toggleFullscreenCommand.fullscreenHandler = new InlineEditorHandler(this.editor as InlineEditor);
            }
        }
    }

    /**
     * Be default the <body> element is used to check if the entire dropdown is visible when open.
     * This is problematic when we have either the side menu or the notifications panel open as
     * part of the dropdown may appear behind them. To resolve we pass in the #content element
     * into the getOptimalPosition method used.
     */
    updateDropdownPositions(): void {
        const toolbarItems = (this.editor as ClassicEditor).ui.view.toolbar.items as ViewCollection<DropdownView>;
        const dropDownItems = toolbarItems.filter((item) => item.panelPosition !== null); // get only those toolbar items that are dropdowns
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
     * @param item - the dropdown item
     */
    updateDropdownPosition(item: DropdownView): void {
        item.on("change:isOpen", (eventInfo, name, isOpen) => {
            if (!isOpen) {
                return;
            }
            /**
             * We also need to account for dropdowns within dropdowns.
             */
            if (item.toolbarView) {
                const innerItems = item.toolbarView.itemsView.children as ViewCollection<DropdownView>;
                const innerDropDownItems = innerItems.filter(($item) => $item.panelPosition !== null); // get only those toolbar items that are dropdowns
                innerDropDownItems.forEach(($item) => {
                    this.updateDropdownPosition($item);
                });
            }
            if (item.panelView.element === null || item.buttonView.element === null) {
                return;
            }
            const { left, top } = getOptimalPosition({
                element: item.panelView.element,
                target: item.buttonView.element.getBoundingClientRect(),
                fitInViewport: true,
                limiter: document.getElementById("content"),
                // @ts-expect-error - Accessing the private property is intentional here, for now
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment -- is any as cant access private property
                positions: item._panelPositions,
            }) as DomPoint;
            item.panelView.element.style.left = `${left}px`;
            item.panelView.element.style.top = `${top}px`;
        });
    }

    /**
     * So we link to existing resources within SBX, we add a browse button within the link UI that
     * opens up our resource modal. When a resource is selected, the modal will close and the
     * resource will be linked into the Link URL text input
     */
    addLinkBrowseButton(): void {
        const linkUI = this.editor.plugins.get(LinkUI);
        const contextualBalloonPlugin = this.editor.plugins.get("ContextualBalloon");
        // listen to the link ui 'balloon' opening, so we can add the button
        this.listenTo(contextualBalloonPlugin, "change:visibleView", (event, name, visibleView) => {
            if (visibleView === linkUI.formView) {
                // only need to add the button once, so remove the listener
                this.stopListening(contextualBalloonPlugin, "change:visibleView");
                const button = new ButtonView(this.editor.locale);
                button.set({
                    label: "Browse",
                    withText: true,
                    tooltip: true,
                    class: "ck-button-resource-link",
                });
                // Open up the resource browser when clicking the "Browse" button
                button.on("execute", () => this.editor.execute("insertResourceLink"));
                button.render();
                // add the button into the link form view
                const linkFormView = linkUI.formView;
                if (linkFormView && linkFormView.urlInputView.fieldView.element) {
                    // register the button under the link form view, so it's also managed within the view instance
                    linkFormView.registerChild(button);
                    // add the resource button after the url input
                    const referenceElement = linkFormView.urlInputView.fieldView.element?.parentElement?.parentElement;
                    if (referenceElement) {
                        insertElementAfter(referenceElement, button.element as HTMLElement);
                    }
                    /**
                     * Let's listen for the same event as packages/ckeditor5-ui/src/bindings/clickoutsidehandler.ts does,
                     * but with higher priority.
                     * It's important that the same emitter is used as by LinkUI (`formView`), because native DOM Events
                     * are translated by packages/ckeditor5-utils/src/dom/emittermixin.ts. If different emitter were used,
                     * we'd get different, non-related events.
                     */
                    linkFormView.listenTo(
                        document,
                        "mousedown",
                        (mouseDownEvent) => {
                            // while the resource modal is open, prevent the link ui from hiding
                            const resourceLinkCommand = this.editor.commands.get(
                                "insertResourceLink",
                            ) as InsertResourceLinkCommand;
                            if (resourceLinkCommand && resourceLinkCommand.isOn) {
                                mouseDownEvent.stop();
                            }
                        },
                        { priority: "high" },
                    );
                }
            }
        });
    }
}
