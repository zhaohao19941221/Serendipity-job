<?php
/**
 * This file is part of Serendipity Job
 * @license  https://github.com/Hyperf-Glory/SerendipityJob/main/LICENSE
 */

declare(strict_types=1);

namespace Serendipity\Job\Console;

use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Serendipity\Job\Kernel\serendipity_call;

abstract class Command extends SymfonyCommand
{
    /**
     * The name of the command.
     *
     * @var null|string
     */
    protected string | null $name = null;

    protected InputInterface $input;

    protected SymfonyStyle $output;

    public function __construct(string $name = null)
    {
        if (!$name && $this->name) {
            $name = $this->name;
        }
        parent::__construct($name);
    }

    /**
     * Run the console command.
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->output = new SymfonyStyle($input, $output);

        return parent::run($this->input = $input, $this->output);
    }

    /**
     * Format input to textual table.
     *
     * @param null|string $tableStyle
     */
    public function table(
        array $headers,
        array $rows,
        null | string $tableStyle = 'default',
        array $columnStyles = []
    ): void {
        $table = new Table($this->output);

        $table->setHeaders($headers)
            ->setRows($rows)
            ->setStyle($tableStyle);

        foreach ($columnStyles as $columnIndex => $columnStyle) {
            $table->setColumnStyle($columnIndex, $columnStyle);
        }

        $table->render();
    }

    /**
     * Write a string as standard output.
     */
    public function line(mixed $string, mixed $style = null, mixed $verbosity = null): void
    {
        $styled = $style ? "<{$style}>{$string}</{$style}>" : $string;
        $this->output->writeln($styled, $this->parseVerbosity($verbosity));
    }

    /**
     * Write a string as information output.
     */
    public function info(mixed $string, mixed $verbosity = null): void
    {
        $this->line($string, 'info', $verbosity);
    }

    /**
     * Write a string as comment output.
     */
    public function comment(mixed $string, mixed $verbosity = null): void
    {
        $this->line($string, 'comment', $verbosity);
    }

    /**
     * Write a string as question output.
     */
    public function question(mixed $string, mixed $verbosity = null): void
    {
        $this->line($string, 'question', $verbosity);
    }

    /**
     * Write a string as error output.
     */
    public function error(mixed $string, mixed $verbosity = null): void
    {
        $this->line($string, 'error', $verbosity);
    }

    /**
     * Write a string as warning output.
     */
    public function warn(mixed $string, mixed $verbosity = null): void
    {
        if (!$this->output->getFormatter()
            ->hasStyle('warning')) {
            $style = new OutputFormatterStyle('yellow');
            $this->output->getFormatter()
                ->setStyle('warning', $style);
        }
        $this->line($string, 'warning', $verbosity);
    }

    /**
     * Write a string in an alert box.
     */
    public function alert(mixed $string): void
    {
        $length = Strings::length(strip_tags($string)) + 12;
        $this->comment(str_repeat('*', $length));
        $this->comment('*     ' . $string . '     *');
        $this->comment(str_repeat('*', $length));
        $this->output->newLine();
    }

    protected function execute(InputInterface $input, OutputInterface $output): mixed
    {
        $callback = function () {
            return serendipity_call([$this, 'handle']);
        };

        return $callback();
    }

    /**
     * Handle the current command.
     */
    abstract public function handle(): mixed;
}
