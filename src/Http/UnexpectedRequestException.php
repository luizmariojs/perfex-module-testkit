<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

use PerfexTestkit\TestkitError;

/**
 * Chamada HTTP feita ao RecordingTransport sem resposta enfileirada.
 */
final class UnexpectedRequestException extends TestkitError
{
}
