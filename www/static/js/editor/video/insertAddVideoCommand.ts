import Command from "@ckeditor/ckeditor5-core/src/command";

export default class InsertAddVideoCommand extends Command {
    execute() {
        this.editor.model.change((writer) => {
            // Insert <simpleBox>*</simpleBox> at the current selection position
            // in a way that will result in creating a valid model structure.
            this.editor.model.insertObject(createAddVideo(writer));
        });
    }

    refresh() {
        const model = this.editor.model;
        const selection = model.document.selection;
        const allowedIn = model.schema.findAllowedParent(selection.getFirstPosition(), "addVideo");

        // this.isEnabled = allowedIn !== null;
        this.isEnabled = true;
    }
}

function createAddVideo(writer) {
    const addVideo = writer.createElement("sbVideo");
    return addVideo;
}
