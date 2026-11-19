<?php

namespace App\Modules\Mailing\Services\Relay;

use App\Modules\Mailing\Exceptions\RelayRejected;
use Illuminate\Support\Str;

/**
 * Mensagem MIME como o sistema do cliente mandou ao relay. O corpo segue intacto; dos cabeçalhos
 * só lemos o remetente e o assunto e tiramos o que não pode chegar ao SES.
 */
final readonly class RelayedMessage
{
    /**
     * `Bcc` exporia as cópias ocultas a todos (o SES não apaga em envio bruto). `Return-Path` é do
     * servidor que entrega, e um de outro domínio faz o SES recusar a mensagem.
     */
    private const DROPPED_HEADERS = ['bcc', 'return-path'];

    /**
     * @param  list<string>  $originators  endereços de `From` e `Sender`, em minúsculas
     */
    private function __construct(
        public string $raw,
        public array $originators,
        public string $subject,
    ) {}

    /** @throws RelayRejected */
    public static function parse(string $raw): self
    {
        if (preg_match("/\r?\n\r?\n/", $raw, $match, PREG_OFFSET_CAPTURE)) {
            $offset = $match[0][1];
            $headerBlock = substr($raw, 0, $offset);
            $rest = substr($raw, $offset);
        } else {
            $headerBlock = $raw;
            $rest = '';
        }

        $kept = [];
        $headers = [];
        $current = null;
        $dropping = false;

        foreach (preg_split("/\r?\n/", $headerBlock) as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
                if ($current !== null) {
                    $headers[$current][array_key_last($headers[$current])] .= ' '.trim($line);
                }
                if (! $dropping) {
                    $kept[] = $line;
                }

                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                throw RelayRejected::malformed('Cabeçalho da mensagem inválido.');
            }

            $current = Str::lower(trim(substr($line, 0, $colon)));
            $headers[$current][] = trim(substr($line, $colon + 1));
            $dropping = in_array($current, self::DROPPED_HEADERS, true);

            if (! $dropping) {
                $kept[] = $line;
            }
        }

        $from = array_merge(...array_map(self::addresses(...), $headers['from'] ?? []));

        if ($from === []) {
            throw RelayRejected::malformed('A mensagem não tem cabeçalho From com um endereço válido.');
        }

        $sender = array_merge(...array_map(self::addresses(...), $headers['sender'] ?? []));

        return new self(
            raw: implode("\r\n", $kept).$rest,
            originators: array_values(array_unique([...$from, ...$sender])),
            subject: self::decode($headers['subject'][0] ?? ''),
        );
    }

    public function fromAddress(): string
    {
        return $this->originators[0];
    }

    /** @return list<string> domínios dos remetentes, sem repetição */
    public function originatorDomains(): array
    {
        return array_values(array_unique(array_map(
            fn (string $address) => Str::after($address, '@'),
            $this->originators,
        )));
    }

    /** @return list<string> */
    private static function addresses(string $value): array
    {
        // "Nome, com vírgula" <a@b.com>, c@d.com (comentário)
        if (preg_match_all('/<([^<>\s]+@[^<>\s]+)>/', $value, $matches)) {
            $candidates = $matches[1];
        } else {
            $candidates = explode(',', preg_replace('/\([^)]*\)/', '', $value));
        }

        $addresses = [];

        foreach ($candidates as $candidate) {
            $address = Str::lower(trim($candidate, " \t\"'"));

            if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $address)) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }

    private static function decode(string $value): string
    {
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }
}
