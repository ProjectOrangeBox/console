<?php

declare(strict_types=1);

namespace orange\console;

use Exception;
use orange\bitwise\BitWise;
use orange\console\ConsoleInterface;
use orange\framework\base\Singleton;
use orange\console\exceptions\Console as ConsoleException;
use orange\framework\interfaces\InputInterface;
use orange\framework\traits\ConfigurationTrait;

/**
 * Console helper for rendering formatted output and handling CLI interactions.
 */
class Console extends Singleton implements ConsoleInterface
{
    use ConfigurationTrait;

    // loaded from config below
    protected array $ansiCodes = [];

    protected array $named = [
        'always'    => ['icon' => '', 'stream' => \STDOUT, 'color' => ''],
        'alert'     => ['icon' => '➤ ', 'stream' => \STDOUT, 'color' => '<bright yellow>'],
        'critical'  => ['icon' => '✘ ', 'stream' => \STDERR, 'color' => '<bright red>'],
        'debug'     => ['icon' => '❖ ', 'stream' => \STDOUT, 'color' => '<bright green>'],
        'emergency' => ['icon' => '✘ ', 'stream' => \STDERR, 'color' => '<bright magenta>'],
        'error'     => ['icon' => '✘ ', 'stream' => \STDERR, 'color' => '<bright red>'],
        'info'      => ['icon' => '', 'stream' => \STDOUT, 'color' => ''],
        'notice'    => ['icon' => '➤ ', 'stream' => \STDOUT, 'color' => '<bright yellow>'],
        'warning'   => ['icon' => '➤ ', 'stream' => \STDOUT, 'color' => '<bright yellow>'],
    ];

    protected array $defaultLevels = [
        'bell' => 'info',
        'line' => 'info',
        'clear' => 'info',
        'linefeed' => 'info',
        'table' => 'info',
    ];

    protected string $listFormat = '<off>[<cyan>%key%<off>] %value%';
    protected string $lf = "\n";
    protected bool $color = true;
    protected string $bell = '';

    protected array $argv = [];
    protected int $argc = 0;

    protected BitWise $verbose;
    protected string $verboseChar = 'v';
    protected string $defaultVerbose = 'info';
    protected string $defaultUpperCaseVerbose = 'everything';

    // unit testing storage
    protected bool $simulate = false;
    protected int $simulatedWidth = 80;
    protected string $stdin = '';
    protected string $stderr = '';
    protected string $stdout = '';

    /**
     * Initialize console services with configuration data and input holder.
     *
     * @param array<string, mixed> $config Configuration values for the console.
     * @param InputInterface $input Input wrapper providing server context.
     */
    protected function __construct(array $config, InputInterface $input)
    {
        $this->config = $this->mergeConfigWith($config);

        $this->lf = $this->config['Linefeed Character'] ?? $this->lf;
        $this->simulate = $this->config['simulate'] ?? $this->simulate;
        $this->listFormat = $this->config['List Format'] ?? $this->listFormat;
        $this->color = $this->config['color'] ?? $this->color;
        $this->ansiCodes = require __DIR__ . '/ANSI_Codes.php';

        if (isset($this->config['ANSI Codes'])) {
            $this->ansiCodes = array_replace($this->ansiCodes, $this->config['ANSI Codes']);
        }

        $this->named = $this->config['named'] ?? $this->named;

        // setup bitwise with our named values
        $this->verbose = BitWise::getInstance(array_keys($this->named));
        $this->verboseChar = $this->config['verbose char'] ?? $this->verboseChar;
        $this->defaultVerbose = $this->config['default verbose'] ?? $this->defaultVerbose;
        $this->defaultUpperCaseVerbose = $this->config['default uppercase verbose'] ?? $this->defaultUpperCaseVerbose;

        $this->argv = $input->server('argv', []);
        $this->argc = $input->server('argc', 0);

        $this->bell = $this->config['bell'] ?? chr(7);
    }

    /**
     * Enable one or more verbose levels.
     *
     * @param string|string[] ...$bits Verbose identifiers or collections of identifiers.
     *
     * @return $this
     */
    public function verboseAdd(): self
    {
        $args = func_get_args();

        $this->verbose->turnOn($args);

        return $this;
    }

    /**
     * Disable one or more verbose levels.
     *
     * @param string|string[] ...$bits Verbose identifiers or collections of identifiers.
     *
     * @return $this
     */
    public function verboseRemove(): self
    {
        $args = func_get_args();

        $this->verbose->turnOff($args);

        return $this;
    }

    /**
     * Reset all verbose flags to their default state.
     *
     * @return $this
     */
    public function verboseReset(): self
    {
        $this->verbose->reset();

        return $this;
    }

    /**
     * Auto-detect verbose levels from CLI arguments.
     * - `command.php -v` enables the default verbose level.
     * - `command.php -vDebug` enables debug.
     * - `command.php -vDebug -vInfo` enables debug and info.
     * - `command.php -V` enables everything.
     *
     * @param string|null $char Override verbose switch character (defaults to configured value).
     *
     * @return void
     */
    public function detectVerboseLevel(?string $char = null): void
    {
        $char = $char ?? $this->verboseChar;

        foreach ($this->argv as $arg) {
            if ($arg == '-' . strtoupper($char)) {
                $this->verboseAdd($this->defaultUpperCaseVerbose);
            } elseif ($arg == '-' . $char) {
                $this->verboseAdd($this->defaultVerbose);
            } elseif (substr($arg, 0, 2) == '-' . $char) {
                $bit = trim(substr($arg, 2));

                if ($this->verbose->hasBit($bit)) {
                    $this->verboseAdd($bit);
                }
            }
        }
    }

    /**
     * Route calls to named output helpers such as `info()` or `error()`.
     *
     * @param string $name Named output level.
     * @param array<int, mixed> $arguments Arguments forwarded to the handler.
     *
     * @return $this
     *
     * @throws ConsoleException When the named level is undefined.
     */
    public function __call(string $name, array $arguments): mixed
    {
        if (!isset($this->named[$name])) {
            throw new ConsoleException('Unknown console method "' . $name . '".');
        }

        $string = $arguments[0] ?? '';
        $linefeed = $arguments[1] ?? true;

        $this->write($this->formatOutput($this->named[$name]['color'] . $this->named[$name]['icon'] . $string, (bool)$linefeed), $name, $this->named[$name]['stream']);

        return $this;
    }

    /**
     * Ring the console bell a specified number of times.
     *
     * @param int $times Number of bell characters to emit.
     * @param string|null $level Verbose level that controls whether the bell is emitted.
     *
     * @return $this
     */
    public function bell(int $times = 1, ?string $level = null): self
    {
        $level = $level ?? $this->defaultLevels['bell'];

        $this->write(str_repeat($this->bell, $times), $level, \STDOUT);

        return $this;
    }

    /**
     * Output a line composed of a repeated character sequence.
     *
     * @param int|null $length Desired line length; auto-detected when null.
     * @param string $char Character sequence to repeat.
     * @param string|null $level Verbose level that controls whether the line is emitted.
     *
     * @return $this
     */
    public function line(?int $length = null, string $char = '-', ?string $level = null): self
    {
        $level = $level ?? $this->defaultLevels['line'];

        if ($length == null && $this->simulate) {
            // fixed amount in simulate mode
            $times = $this->simulatedWidth;
        } else {
            $times = $length ?? (int)$this->system('tput cols');
        }

        $times = (int)floor($times / strlen($char));

        $this->write(str_repeat($char, $times) . $this->lf, $level, \STDOUT);

        return $this;
    }

    /**
     * Attempt to clear console output or reset simulated buffers.
     *
     * @param string|null $level Verbose level that controls whether the operation runs.
     *
     * @return $this
     */
    public function clear(?string $level = null): self
    {
        $level = $level ?? $this->defaultLevels['clear'];

        if ($this->simulate) {
            // if simulating "clear" the output
            $this->stderr = '';
            $this->stdout = '';
        } elseif ($this->verbose->isSet($level)) {
            $this->system('clear');
        }

        return $this;
    }

    /**
     * Output one or more line feed characters.
     *
     * @param int $times Number of line feeds to emit.
     * @param string|null $level Verbose level that controls whether output occurs.
     *
     * @return $this
     */
    public function linefeed(int $times = 1, ?string $level = null): self
    {
        $level = $level ?? $this->defaultLevels['linefeed'];

        return $this->write(str_repeat($this->lf, $times), $level, \STDOUT);
    }

    /**
     * Render an ASCII table with aligned columns.
     *
     * @param array<int, array<int, scalar>> $table Table rows and columns.
     * @param string|null $level Verbose level that controls whether the table prints.
     *
     * @return $this
     */
    public function table(array $table, ?string $level = null): self
    {
        $level = $level ?? $this->defaultLevels['table'];

        // get max column size
        $columnsMaxWidth = [];

        foreach ($table as $rowIndex => $row) {
            foreach ($row as $columnIndex => $column) {
                if (!isset($columnsMaxWidth[$columnIndex])) {
                    $columnsMaxWidth[$columnIndex] = 0;
                }

                $columnsMaxWidth[$columnIndex] = max($columnsMaxWidth[$columnIndex], strlen((string)$column) + 1);
            }
        }

        $totalWidth = 0;

        $masks = [];

        foreach ($table as $rowIndex => $row) {
            $m = [];
            foreach ($row as $columnIndex => $column) {
                $width = $columnsMaxWidth[$columnIndex];

                $m[] = ' %-' . $width . '.' . $width . 's ';

                if ($rowIndex == 0) {
                    $totalWidth = $totalWidth + $width + 2;
                }
            }

            $masks[$rowIndex] = '|' . implode('|', $m) . '|';
        }

        $totalWidth = $totalWidth  + 4;

        $this->line($totalWidth, '-', $level);

        foreach ($table as $rowIndex => $row) {
            array_unshift($row, $masks[$rowIndex]);

            ob_start();

            call_user_func_array('printf', $row);

            $this->$level(trim(ob_get_clean()), $level);

            if ($rowIndex == 0) {
                $this->line($totalWidth, '-', $level);
            }
        }

        $this->line($totalWidth, '-', $level);

        return $this;
    }

    /**
     * Write a formatted key/value list to the console.
     *
     * @param array<string, scalar> $list Entries to display.
     *
     * @return $this
     */
    public function list(array $list): self
    {
        foreach ($list as $key => $value) {
            $this->always(str_replace(['%key%', '%value%'], [$key, $value], $this->listFormat));
        }

        return $this;
    }

    /* get input until return is pressed */

    /**
     * Prompt the user for input terminated by a line feed.
     *
     * @param string|null $prompt Optional prompt message.
     *
     * @return string Input captured from STDIN or simulated buffer.
     */
    public function getLine(?string $prompt = null): string
    {
        if ($prompt) {
            $this->always($prompt);
        }

        // if in simulate send back std in
        return ($this->simulate) ? $this->stdin : rtrim(fgets(\STDIN), $this->lf);
    }

    /**
     * Prompt until the user selects one of the provided options.
     *
     * @param string|null $prompt Optional prompt message.
     * @param array<int, string> $options Allowed responses.
     *
     * @return string Selected response.
     */
    public function getLineOneOf(?string $prompt = null, array $options = []): string
    {
        do {
            $input = $this->getLine($prompt);
            $success = $this->oneOf($input, $options);
        } while (!$success);

        return $input;
    }
    /**
     * Read a single character from STDIN without requiring a newline.
     *
     * @param string|null $prompt Optional prompt message.
     *
     * @return string Captured character or simulated input.
     */
    public function get(?string $prompt = null): string
    {
        if ($prompt) {
            $this->always($prompt);
        }

        // if in simulate send back stdin
        if ($this->simulate) {
            // BAIL NOW - multiple exits
            return $this->stdin;
        }

        // setup console no buffer
        $this->system('stty -icanon');

        while ($char = fread(\STDIN, 1)) {
            return $char;
        }

        // just incase we slip through to here
        return '';
    }

    /**
     * Read a single character limited to a set of valid options.
     *
     * @param string|null $prompt Optional prompt message.
     * @param array<int, string> $options Acceptable characters.
     *
     * @return string Validated character.
     */
    public function getOneOf(?string $prompt = null, array $options = []): string
    {
        do {
            $input = $this->get($prompt);
            $success = $this->oneOf($input, $options);
        } while (!$success);

        $this->linefeed(1);

        return $input;
    }

    /**
     * Exit the application, or throw during simulation.
     *
     * @param int $exitLevel Exit status code.
     *
     * @return never
     *
     * @throws ConsoleException When running in simulation mode.
     */
    public function exit(int $exitLevel = 0)
    {
        if ($this->simulate) {
            throw new ConsoleException('Exception thrown with exit level ' . $exitLevel);
        } else {
            exit($exitLevel);
        }
    }

    /* Arguments */

    /**
     * Ensure at least the provided number of CLI arguments exist.
     *
     * @param int $num Required argument count.
     * @param string|null $error Optional message when validation fails.
     *
     * @return $this
     */
    public function minimumArguments(int $num, ?string $error = null): self
    {
        if ($this->argc < ($num + 1)) {
            $error = $error ?? 'Please provide ' . $num . ' arguments';

            $this->error($error)->exit(1);
        }

        return $this;
    }

    /**
     * Check whether a specific CLI argument value exists.
     *
     * @param string $match Argument to look for.
     *
     * @return bool True when the argument is present.
     */
    public function getArgumentExists(string $match): bool
    {
        $found = false;

        foreach ($this->argv as $arg) {
            if ($arg == $match) {
                $found = true;

                break;
            }
        }

        return $found;
    }

    /**
     * Retrieve an argument by index or exit with an error.
     *
     * @param int $num Argument index.
     * @param string|null $error Optional error message when the argument is missing.
     *
     * @return string Selected argument.
     */
    public function getArgument(int $num, ?string $error = null): string
    {
        if (!isset($this->argv[$num])) {
            if (!$error) {
                $error = 'Could not locate a Argument ' . $num;
            }

            $this->error($error)->exit(1);
        }

        return $this->argv[$num];
    }

    /**
     * Return the final CLI argument when available.
     *
     * @return string Last argument or empty string.
     */
    public function getLastArgument(): string
    {
        $last = '';

        if ($this->argc > 0) {
            $last = end($this->argv);
        }

        return $last;
    }

    /**
     * Retrieve the argument value following a named option.
     *
     * @param string $match Option name to search for.
     * @param string|null $error Optional message when the option is missing a value.
     *
     * @return string Argument paired with the option.
     */
    public function getArgumentByOption(string $match, ?string $error = null): string
    {
        if (!$error) {
            $error = 'Could not locate a option for ' . $match;
        }

        foreach ($this->argv as $key => $value) {
            if ($value == $match) {
                $next = $key + 1;

                if (!isset($this->argv[$next])) {
                    $this->error($error)->exit(1);
                }

                return $this->argv[$next];
            }
        }

        $this->error($error)->exit(1);

        return '';
    }

    /**
     * Apply ANSI color formatting to output strings.
     *
     * @param string $string String containing optional markup tags.
     * @param bool $linefeed Whether to append the configured linefeed.
     *
     * @return string Formatted string ready for output.
     */
    public function formatOutput(string $string, bool $linefeed = true): string
    {
        $string = $this->stripTags($string);

        $turnOff = '';

        // find all the <tags>
        preg_match_all('/<([^>]*)>/i', $string, $tags, PREG_SET_ORDER, 0);

        foreach ($tags as $tag) {
            $colorsEscaped = '';

            // apply color escape codes
            if (!isset($this->ansiCodes[$tag[1]])) {
                $this->error('Could not find tag "' . $tag[1] . '"')->exit(1);
            }

            foreach (explode(',', (string)$this->ansiCodes[$tag[1]]) as $colorEscapeCode) {
                $colorsEscaped .= "\033[" . $colorEscapeCode . "m";
            }

            $string = str_replace($tag[0], $colorsEscaped, $string);

            $turnOff = "\033[" . $this->ansiCodes['off'] . "m";
        }

        return $string . $turnOff . (($linefeed) ? $this->lf : '');
    }

    /* protected */

    /**
     * Remove markup tags when colors are disabled and normalize linefeeds.
     *
     * @param string $string String containing optional markup.
     *
     * @return string Sanitized string.
     */
    protected function stripTags(string $string): string
    {
        // quick find and replace for all linefeeds
        $string = str_replace('<lf>', $this->lf, $string);

        if (!$this->color) {
            preg_match_all('/<([^>]*)>/i', $string, $tags, PREG_SET_ORDER, 0);

            foreach ($tags as $tag) {
                $string = str_replace($tag[0], '', $string);
            }
        }

        return $string;
    }

    /**
     * Validate input against a set of allowed values.
     *
     * @param string $input User input to validate.
     * @param array<int, string> $oneOf Allowed values.
     * @param string|null $error Optional error message.
     *
     * @return bool True when input is valid.
     */
    protected function oneOf(string $input, array $oneOf, ?string $error = null): bool
    {
        $success = true;
        $shownError = '';

        if (empty(trim($input))) {
            $shownError = $error ?? 'Please select an option.';
        } elseif (!in_array($input, $oneOf)) {
            $shownError = $error ?? 'Your input did not match an option.';
            $shownError = $this->lf . $shownError;
        }

        if (!empty($shownError)) {
            $this->linefeed(0)->always($shownError);
            $success = false;
        }

        return $success;
    }

    /**
     * Write formatted content to the target stream or simulated buffers.
     *
     * @param string $string Formatted output string.
     * @param string $level Verbose level that guards the write.
     * @param resource $stream Stream resource to receive the output.
     *
     * @return $this
     */
    protected function write(string $string, string $level, $stream): self
    {
        if ($this->verbose->isSet($level)) {
            if ($this->simulate) {
                if ($stream == \STDERR) {
                    $this->stderr .= $string;
                } else {
                    $this->stdout .= $string;
                }
            } else {
                fwrite($stream, $string);
            }
        }

        return $this;
    }

    /**
     * Validate an argument position with a callable type check.
     *
     * @param array<int, mixed> $arguments Arguments to inspect.
     * @param int $index Position within the argument list.
     * @param mixed $default Default value when the argument is missing.
     * @param callable $function Type checking callback.
     *
     * @return mixed Validated argument or default value.
     *
     * @throws Exception When the argument fails validation.
     */
    protected function validateArgument($arguments, $index, $default, $function)
    {
        $typeMap = [
            'is_string' => 'string',
            'is_int' => 'integer',
            'is_float' => 'floating',
            'is_bool' => 'boolean',
            'is_array' => 'array',
        ];

        $type = $typeMap[$function];

        if (!isset($arguments[$index])) {
            $return = $default;
        } else {
            if (!$function($arguments[$index])) {
                throw new \Exception('Argument ' . ($index + 1) . ' must be ' . $type . '.');
            }

            $return = $arguments[$index];
        }

        return $return;
    }

    /**
     * Execute a shell command and capture its first line of output.
     *
     * @param string $command Command string to run.
     *
     * @return string First line of output or empty string.
     */
    protected function system(string $command): string
    {
        $resultCode = 0;
        $output = [];

        exec($command, $output, $resultCode);

        return (empty($output)) ? '' : $output[0];
    }
}
