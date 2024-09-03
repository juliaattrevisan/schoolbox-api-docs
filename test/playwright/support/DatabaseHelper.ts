import { createPool, Pool, RowDataPacket, ResultSetHeader } from "mysql2/promise";

export interface DataPairs {
    [key: string]: string | number | boolean | null;
}

export default class DatabaseHelper {
    private pool: Pool;

    /**
     * Initialises the database connection pool
     */
    constructor() {
        this.pool = createPool({
            host: "vagrant.dev.schoolbox.cloud",
            user: "root",
            password: "tnp879",
            database: "schoolbox",
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0,
        });
    }

    /**
     * Query the database
     * @param table - Table name
     * @param columns - Columns to select [optional, default=*]
     * @param criteria - Search criteria [optional, default={}]
     * @returns - Query results
     */
    private async queryDatabase(
        table: string,
        columns: string = "*",
        criteria: DataPairs = {},
    ): Promise<RowDataPacket[]> {
        const criteriaKeys = Object.keys(criteria);
        const criteriaValues = Object.values(criteria);

        const whereBinding = criteriaKeys
            .map((key) => {
                const value = criteria[key];
                // Check for NULL specifically to handle IS NULL and IS NOT NULL
                if (value === null) {
                    return `${key} IS NULL`;
                }
                if (value === "NOT NULL") {
                    return `${key} IS NOT NULL`;
                }
                // If key has a space, don't include = sign as we assume it's a custom assignment such as "column LIKE"
                if (key.includes(" ")) return `${key} ?`;
                return `${key} = ?`;
            })
            .join(" AND ");
        const whereClause = criteriaKeys.length > 0 ? `WHERE ${whereBinding}` : "";

        // Remove NULL and NOT NULL values from the criteria values, as we override them in the where clause
        const valuesBinding = criteriaValues.filter((value) => value !== null && value !== "NOT NULL");

        const [results] = await this.pool.execute<RowDataPacket[]>(
            `SELECT ${columns} FROM ${table} ${whereClause}`,
            valuesBinding,
        );
        return results;
    }

    /**
     * Count the number of rows in a table
     * @param table - Table name
     * @param criteria - Search criteria [optional, default={}]
     * @returns - Number of rows
     */
    async countInDatabase(table: string, criteria: DataPairs = {}): Promise<number> {
        const results = await this.queryDatabase(table, "COUNT(*) as count", criteria);
        return results[0] ? (results[0].count as number) : 0;
    }

    /**
     * Inserts an SQL record into the database
     * @param table - Table name
     * @param data - Data to insert
     * @returns - ID of the inserted record
     */
    async haveInDatabase(table: string, data: DataPairs): Promise<number> {
        const keys = Object.keys(data);
        const values = Object.values(data);
        const placeholders = keys.map(() => "?").join(", ");

        const [result] = await this.pool.execute<ResultSetHeader>(
            `INSERT INTO ${table} (${keys.join(", ")}) VALUES (${placeholders})`,
            values,
        );
        return result.insertId;
    }

    /**
     * Updates an SQL record in the database
     * @param table - Table name
     * @param data - Data to update
     * @param criteria - Search criteria [optional, default={}]
     */
    async updateInDatabase(table: string, data: DataPairs, criteria: DataPairs = {}): Promise<void> {
        const dataKeys = Object.keys(data).map((key) => `${key} = ?`);
        const criteriaKeys = Object.keys(criteria).map((key) => {
            const value = criteria[key];
            // Check for NULL specifically to handle IS NULL and IS NOT NULL
            if (value === null) {
                return `${key} IS NULL`;
            }
            if (value === "NOT NULL") {
                return `${key} IS NOT NULL`;
            }
            // If key has a space, don't include = sign as we assume it's a custom assignment such as "column LIKE"
            if (key.includes(" ")) return `${key} ?`;
            return `${key} = ?`;
        });
        const values = [
            ...Object.values(data),
            ...Object.values(criteria).filter((value) => value !== null && value !== "NOT NULL"),
        ];

        await this.pool.execute(
            `UPDATE ${table} SET ${dataKeys.join(", ")} WHERE ${criteriaKeys.join(" AND ")}`,
            values,
        );
    }

    /**
     * Fetches all values from a column in the database
     * @param table - Table name
     * @param column - Column name
     * @param criteria - Search criteria [optional, default={}]
     * @returns - Returns an array of values from the column
     */
    async grabColumnFromDatabase(table: string, column: string, criteria: DataPairs = {}): Promise<unknown[]> {
        const results = await this.queryDatabase(table, column, criteria);
        return results.map((result) => result[column] as unknown);
    }

    /**
     * Fetches a single column value from the database
     * @param table - Table name
     * @param column - Column name
     * @param criteria - Search criteria [optional, default={}]
     * @returns - Returns the value of the column, or null if not found
     */
    async grabFromDatabase(table: string, column: string, criteria: DataPairs = {}): Promise<unknown> {
        const results = await this.grabColumnFromDatabase(table, column, criteria);
        return results[0] || null;
    }

    /**
     * Closes the database connection
     */
    async closePool(): Promise<void> {
        await this.pool.end();
    }
}
