/**
 * The start of a SBX User type
 */
export type User = {
    externalId: number;
    firstname: string;
    fullName: string;
    givenName: string;
    id: number;
    lastname: string;
    preferredName: string | null;
    title: string | null;
};
