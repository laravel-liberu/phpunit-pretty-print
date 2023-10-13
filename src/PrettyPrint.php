<?php

namespace LaravelLiberu\PHPUnitPrettyPrint;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PHPUnit\Framework\Test;
use PHPUnit\TextUI\DefaultResultPrinter;
use PHPUnit\Util\Test as Util;
use ReflectionClass;

class PrettyPrint extends DefaultResultPrinter
{
    protected const Output = [
        '.' => ['color' => 'fg-green', 'progress' => '✓'],
        'E' => ['color' => 'fg-red', 'progress' => '⚈'],
        'F' => ['color' => 'fg-red', 'progress' => 'x'],
        'W' => ['color' => 'fg-yellow', 'progress' => '!'],
        'I' => ['color' => 'fg-yellow', 'progress' => '∅'],
        'R' => ['color' => 'fg-yellow', 'progress' => '⚑'],
        'S' => ['color' => 'fg-cyan', 'progress' => '⤼'],
    ];

    protected const TimeThresholds = [
        ['limit' => 0.1, 'color' => 'fg-white'],
        ['limit' => 0.25, 'color' => 'fg-yellow'],
        ['limit' => 0.5, 'color' => 'fg-magenta'],
    ];

    protected const Danger = ['color' => 'fg-red', 'progress' => '?'];

    private Test $test;
    private string $testName;
    private array $output;

    public function startTest(Test $test): void
    {
        $this->test = $test;
    }

    public function endTest(Test $test, float $time): void
    {
        parent::endTest($test, $time);

        $this->writeCurentTestDuration($time);
    }

    protected function writeProgress(string $progress): void
    {
        $this->numTestsRun++;

        $this->output = $this->currentOutput($progress);

        $this->writeTestName()
            ->writeSuiteProgress()
            ->writeTestResult()
            ->writeMethodName();
    }

    private function currentOutput(string $progress): array
    {
        $sanitizedProgress = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $progress);

        return static::Output[$sanitizedProgress] ?? static::Danger;
    }

    private function writeTestName(): self
    {
        if ($this->shouldWriteTestName()) {
            $this->testName = $this->testName();
            $this->write("\n");
            $this->writeWithColor('bold', $this->testName);
        }

        return $this;
    }

    private function shouldWriteTestName(): bool
    {
        return ! isset($this->testName)
            || $this->testName !== $this->testName();
    }

    private function testName(): string
    {
        return (new ReflectionClass($this->test))->getShortName();
    }

    private function writeSuiteProgress(): self
    {
        $pad = Str::length($this->numTests);
        $current = sprintf("%0{$pad}d", $this->numTestsRun);

        $this->write("[{$current}/{$this->numTests}] ");

        return $this;
    }

    private function writeTestResult(): self
    {
        $this->writeWithColor($this->output['color'], $this->output['progress'], false);

        return $this;
    }

    private function writeMethodName(): self
    {
        $this->write(' ');
        $this->writeWithColor($this->output['color'], $this->currentMethodName(), false);
        $this->write(' ');

        return $this;
    }

    private function currentMethodName(): string
    {
        $description = Util::describe($this->test)[1];
        $method = Str::snake($description);
        $method = Str::replaceFirst('with_data_set"', '[', $method);
        $method = Str::replaceFirst('"', ']', $method);

        return Collection::wrap(explode('_', $method))
            ->reject(fn ($word) => $word === 'test')
            ->implode(' ');
    }

    private function writeCurentTestDuration(float $time): void
    {
        $thresholds = new Collection(static::TimeThresholds);
        $duration = $thresholds->first(fn ($threshold) => $time <= $threshold['limit']);
        $formattedTime = number_format($time, 3);
        $color = $duration['color'] ?? static::Danger['color'];

        $this->writeWithColor($color, "({$formattedTime}s)", true);
    }
}
