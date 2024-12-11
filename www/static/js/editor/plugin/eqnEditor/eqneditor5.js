/*
 * Main entrypoint for Codecogs Equation Editor plugin
 * Use this plugin in your main CKEditor JS file like:
 *
 * import Eqneditor5 from 'PATH/TO/DIR/eqneditor5';
 *
 * ClassicEditor
 *	.create( document.querySelector( '#editor' ), {
 *		plugins: [
 *			Eqneditor5,
 *			...
 * 		],
 * 		toolbar: [
 * 			'eqneditor5Button',
 * 			...
 * 		]
 * 		...
 */

import { Plugin } from "@ckeditor/ckeditor5-core/src";
import { ButtonView, ContextualBalloon, clickOutsideHandler } from "@ckeditor/ckeditor5-ui";
import DomEventObserver from "@ckeditor/ckeditor5-engine/src/view/observer/domeventobserver";
import { default as EqnEditorView } from "./dialog";
const fxIcon =
    '<svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="18px" height="20px" viewBox="0 0 18 20" enable-background="new 0 0 18 20" xml:space="preserve"><rect x="4.4" y="5.1" fill="none" width="38.6" height="43.4"/><g enable-background="new"><path d="M4,9.5H2L2.3,7h2.1l0.3-1.8C4.7,4.6,4.9,4.1,5,3.6c0.2-0.5,0.4-0.9,0.7-1.3c0.3-0.4,0.8-0.7,1.3-0.9C7.7,1.1,8.4,1,9.2,1c0.3,0,0.6,0,0.9,0c0.3,0,0.6,0.1,0.9,0.1l-0.5,2.7c-0.2-0.1-0.3-0.1-0.5-0.2c-0.1,0-0.3-0.1-0.5-0.1C9,3.7,8.6,3.8,8.4,4C8.1,4.2,7.9,4.7,7.8,5.4L7.6,7h2.3L9.5,9.5H7.2l-1.1,7.7H3L4,9.5z"/></g><g enable-background="new"><path d="M11.1,15.3l-2.3-3.2h2.5l1.2,1.8l1.2-1.8H16l-2.2,3.2l2.6,3.6h-2.5l-1.5-2.3L10.8,19H8.4L11.1,15.3z"/></g></svg>';

class DoubleClickObserver extends DomEventObserver {
    constructor(view) {
        super(view);

        this.domEventType = "dblclick";
    }

    onDomEvent(domEvent) {
        this.fire(domEvent.type, domEvent);
    }
}

export default class Eqneditor5 extends Plugin {
    static get pluginName() {
        return "Eqneditor5";
    }

    init() {
        const editor = this.editor;

        var host = "latex.codecogs.com";
        var http = "https:" == document.location.protocol ? "https://" : "http://";

        this._installExtScript(http + host + "/js/eqneditor.api.min.js");
        this._installExtCSS(http + host + "/css/eqneditor_1.css");

        // Create the balloon and the form view.
        this._balloon = this.editor.plugins.get(ContextualBalloon);
        this.eqnView = this._createEqnEditorView();

        // Add the "eqneditor5Button" to feature components.
        editor.ui.componentFactory.add("eqneditor5Button", (locale) => {
            const view = new ButtonView(locale);

            view.set({
                label: editor.t("Insert Equation"),
                icon: fxIcon,
                tooltip: true,
            });

            // Insert a text into the editor after clicking the button.
            this.listenTo(view, "execute", () => {
                this.eqnView = this._createEqnEditorView();
                this._initEqnEditor(locale);
            });

            return view;
        });

        // Listen for double click
        const view = editor.editing.view;
        const viewDocument = view.document;

        view.addObserver(DoubleClickObserver);
        editor.listenTo(
            viewDocument,
            "dblclick",
            function (e, dat) {
                console.log(dat);
                if (dat.domTarget.nodeName.toLowerCase() == "img") {
                    var sName = dat.domTarget.src.match(/(gif|svg)\.image\?(.*)/);
                    if (sName != null) this._initEqnEditor(null, sName[2]);
                }
            }.bind(this),
        );
    }

    _installExtScript(url) {
        var scriptTag = document.createElement("script");
        scriptTag.src = url;
        scriptTag.type = "text/javascript";
        document.head.appendChild(scriptTag);
    }

    _installExtCSS(url) {
        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = url;
        document.head.appendChild(link);
    }

    _initEqnEditor(locale, inp = "") {
        this._showUI(locale);

        this.output = new EqEditor.Output("output");
        this.textarea = EqEditor.TextArea.link("latexInput")
            .addOutput(this.output)
            .addHistoryMenu(new EqEditor.History("history"));

        EqEditor.Toolbar.link("toolbar").addTextArea(this.textarea);

        var nodes = document.getElementById("history").childNodes;
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].style.padding = "revert";
        }

        this.textarea.clear();
        this.textarea.insert(inp.replace(/&space;/g, " "), inp.length);
    }

    _createEqnEditorView() {
        const editor = this.editor;
        const view = new EqnEditorView(editor.locale);

        this.listenTo(view, "submit", () => {
            const viewFragment = this.editor.data.processor.toView(
                // The output.exportAs() function has an 'html' export format, but that format uses an unencoded
                // URL in its <img> element.  This causes problems when the HTML is saved by the CKEditor.
                // To work around this bug in the 'html' export format, we must create our own html export.
                // @see https://editor.codecogs.com/docs/2-API.php#exportAs
                "<img src='" + this.output.exportAs("urlencoded") + "' alt='Math formula'>",
            );
            const modelFragment = this.editor.data.toModel(viewFragment);
            this.editor.model.insertContent(modelFragment);

            this.textarea.pushToHistory();

            // Hide the form view after submit.
            this._hideUI();
        });

        // Hide the form view after clicking the "Cancel" button.
        this.listenTo(view, "cancel", () => {
            this._hideUI();
        });

        // Hide the form view when clicking outside the balloon.
        clickOutsideHandler({
            emitter: view,
            activator: () => this._balloon.visibleView === view,
            contextElements: () => [this._balloon.view.element],
            callback: () => this._hideUI(),
        });

        return view;
    }

    _hideUI() {
        // Hack to avoid repeated adding when editor is clicked multiple times
        document.getElementById("history").innerHTML = "";
        document.getElementById("toolbar").innerHTML = "";
        document.getElementById("output").src = "";

        this.textarea.clear();

        this.eqnView.element.reset();

        this._balloon.remove(this.eqnView);

        // Focus the editing view after closing the form view.
        this.editor.editing.view.focus();
    }

    _getBalloonPositionData() {
        const view = this.editor.editing.view;
        const viewDocument = view.document;
        let target = null;

        // Set a target position by converting view selection range to DOM.
        target = () => view.domConverter.viewRangeToDom(viewDocument.selection.getFirstRange());

        return {
            target,
        };
    }

    _showUI(locale) {
        // const balloon = this.editor.plugins.get( ContextualBalloon );

        this._balloon.add({
            view: this.eqnView,
            position: this._getBalloonPositionData(),
        });

        // HACK: Causes jumpy window behaviour, but technically works
        // TODO: Find out why positioning is initially wrong
        setTimeout(() => {
            window.dispatchEvent(new Event("resize"));
        }, 0);
    }
}
