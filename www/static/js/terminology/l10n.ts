/**
 * Utilities to help with managing terminology
 * This file creates a model for localisation objects that effectively aligns with library/Schoolbox/L10n/Model.php
 */
import { startCase, lowerFirst } from "lodash";

/**
 * The localisation object expected from the backend
 */
export type IL10n = {
    term: string;
    title: string;
    singularForm: string;
    pluralForm: string;
    capitaliseFirst: boolean;
    indefiniteArticle: string;
};

export class L10n {
    protected term: string;

    protected title: string;

    protected singularForm: string;

    protected pluralForm: string;

    protected capitaliseFirst: boolean;

    protected indefiniteArticle: string;

    /**
     * Initialise the L10n object
     * @param l10n - The localisation object to use (from the backend)
     */
    constructor(l10n: IL10n) {
        this.term = l10n.term;
        this.title = l10n.title;
        this.singularForm = l10n.singularForm;
        this.pluralForm = l10n.pluralForm;
        this.capitaliseFirst = l10n.capitaliseFirst;
        this.indefiniteArticle = l10n.indefiniteArticle;
    }

    /**
     * Get the term
     * @returns The term
     */
    getTerm(): string {
        return this.term;
    }

    /**
     * Get the title
     * @returns The title
     */
    getTitle(): string {
        return this.title;
    }

    /**
     * Get the singular form
     * @returns The singular form
     */
    getSingularForm(): string {
        return this.singularForm;
    }

    /**
     * Get the plural form
     * @returns The plural form
     */
    getPluralForm(): string {
        return this.pluralForm;
    }

    /**
     * Get whether to capitalise the first letter
     * @returns Whether to capitalise the first letter
     */
    getCapitaliseFirst(): boolean {
        return this.capitaliseFirst;
    }

    /**
     * Get the indefinite article
     * @returns The indefinite article
     */
    getIndefiniteArticle(): string {
        return this.indefiniteArticle;
    }

    /**
     * Helper function to pluralise/capitalise a term as required
     * @param plural - Whether to pluralise the term
     * @param capitalise - Whether to capitalise the term
     * @returns The new term
     */
    translate(plural: boolean, capitalise: boolean): string {
        let term = plural ? this.pluralForm : this.singularForm;

        if (capitalise) {
            term = startCase(term);
            if (!this.capitaliseFirst) {
                term = lowerFirst(term);
            }
        }

        return term;
    }

    /**
     * Helper for translating an indefinite article
     * @param capitalise - Whether to capitalise the article
     * @returns The new article
     */
    translateIndefiniteArticle(capitalise: boolean): string {
        let article = this.indefiniteArticle;
        if (capitalise) {
            article = startCase(article);
        }
        return article;
    }

    /**
     * Get the singular term (Default behaviour is singular, lowercase)
     * @returns The term, e.g. "attempt history"
     */
    singular(): string {
        return this.translate(false, false);
    }

    /**
     * Get the proper term
     * @returns The proper term, e.g. "Attempt History"
     */
    proper(): string {
        return this.translate(false, true);
    }

    /**
     * Get the plural term
     * @returns The plural term, e.g. "attempt histories"
     */
    plural(): string {
        return this.translate(true, false);
    }

    /**
     * Get the proper plural term
     * @returns The proper plural term e.g. "Attempt Histories"
     */
    properPlural(): string {
        return this.translate(true, true);
    }
}
