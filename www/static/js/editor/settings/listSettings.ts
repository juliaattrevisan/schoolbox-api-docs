/**
 * As <p> tags with <li> are stripped from CKditor5, we should remove them prior to their instertion.
 * Otherwise the <li> tags are stripped instead, which results in the loss of the list.
 * @param { HTMLElement } sourceElementHTML - the sourceElement content as HTML
 * @returns { HTMLElement } - the html with the <p> tags removed from the <li> tags
 */
export function removeParagraphsInLists(sourceElementHTML: HTMLElement): HTMLElement {
    const listItems = sourceElementHTML.querySelectorAll("li");
    listItems.forEach((listItem) => {
        // only check for <p> tags if there are no nested lists
        if (
            listItem.querySelector("ul") === null &&
            listItem.querySelector("ol") === null &&
            listItem.querySelector("p")
        ) {
            const paragraphs = listItem.getElementsByTagName("p");
            // first insert a <br> tag after each <p> tag
            for (let i = 0; i < paragraphs.length; i += 1) {
                const p = paragraphs[i];
                if (i < paragraphs.length - 1) {
                    const br = document.createElement("br");
                    listItem.insertBefore(br, p.nextSibling);
                }
            }
            // then strip the <p> tags
            for (let i = paragraphs.length - 1; i >= 0; i -= 1) {
                const p = paragraphs[i];
                while (p.firstChild) {
                    listItem.insertBefore(p.firstChild, p);
                }
                listItem.removeChild(p);
            }
        }
    });
    return sourceElementHTML;
}
