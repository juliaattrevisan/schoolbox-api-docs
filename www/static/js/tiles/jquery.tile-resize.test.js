global.jQuery = global.$;
window.jQuery = global.$;

require("./jquery.tile-resize");

describe("fixTileHeightAndFontSize", () => {
    afterEach(() => {
        document.body.innerHTML = "";
    });

    it("sets row gap from the tile list width and column gap percentage", () => {
        document.body.innerHTML = `
            <ul data-tile-list data-aspect-ratio="0.5" data-column-gap-percentage="10" style="width: 600px;">
                <li class="tile" data-min-font-size="0.75" data-max-font-size="1.75" style="width: 180px;">
                    <span class="title">Title</span>
                </li>
            </ul>
        `;

        const tile = document.querySelector(".tile");
        const tileList = document.querySelector("[data-tile-list]");

        $(tile).fixTileHeightAndFontSize();

        expect(tileList.style.rowGap).toBe("60px");
    });
});
