/**
 * The start of a SBX Unit type
 */
export type Unit = {
    id: number;
    title: string;
    folder?: {
        id: number;
        _links: {
            view: string; // URL to view the folder
        };
    };
};
