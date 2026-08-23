<?php

if (! class_exists('Mockery')) {
    class MockeryExpectation implements \Symfony\Component\Console\Output\OutputInterface
    {
        private ?\Symfony\Component\Console\Formatter\OutputFormatterInterface $formatter = null;

        public function __call(string $name, array $arguments)
        {
            return $this;
        }

        public function setVerbosity(int $level): void
        {
        }

        public function getVerbosity(): int
        {
            return \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_NORMAL;
        }

        public function isQuiet(): bool
        {
            return false;
        }

        public function isVerbose(): bool
        {
            return false;
        }

        public function isVeryVerbose(): bool
        {
            return false;
        }

        public function isDebug(): bool
        {
            return false;
        }

        public function setDecorated(bool $decorated): void
        {
        }

        public function isDecorated(): bool
        {
            return false;
        }

        public function setFormatter(\Symfony\Component\Console\Formatter\OutputFormatterInterface $formatter): void
        {
            $this->formatter = $formatter;
        }

        public function getFormatter(): \Symfony\Component\Console\Formatter\OutputFormatterInterface
        {
            return $this->formatter ??= new \Symfony\Component\Console\Formatter\OutputFormatter();
        }

        public function write(iterable|string $messages, bool $newline = false, int $options = 0): void
        {
        }

        public function writeln(iterable|string $messages, int $options = 0): void
        {
        }

        public function setDecoratedOutput(bool $decorated): void
        {
        }
    }

    class MockeryContainer
    {
        public function mockery_getExpectationCount(): int
        {
            return 0;
        }
    }

    class Mockery
    {
        public static function close(): void
        {
        }

        public static function getContainer(): MockeryContainer
        {
            return new MockeryContainer();
        }

        public static function mock(...$arguments): MockeryExpectation
        {
            return new MockeryExpectation();
        }

        public static function spy(...$arguments): MockeryExpectation
        {
            return new MockeryExpectation();
        }

        public static function on(callable $callback): object
        {
            return new class ($callback) {
                public function __construct(private $callback)
                {
                }
            };
        }

        public static function any(): object
        {
            return new class {
            };
        }
    }
}
