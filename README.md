# Console

CLI helper for colorized/ANSI output, verbose-level gating, argument parsing, and simple prompts — built on top of [`peels/bitwise`](../bitwise/README.md) to track which verbose levels are currently active.

## Example

```php
use peels\console\Console;
use orange\framework\input\Input; // any InputInterface implementation

$console = Console::getInstance([], $input);

$console->detectVerboseLevel(); // reads -v / -vDebug / -V from argv

$console->info('Starting import...')
        ->line()
        ->table([
            ['Column', 'Value'],
            ['Rows',   '1,204'],
            ['Errors', '0'],
        ]);

$name = $console->getLine('Enter your name: ');

$console->minimumArguments(1, 'Usage: import.php <file>');
$file = $console->getArgument(1);
```

Named output levels (`always`, `alert`, `critical`, `debug`, `emergency`, `error`, `info`, `notice`, `warning`) are called directly as methods and only print when their verbose bit is turned on — control this with `verboseAdd()`, `verboseRemove()`, or `detectVerboseLevel()`.

Markup tags like `<bright red>...` in any output string are converted to ANSI escape codes using `console/src/config/ansiColors.php`, and stripped entirely when color is disabled.
