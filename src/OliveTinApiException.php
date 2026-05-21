<?php

declare(strict_types=1);

namespace OliveTin\Api;

/**
 * Thrown when OliveTin returns a non-success HTTP status or malformed JSON.
 */
final class OliveTinApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly ?string $connectCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function connectCode(): ?string
    {
        return $this->connectCode;
    }

    /**
     * @param array<string, mixed>|null $decoded
     */
    public static function fromHttpResponse(int $httpStatus, ?array $decoded, string $rawBody): self
    {
        if ($decoded !== null && isset($decoded['message']) && is_string($decoded['message'])) {
            $message = $decoded['message'];
        } elseif (trim($rawBody) !== '') {
            $message = $rawBody;
        } else {
            $message = 'HTTP ' . $httpStatus;
        }

        $code = ($decoded !== null && isset($decoded['code']) && is_string($decoded['code']))
            ? $decoded['code']
            : null;

        return new self($message, $httpStatus, $code);
    }
}
