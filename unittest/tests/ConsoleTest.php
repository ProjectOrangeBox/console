<?php

use peels\console\Console;
use peels\console\exceptions\Console as ConsoleException;
use orange\framework\interfaces\InputInterface;

final class ConsoleTest extends unitTestHelper
{
    public function testInfoWritesToStdoutWhenVerboseEnabled(): void
    {
        $console = $this->createConsole();

        $console->verboseAdd('everything')->info('hello world');

        $this->assertSame("hello world\n", $this->sanitizeOutput($this->getPrivatePublic('stdout', $console)));
    }

    public function testErrorWritesToStderrWhenVerboseEnabled(): void
    {
        $console = $this->createConsole();

        $console->verboseAdd('everything')->error('boom!');

        $this->assertSame("✘ boom!\n", $this->sanitizeOutput($this->getPrivatePublic('stderr', $console)));
        $this->assertSame('', $this->sanitizeOutput($this->getPrivatePublic('stdout', $console)));
    }

    public function testLineUsesSimulatedWidth(): void
    {
        $console = $this->createConsole();
        $console->verboseAdd('everything');
        $this->setPrivatePublic('simulatedWidth', 10, $console);

        $console->line();

        $this->assertSame("----------\n", $this->sanitizeOutput($this->getPrivatePublic('stdout', $console)));
    }

    public function testClearResetsSimulatedBuffers(): void
    {
        $console = $this->createConsole();
        $console->verboseAdd('everything')->info('hello')->error('problem');

        $this->assertNotSame('', $this->getPrivatePublic('stdout', $console));
        $this->assertNotSame('', $this->getPrivatePublic('stderr', $console));

        $console->clear();

        $this->assertSame('', $this->getPrivatePublic('stdout', $console));
        $this->assertSame('', $this->getPrivatePublic('stderr', $console));
    }

    public function testDetectVerboseLevelFromArgumentsEnablesOutput(): void
    {
        $console = $this->createConsole([], ['argv' => ['console.php', '-vWarning']]);

        $console->detectVerboseLevel();
        $console->warning('pay attention');

        $this->assertSame("➤ pay attention\n", $this->sanitizeOutput($this->getPrivatePublic('stdout', $console)));
    }

    public function testBellWritesConfiguredCharacter(): void
    {
        $console = $this->createConsole(['bell' => '!']);
        $console->verboseAdd('everything');

        $console->bell(3);

        $this->assertSame('!!!', $this->sanitizeOutput($this->getPrivatePublic('stdout', $console)));
    }

    public function testGetLineReturnsSimulatedInput(): void
    {
        $console = $this->createConsole();
        $this->setPrivatePublic('stdin', 'simulated input', $console);

        $this->assertSame('simulated input', $console->getLine());
    }

    public function testGetLineOneOfReturnsValidSelection(): void
    {
        $console = $this->createConsole();
        $console->verboseAdd('everything');
        $this->setPrivatePublic('stdin', 'yes', $console);

        $this->assertSame('yes', $console->getLineOneOf(null, ['yes', 'no']));
    }

    public function testGetOneOfReturnsValidSelection(): void
    {
        $console = $this->createConsole();
        $console->verboseAdd('everything');
        $this->setPrivatePublic('stdin', '2', $console);

        $this->assertSame('2', $console->getOneOf(null, ['1', '2', '3']));
        $this->assertSame("\n", $this->sanitizeOutput($this->getPrivatePublic('stdout', $console)));
    }

    public function testExitThrowsConsoleExceptionInSimulationMode(): void
    {
        $console = $this->createConsole();

        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Exception thrown with exit level 5');

        $console->exit(5);
    }

    public function testMinimumArgumentsThrowsWhenMissing(): void
    {
        $console = $this->createConsole([], ['argv' => ['console.php']]);
        $console->verboseAdd('everything');

        $this->expectException(ConsoleException::class);
        $this->expectExceptionMessage('Exception thrown with exit level 1');

        $console->minimumArguments(1, 'Need more');
    }

    public function testMinimumArgumentsPassesWithEnoughArgs(): void
    {
        $console = $this->createConsole([], ['argv' => ['console.php', 'first', 'second']]);
        $console->verboseAdd('everything');

        $this->assertSame($console, $console->minimumArguments(2));
    }

    public function testGetArgumentByOptionReturnsFollowingValue(): void
    {
        $console = $this->createConsole([], ['argv' => ['console.php', '--color', 'blue', '--file', 'example']]);

        $this->assertSame('blue', $console->getArgumentByOption('--color'));
    }

    public function testFormatOutputAppliesAnsiCodes(): void
    {
        $console = $this->createConsole();

        $output = $console->formatOutput('<red>Danger<off>');

        $this->assertStringContainsString("\033[31m", $output);
        $this->assertStringContainsString('Danger', $output);
        $this->assertStringEndsWith("\033[0m\n", $output);
    }

    public function testStripTagsRemovesMarkupWhenColorDisabled(): void
    {
        $console = $this->createConsole();
        $this->setPrivatePublic('color', false, $console);

        $result = $this->callMethod('stripTags', ['Hello<lf>World<red>!'], $console);

        $this->assertSame("Hello\nWorld!", $result);
    }

    private function createConsole(array $config = [], array $server = []): Console
    {
        $server = array_replace(['argv' => ['console.php']], $server);
        $server['argc'] = $server['argc'] ?? count($server['argv']);

        $config = array_replace(['simulate' => true], $config);

        $console = Console::newInstance($config, new ConsoleTestInput($server));
        $this->instance = $console;

        return $console;
    }

    private function sanitizeOutput(string $value): string
    {
        return preg_replace('/\e\[[0-9;]*m/', '', $value);
    }
}

final class ConsoleTestInput implements InputInterface
{
    public function __construct(private array $server = [])
    {
    }

    public function getUrl(int $component = -1)
    {
        return '';
    }

    public function requestUri(): string
    {
        return '';
    }

    public function uriSegment(int $segmentNumber): string
    {
        return '';
    }

    public function contentType(bool $asLowercase = true): string
    {
        $type = 'text/plain';

        return $asLowercase ? strtolower($type) : $type;
    }

    public function requestMethod(bool $asLowercase = true): string
    {
        $method = 'GET';

        return $asLowercase ? strtolower($method) : $method;
    }

    public function requestType(bool $asLowercase = true): string
    {
        $type = 'cli';

        return $asLowercase ? strtolower($type) : $type;
    }

    public function isAjaxRequest(): bool
    {
        return false;
    }

    public function isCliRequest(): bool
    {
        return true;
    }

    public function isHttpsRequest(bool $asString = false): bool|string
    {
        return $asString ? 'http' : false;
    }

    public function request(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    public function header(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function cookie(?string $key = null, mixed $default = null): mixed
    {
        return $default;
    }

    public function file(null|int|string $key = null, mixed $default = null): mixed
    {
        return $default;
    }
}
