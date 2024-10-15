import { spawn } from "child_process";
import { existsSync } from "fs";

/**
 * BaseTester class to provide common functionality for all tests
 * Can be used in any context, including .beforeAll and .afterAll
 * The page fixture is not available in this class, so it cannot interact with the browser
 */
export default class BaseTester {
    /**
     * Executes a shell command and returns the result.
     * Looks for a /usr/share/schoolbox directory and if not found, attempts to run the command via `vagrant ssh -c '...'`
     * @param command - The command to execute.
     * @returns The stdout from the command.
     * @throws { Error } If the command fails.
     */
    // eslint-disable-next-line class-methods-use-this
    async runShellCommand(command: string): Promise<string> {
        // Try to run in vagrant if the schoolbox directory does not exist
        if (!existsSync("/usr/share/schoolbox") && !command.startsWith("vagrant ssh -c ")) {
            const vagrantCommand = `vagrant ssh -c "${command.replace(/"/g, '\\"')}"`;
            return this.runShellCommand(vagrantCommand);
        }

        return new Promise((resolve, reject) => {
            const parts = command.split(" ");
            const cmd = parts[0];
            const args = parts.slice(1);

            const child = spawn(cmd, args, { shell: true });

            let output = ""; // This will collect all the stdout

            // Handle stdout data
            child.stdout.on("data", (data: string) => {
                process.stdout.write(data);
                output += data.toString();
            });

            // Handle stderr data
            child.stderr.on("data", (data: string) => {
                process.stdout.write(data);
                output += data.toString();
            });

            // Handle completion
            child.on("close", (code) => {
                if (code === 0) {
                    resolve(output);
                } else {
                    reject(new Error(`Command failed with exit code ${code}`));
                }
            });
        });
    }

    /**
     * Rebuild the search index
     */
    async rebuildSearchIndex(): Promise<void> {
        this.isGoingTo("rebuild the search index");
        await this.runShellCommand("php /usr/share/schoolbox/tools/search/buildIndex.php -p --from='-10 min'");

        this.isGoingTo("wait for the search index to finish building");
        await new Promise((resolve) => {
            setTimeout(resolve, 2000);
        });
    }

    /**
     * Create an "I am going to " comment for debugging
     * @param argumentation - The message to log
     */
    // eslint-disable-next-line class-methods-use-this
    isGoingTo(argumentation: string): void {
        // eslint-disable-next-line no-console
        console.log("I am going to", argumentation);
    }
}
