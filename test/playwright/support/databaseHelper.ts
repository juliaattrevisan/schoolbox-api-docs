import { createPool, Pool, RowDataPacket, ResultSetHeader } from "mysql2/promise";

export interface DataPairs {
    [key: string]: string | number | boolean | null;
}

export class DatabaseHelper {
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
     * @param { string } table - Table name
     * @param { string= } columns - Columns to select [optional, default=*]
     * @param { DataPairs= } criteria - Search criteria [optional, default={}]
     * @returns { Promise<RowDataPacket[]> } - Query results
     */
    private async queryDatabase(
        table: string,
        columns: string = "*",
        criteria: DataPairs = {},
    ): Promise<RowDataPacket[]> {
        const criteriaKeys = Object.keys(criteria);
        const criteriaValues = Object.values(criteria);

        const whereBinding = criteriaKeys.map((key) => `${key} = ?`).join(" AND ");
        const whereClause = criteriaKeys.length > 0 ? `WHERE ${whereBinding}` : "";

        const [results] = await this.pool.execute<RowDataPacket[]>(
            `SELECT ${columns} FROM ${table} ${whereClause}`,
            criteriaValues,
        );
        return results;
    }

    /**
     * Count the number of rows in a table
     * @param { string } table - Table name
     * @param { DataPairs= } criteria - Search criteria [optional, default={}]
     * @returns { Promise<number> } - Number of rows
     */
    async countInDatabase(table: string, criteria: DataPairs = {}): Promise<number> {
        const results = await this.queryDatabase(table, "COUNT(*) as count", criteria);
        return results[0] ? (results[0].count as number) : 0;
    }

    /**
     * Inserts an SQL record into the database
     * @param { string } table - Table name
     * @param { DataPairs } data - Data to insert
     * @returns { Promise<number> } - ID of the inserted record
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
     * @param { string } table - Table name
     * @param  { DataPairs } data - Data to update
     * @param { DataPairs= } criteria - Search criteria [optional, default={}]
     */
    async updateInDatabase(table: string, data: DataPairs, criteria: DataPairs = {}): Promise<void> {
        const dataKeys = Object.keys(data).map((key) => `${key} = ?`);
        const criteriaKeys = Object.keys(criteria).map((key) => `${key} = ?`);
        const values = [...Object.values(data), ...Object.values(criteria)];

        await this.pool.execute(
            `UPDATE ${table} SET ${dataKeys.join(", ")} WHERE ${criteriaKeys.join(" AND ")}`,
            values,
        );
    }

    /**
     * Fetches all values from a column in the database
     * @param { string } table - Table name
     * @param { string } column - Column name
     * @param { DataPairs= } criteria - Search criteria [optional, default={}]
     * @returns { Promise<unknown[]> } - Returns an array of values from the column
     */
    async grabColumnFromDatabase(table: string, column: string, criteria: DataPairs = {}): Promise<unknown[]> {
        const results = await this.queryDatabase(table, column, criteria);
        return results.map((result) => result[column] as unknown);
    }

    /**
     * Fetches a single column value from the database
     * @param { string } table - Table name
     * @param { string } column - Column name
     * @param { DataPairs= } criteria - Search criteria [optional, default={}]
     * @returns { Promise<unknown> } - Returns the value of the column, or null if not found
     */
    async grabFromDatabase(table: string, column: string, criteria: DataPairs = {}): Promise<unknown> {
        const results = await this.grabColumnFromDatabase(table, column, criteria);
        return results[0] || null;
    }

    /**
     * Closes the database connection
     */
    async closePool() {
        await this.pool.end();
    }
}
