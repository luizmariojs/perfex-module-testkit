<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

/**
 * Stream wrapper que substitui o protocolo `php` enquanto uma requisição simulada está ativa.
 *
 * - `php://input` devolve o corpo simulado ({@see RequestSimulator});
 * - qualquer outro caminho `php://` (memory, temp, stdout, stderr, output, filter...) é aberto pelo
 *   wrapper nativo e as operações são repassadas ao handle real.
 *
 * @internal
 */
final class InputStreamWrapper
{
    /** Corpo servido em php://input. */
    public static string $body = '';

    /** @var resource|null */
    public $context;

    /** @var resource|null handle nativo, para caminhos que não são php://input */
    private $handle = null;

    private bool $isInput = false;

    private int $position = 0;

    public static function register(): void
    {
        if (in_array('php', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_register('php', self::class);
    }

    public static function unregister(): void
    {
        if (in_array('php', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('php');
        }
        stream_wrapper_restore('php');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        if (strtolower($path) === 'php://input') {
            $this->isInput  = true;
            $this->position = 0;

            return true;
        }

        $this->handle = $this->native(static fn () => @fopen($path, $mode));

        return $this->handle !== false && $this->handle !== null;
    }

    public function stream_read(int $count): string|false
    {
        if (!$this->isInput) {
            return fread($this->handle, $count);
        }

        $chunk           = (string) substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_write(string $data): int
    {
        if ($this->isInput) {
            return 0; // php://input é somente leitura
        }

        return (int) fwrite($this->handle, $data);
    }

    public function stream_eof(): bool
    {
        return $this->isInput ? $this->position >= strlen(self::$body) : feof($this->handle);
    }

    public function stream_tell(): int
    {
        return $this->isInput ? $this->position : (int) ftell($this->handle);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if (!$this->isInput) {
            return fseek($this->handle, $offset, $whence) === 0;
        }

        $target = match ($whence) {
            SEEK_CUR => $this->position + $offset,
            SEEK_END => strlen(self::$body) + $offset,
            default  => $offset,
        };
        if ($target < 0) {
            return false;
        }
        $this->position = $target;

        return true;
    }

    public function stream_flush(): bool
    {
        return $this->isInput || fflush($this->handle);
    }

    public function stream_truncate(int $new_size): bool
    {
        return !$this->isInput && ftruncate($this->handle, $new_size);
    }

    public function stream_close(): void
    {
        if (!$this->isInput && is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /** @return array<int|string, int>|false */
    public function stream_stat(): array|false
    {
        if (!$this->isInput) {
            return fstat($this->handle);
        }

        return ['size' => strlen(self::$body), 7 => strlen(self::$body), 'mode' => 0100444, 2 => 0100444];
    }

    public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
    {
        return false;
    }

    /** @return array<int|string, int>|false */
    public function url_stat(string $path, int $flags): array|false
    {
        return strtolower($path) === 'php://input'
            ? ['size' => strlen(self::$body), 7 => strlen(self::$body), 'mode' => 0100444, 2 => 0100444]
            : false;
    }

    /**
     * Executa $open com o wrapper nativo restaurado e reinstala este wrapper em seguida.
     *
     * @template T
     * @param callable(): T $open
     * @return T
     */
    private function native(callable $open): mixed
    {
        stream_wrapper_restore('php');
        try {
            return $open();
        } finally {
            stream_wrapper_unregister('php');
            stream_wrapper_register('php', self::class);
        }
    }
}
