<?php

declare(strict_types=1);

namespace peels\console;

/**
 * Contract for console helpers that manage output formatting and command-line interactions.
 */
interface ConsoleInterface
{
    /**
     * Enable one or more verbose output levels.
     *
     * @param string|string[] ...$bits Verbose identifiers to activate.
     *
     * @return static
     */
    public function verboseAdd(): self;

    /**
     * Disable one or more verbose output levels.
     *
     * @param string|string[] ...$bits Verbose identifiers to deactivate.
     *
     * @return static
     */
    public function VerboseRemove(): self;

    /**
     * Reset verbose configuration to the default state.
     *
     * @return static
     */
    public function verboseReset(): self;

    /**
     * Detect verbose flags from CLI arguments and update state accordingly.
     *
     * @param string|null $char Optional override for the verbose flag character.
     *
     * @return void
     */
    public function detectVerboseLevel(?string $char = null): void;

    /**
     * Handle dynamic calls to named output helpers such as `info()` or `error()`.
     *
     * @param string $name Requested output level.
     * @param array<int, mixed> $arguments Arguments forwarded by the caller.
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments): mixed;

    /**
     * Emit one or more bell characters.
     *
     * @param int $times Number of times to emit the bell.
     * @param string|null $level Verbose level to use when writing output.
     *
     * @return static
     */
    public function bell(int $times = 1, ?string $level = null): self;

    /**
     * Render a horizontal line using a repeated character sequence.
     *
     * @param int|null $length Total length of the line; auto-detected when null.
     * @param string $char Character sequence used for the line.
     * @param string|null $level Verbose level to use when writing output.
     *
     * @return static
     */
    public function line(?int $length = null, string $char = '-', ?string $level = null): self;

    /**
     * Clear console output or reset simulated buffers.
     *
     * @param string|null $level Verbose level gate for the operation.
     *
     * @return static
     */
    public function clear(?string $level = null): self;

    /**
     * Write one or more line feed characters.
     *
     * @param int $times Number of line feeds to output.
     * @param string|null $level Verbose level gate for the operation.
     *
     * @return static
     */
    public function linefeed(int $times = 1, ?string $level = null): self;

    /**
     * Render a formatted table with aligned columns.
     *
     * @param array<int, array<int, scalar>> $table Table rows to output.
     * @param string|null $level Verbose level gate for the operation.
     *
     * @return static
     */
    public function table(array $table, ?string $level = null): self;

    /**
     * Output a formatted key/value list.
     *
     * @param array<string, scalar> $list Entries to render.
     *
     * @return static
     */
    public function list(array $list): self;

    /**
     * Prompt the user for a line of input.
     *
     * @param string|null $prompt Optional prompt message.
     *
     * @return string Captured input.
     */
    public function getLine(?string $prompt = null): string;

    /**
     * Prompt until the response matches one of the provided options.
     *
     * @param string|null $prompt Optional prompt message.
     * @param array<int, string> $options Allowed responses.
     *
     * @return string Selected option.
     */
    public function getLineOneOf(?string $prompt = null, array $options = []): string;

    /**
     * Read a single character from STDIN.
     *
     * @param string|null $prompt Optional prompt message.
     *
     * @return string Captured character.
     */
    public function get(?string $prompt = null): string;

    /**
     * Read a single character limited to a set of values.
     *
     * @param string|null $prompt Optional prompt message.
     * @param array<int, string> $options Allowed characters.
     *
     * @return string Selected character.
     */
    public function getOneOf(?string $prompt = null, array $options = []): string;

    /**
     * Exit the application or throw during simulation.
     *
     * @param int $exitLevel Exit status code.
     *
     * @return void
     */
    public function exit(int $exitLevel = 0);

    /**
     * Ensure the expected number of CLI arguments are present.
     *
     * @param int $num Required arguments.
     * @param string|null $error Optional message when validation fails.
     *
     * @return static
     */
    public function minimumArguments(int $num, ?string $error = null): self;

    /**
     * Determine whether a specific argument exists.
     *
     * @param string $match Argument value to look for.
     *
     * @return bool True when found.
     */
    public function getArgumentExists(string $match): bool;

    /**
     * Retrieve an argument by index.
     *
     * @param int $num Argument index.
     * @param string|null $error Optional error message when missing.
     *
     * @return string Argument value.
     */
    public function getArgument(int $num, ?string $error = null): string;

    /**
     * Retrieve the final CLI argument if present.
     *
     * @return string Last argument or empty string.
     */
    public function getLastArgument(): string;

    /**
     * Retrieve the value paired with a named CLI option.
     *
     * @param string $match Option to search for.
     * @param string|null $error Optional message when the option is missing a value.
     *
     * @return string Value associated with the option.
     */
    public function getArgumentByOption(string $match, ?string $error = null): string;

    /**
     * Apply formatting to console output strings.
     *
     * @param string $string Source string.
     * @param bool $linefeed Whether to append a trailing linefeed.
     *
     * @return string Formatted string.
     */
    public function formatOutput(string $string, bool $linefeed = true): string;
}
