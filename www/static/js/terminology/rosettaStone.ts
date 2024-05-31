/**
 * Utilities to help with managing terminology
 * This is a class to manage an overall dictionary of L10n terms, it effectively aligns with library/Schoolbox/L10n/RosettaStone.php
 */
import { IL10n, L10n } from "@js/terminology/l10n";

/**
 * A class to manage a dictionary of terms
 */
export class RosettaStone {
    private dictionary: Record<string, L10n> = {};

    /**
     * Initialise the RosettaStone object
     * @param {IL10n[]} terms - The terms to add to the dictionary
     */
    constructor(terms: IL10n[] = []) {
        terms.forEach((term) => {
            this.addTerm(term);
        });
    }

    /**
     * Add a term to the dictionary
     * @param {IL10n} term - The term to add
     * @returns {RosettaStone} The RosettaStone object
     */
    addTerm(term: IL10n): RosettaStone {
        this.dictionary[term.term] = new L10n(term);
        return this;
    }

    /**
     * Add multiple terms to the dictionary
     * @param {IL10n[]} terms - The terms to add
     * @returns {RosettaStone} The RosettaStone object
     */
    addTerms(terms: IL10n[]): RosettaStone {
        terms.forEach((term) => {
            this.addTerm(term);
        });
        return this;
    }

    /**
     * Translate a term
     * @param {string} term - The term to translate
     * @param {boolean} plural - Whether to pluralise the term
     * @param {boolean} capital - Whether to capitalise the term
     * @returns {string} The translated term
     */
    translate(term: string, plural: boolean = false, capital: boolean = false): string {
        return this.get(term).translate(plural, capital);
    }

    /**
     * Get the indefinite article for a term
     * @param {string} term - The term to get the article for
     * @param {boolean} capital - Whether to capitalise the article
     * @returns {string} The indefinite article
     */
    indefiniteArticle(term: string, capital: boolean = false): string {
        return this.get(term).translateIndefiniteArticle(capital);
    }

    /**
     * Find a term in the dictionary
     * @param {string} term - The term to find
     * @returns {L10n} The relevant L10n object
     * @throws {Error} If the term is not loaded in the dictionary
     */
    get(term: string): L10n {
        if (this.dictionary[term] === undefined) {
            throw new Error(`Term "${term}" not in localisation table`);
        }

        return this.dictionary[term];
    }
}
