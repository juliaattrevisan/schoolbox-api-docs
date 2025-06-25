/*
 * Equation editor balloon view for CKEditor version 5
 *
 * This file dictates the appearance of the Equation Editor component that appears
 * when a user clicks the icon to insert an equation.
 */

import { View, ButtonView, submitHandler, IconView, IconCheck, IconCancel } from "ckeditor5";

export default class EqnEditorView extends View {
    constructor(locale) {
        super(locale);
        const iconCheck = new IconView();
        iconCheck.content = IconCheck;
        this.saveButtonView = this._createButton("Save", iconCheck.content, "ck-button-save");
        this.saveButtonView.type = "submit";
        const iconCancel = new IconView();
        iconCancel.content = IconCancel;
        this.cancelButtonView = this._createButton("Cancel", iconCancel.content, "ck-button-cancel");
        // Delegate ButtonView#execute to FormView#cancel.
        this.cancelButtonView.delegate("execute").to(this, "cancel");

        this.setTemplate({
            tag: "form",
            attributes: {
                class: ["ck", "ck-abbr-form"],
                tabindex: "-1",
            },
            children: [
                {
                    tag: "div",
                    attributes: {
                        id: "equation-editor",
                    },
                    children: [
                        {
                            tag: "div",
                            attributes: {
                                id: "history",
                            },
                        },
                        {
                            tag: "div",
                            attributes: {
                                id: "toolbar",
                            },
                        },
                        {
                            tag: "div",
                            attributes: {
                                id: "latexInput",
                                autocorrect: "off",
                            },
                        },
                        {
                            tag: "div",
                            attributes: {
                                id: "equation-output",
                            },
                            children: [
                                {
                                    tag: "img",
                                    attributes: {
                                        id: "output",
                                    },
                                },
                            ],
                        },
                    ],
                },
                this.saveButtonView,
                this.cancelButtonView,
            ],
        });
    }

    _createButton(label, icon, className) {
        const button = new ButtonView();

        button.set({
            label,
            icon,
            withText: true,
            tooltip: true,
            class: className,
        });

        return button;
    }

    render() {
        super.render();

        submitHandler({
            view: this,
        });
    }
}
