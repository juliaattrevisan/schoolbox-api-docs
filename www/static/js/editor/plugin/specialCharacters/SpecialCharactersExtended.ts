import { type Editor } from "ckeditor5";

/**
 * Adds extended mathematical special characters to the SpecialCharacters plugin.
 * Extends the default Mathematical category with additional useful symbols.
 * @param editor - the editor instance to add the special characters to
 */
export default function SpecialCharactersExtended(editor: Editor): void {
    editor.plugins.get("SpecialCharacters").addItems("Mathematical", [
        { title: "Pi", character: "π" },
        { title: "Greek small letter alpha", character: "α" },
        { title: "Greek small letter beta", character: "β" },
        { title: "Greek small letter gamma", character: "γ" },
        { title: "Greek small letter delta", character: "δ" },
        { title: "Greek small letter epsilon", character: "ε" },
        { title: "Greek small letter theta", character: "θ" },
        { title: "Greek small letter lambda", character: "λ" },
        { title: "Greek small letter mu", character: "μ" },
        { title: "Greek small letter sigma", character: "σ" },
        { title: "Greek small letter phi", character: "φ" },
        { title: "Greek small letter omega", character: "ω" },
        { title: "Greek capital letter delta", character: "Δ" },
        { title: "Greek capital letter sigma", character: "Σ" },
        { title: "Greek capital letter phi", character: "Φ" },
        { title: "Greek capital letter omega", character: "Ω" },
        { title: "Subset of", character: "⊂" },
        { title: "Superset of", character: "⊃" },
        { title: "Subset of or equal to", character: "⊆" },
        { title: "Superset of or equal to", character: "⊇" },
        { title: "Not a subset of", character: "⊄" },
        { title: "Not a superset of", character: "⊅" },
        { title: "Therefore", character: "∴" },
        { title: "Because", character: "∵" },
        { title: "Perpendicular", character: "⊥" },
        { title: "Parallel to", character: "∥" },
        { title: "Not parallel to", character: "∦" },
        { title: "Sine wave", character: "∿" },
        { title: "Left floor", character: "⌊" },
        { title: "Right floor", character: "⌋" },
        { title: "Left ceiling", character: "⌈" },
        { title: "Right ceiling", character: "⌉" },
        { title: "End of proof", character: "∎" },
        { title: "Ring operator", character: "∘" },
        { title: "Circled plus", character: "⊕" },
        { title: "Circled times", character: "⊗" },
        { title: "Circled dot operator", character: "⊙" },
        { title: "Minus-plus sign", character: "∓" },
        { title: "Ratio", character: "∶" },
        { title: "Proportion", character: "∷" },
    ]);
}
