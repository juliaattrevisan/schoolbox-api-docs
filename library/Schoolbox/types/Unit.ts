/**
 * The start of a SBX Unit type
 */
export type Unit = {
    id: number;
    name: string;
    folder?: {
        _links: {
            view: string; // URL to view the folder
        };
    };
};
