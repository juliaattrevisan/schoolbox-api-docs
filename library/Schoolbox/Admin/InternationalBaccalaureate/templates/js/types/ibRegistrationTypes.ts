export type RegistrationSession = {
    id: number;
    title: string;
    academicYear: string;
    examSession: "May" | "November";
    createdAt: string;
    createdBy: string | null;
    deleteEndpoint: string | null;
};
