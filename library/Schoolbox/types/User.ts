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
    fullName: string;
    givenName: string;
    id: number;
    preferredName?: string | null;
    title: string | null;
    pronoun: string | null;
    _links?: UserLinks;
};

/**
 * there's currently a mix of `lastname` and `lastName` in the codebase...
 * there has got to be a better way to handle this, but for now have another type so that we know what to expect
 */
export type UserCC = Omit<User, "firstname" | "lastname"> & {
    firstName: string;
    lastName: string;
};
