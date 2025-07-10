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
    fullName: string;
    givenName: string;
    id: number;
    lastname: string;
    preferredName?: string | null;
    title: string | null;
    pronoun: string | null;
    _links?: UserLinks;
};
