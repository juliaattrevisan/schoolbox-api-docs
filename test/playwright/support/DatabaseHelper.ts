import type { Pool, RowDataPacket, ResultSetHeader } from "mysql2/promise";
import { createPool } from "mysql2/promise";

type DataPairValue = string | number | boolean | null;

// For use when creating search criteria. An array of values is treated as an OR condition
type CriteriaPairs = Record<string, DataPairValue | DataPairValue[]>;

// For use when inserting or updating data, each key is a column name with a single value to set
type DataPairs = Record<string, DataPairValue>;

export default class DatabaseHelper {
    private pool: Pool;

    /**
     * Initialises the database connection pool
     */
    constructor() {
        // There are different connection details for CI vs local vagrant
        this.pool = createPool({
            host: "vagrant.dev.schoolbox.cloud",
            user: process.env.CI ? "schoolboxvagrant" : "root",
            password: process.env.CI ? "abc123" : "tnp879",
            database: "schoolbox",
            waitForConnections: true,
            connectionLimit: 10,
            queueLimit: 0,
        });
    }

    /**
     * Convert data pairs into a WHERE clause
     * @param criteria - Search criteria
     *   - Each CriteriaPairs object is treated as a union (OR) between any other CriteriaPairs object
     *   - Each entry of a CriteriaPairs object is treated as an intersection (AND) of the criteria
     * @returns
     *   - clause: The WHERE clause to use in the SQL query
     *   - binding: The values to bind to the WHERE clause
     */
    private static buildWhereClause(criteria: CriteriaPairs | CriteriaPairs[]): {
        clause: string;
        binding: DataPairValue[];
    } {
        const pairs = Array.isArray(criteria) ? criteria : [criteria];

        const whereClauses: string[] = [];
        const valuesBinding: DataPairValue[] = [];

        pairs.forEach((pair) => {
            const criteriaKeys = Object.keys(pair);
            const criteriaValues = Object.values(pair);

            if (criteriaKeys.length === 0) {
                return;
            }

            const clause = criteriaKeys
                .map((key) => {
                    const value = pair[key];
                    // Check for NULL specifically to handle IS NULL and IS NOT NULL
                    if (value === null) {
                        return `${key} IS NULL`;
                    }
                    if (value === "NOT NULL") {
                        return `${key} IS NOT NULL`;
                    }
                    if (Array.isArray(value)) {
                        return `${key} IN (${value.map(() => "?").join(", ")})`;
                    }
                    // If key has a space, don't include = sign as we assume it's a custom assignment such as "column LIKE"
                    if (key.includes(" ")) return `${key} ?`;
                    return `${key} = ?`;
                })
                .join(" AND ");

            whereClauses.push(`(${clause})`);

            criteriaValues.forEach((value) => {
                // Remove NULL and NOT NULL values from the criteria values, as we override them in the where clause
                if (value === null || value === "NOT NULL") return;

                // If the value is an array push each value into the valuesBinding array
                if (Array.isArray(value)) {
                    valuesBinding.push(...value);
                    return;
                }

                valuesBinding.push(value);
            });
        });

        if (whereClauses.length === 0) {
            return { clause: "", binding: [] };
        }

        return { clause: `WHERE ${whereClauses.join(" OR ")}`, binding: valuesBinding };
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
        criteria: CriteriaPairs | CriteriaPairs[] = {},
    ): Promise<RowDataPacket[]> {
        const where = DatabaseHelper.buildWhereClause(criteria);

        const [results] = await this.pool.execute<RowDataPacket[]>(
            `SELECT ${columns} FROM ${table} ${where.clause}`,
            where.binding,
        );
        return results;
    }

    /**
     * Count the number of rows in a table
     * @param table - Table name
     * @param criteria - Search criteria [optional, default={}]
     * @returns - Number of rows
     */
    async countInDatabase(table: string, criteria: CriteriaPairs | CriteriaPairs[] = {}): Promise<number> {
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
     * Deletes an SQL record from the database
     * @param table - Table name
     * @param criteria - Search criteria [optional, default={}]
     */
    async dontHaveInDatabase(table: string, criteria: CriteriaPairs | CriteriaPairs[] = {}): Promise<void> {
        const where = DatabaseHelper.buildWhereClause(criteria);
        await this.pool.execute(`DELETE FROM ${table} ${where.clause}`, where.binding);
    }

    /**
     * Updates an SQL record in the database
     * @param table - Table name
     * @param data - Data to update
     * @param criteria - Search criteria [optional, default={}]
     */
    async updateInDatabase(
        table: string,
        data: DataPairs,
        criteria: CriteriaPairs | CriteriaPairs[] = {},
    ): Promise<void> {
        const dataKeys = Object.keys(data).map((key) => `${key} = ?`);
        const where = DatabaseHelper.buildWhereClause(criteria);

        const values = [...Object.values(data), ...where.binding];

        await this.pool.execute(`UPDATE ${table} SET ${dataKeys.join(", ")} ${where.clause}`, values);
    }

    /**
     * Fetches all values from a column in the database
     * @param table - Table name
     * @param column - Column name
     * @param criteria - Search criteria [optional, default={}]
     * @returns - Returns an array of values from the column
     */
    async grabColumnFromDatabase(
        table: string,
        column: string,
        criteria: CriteriaPairs | CriteriaPairs[] = {},
    ): Promise<unknown[]> {
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
    async grabFromDatabase(
        table: string,
        column: string,
        criteria: CriteriaPairs | CriteriaPairs[] = {},
    ): Promise<unknown> {
        const results = await this.grabColumnFromDatabase(table, column, criteria);
        return results[0] || null;
    }

    /**
     * Closes the database connection
     */
    async closePool(): Promise<void> {
        await this.pool.end();
    }

    /**
     * Create a backup of a table
     * @param table - Table name
     */
    async backupTable(table: string): Promise<void> {
        const backupTableName = `${table}_backup`;
        await this.pool.execute(`RENAME TABLE ${table} TO ${backupTableName}`);
        await this.pool.execute(`CREATE TABLE ${table} LIKE ${backupTableName}`);
        await this.pool.execute(`INSERT INTO ${table} SELECT * FROM ${backupTableName}`);
    }

    /**
     * Restore a table from a backup
     * @param table - Table name
     */
    async restoreTable(table: string): Promise<void> {
        const backupTableName = `${table}_backup`;
        await this.pool.execute(`DROP TABLE IF EXISTS ${table}`);
        await this.pool.execute(`RENAME TABLE ${backupTableName} TO ${table}`);
    }
}
