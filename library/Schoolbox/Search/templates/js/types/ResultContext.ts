/**
 * To provide context where the result is from
 */
export type ResultContext = {
    id: number;
    name: string;
    title: string;
    codes: number[];
    parent: {
        id: number;
    };
    _links: {
        view: string;
    };
};
