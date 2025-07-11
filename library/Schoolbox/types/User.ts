/**
 * Auto generated links provided for the User
 */
type UserLinks = {
    avatar?: string;
    profile?: string;
};

/**
 * The start of a SBX User type
 */
export type User = {
    externalId?: number;
    firstname: string;
    firstName: string; // there's currently a mix of `firstname` and `firstName` in the codebase...
    fullName: string;
    givenName: string;
    id: number;
    lastname: string;
    lastName: string; // there's currently a mix of `lastname` and `lastName` in the codebase...
    preferredName?: string | null;
    title: string | null;
    pronoun: string | null;
    _links?: UserLinks;
};
