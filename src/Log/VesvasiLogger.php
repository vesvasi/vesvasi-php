<?php

declare(strict_types=1);

namespace Vesvasi\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\InvalidArgumentException;

final class VesvasiLogger implements LoggerInterface
{
    use LoggerTrait;

    private VesvasiLogProcessor $processor;
    private string $channel;
    private array $context = [];

    public function __construct(VesvasiLogProcessor $processor, string $channel = 'app')
    {
        $this->processor = $processor;
        $this->channel = $channel;
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!is_string($level)) {
            throw new InvalidArgumentException('Level must be a string');
        }

        $context = array_merge($this->context, $context);
        $context['channel'] = $this->channel;

        $this->processor->process(
            (string) $message,
            $level,
            $context
        );
    }

    public function withContext(array $context): self
    {
        $logger = new self($this->processor, $this->channel);
        $logger->context = array_merge($this->context, $context);
        return $logger;
    }

    public function withChannel(string $channel): self
    {
        $logger = new self($this->processor, $channel);
        $logger->context = $this->context;
        return $logger;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}