<?php

namespace App\Platform\Security;

use Carbon\CarbonInterface;

/**
 * Marcos de aviso de vencimento de certificado digital, comuns ao certificado do emitente e ao da plataforma.
 */
final class CertificateExpiry
{
    /** Dias até o vencimento em que se avisa. 0 é o aviso de certificado já vencido. */
    public const NOTICE_DAYS = [30, 15, 7, 1, 0];

    /** Dias inteiros até o vencimento, arredondando para cima: faltando 3 horas, ainda é "1 dia". 0 = vencido. */
    public static function daysLeft(CarbonInterface $validUntil): int
    {
        return $validUntil->isPast() ? 0 : (int) ceil(now()->diffInDays($validUntil));
    }

    /** O marco em que o certificado está agora, ou null se ainda falta mais que o primeiro aviso. */
    public static function noticeFor(int $daysLeft): ?int
    {
        foreach (array_reverse(self::NOTICE_DAYS) as $notice) {
            if ($daysLeft <= $notice) {
                return $notice;
            }
        }

        return null;
    }

    /**
     * Se o marco atual ainda não foi avisado. Quando o agendamento fica dias sem rodar, sai só o aviso
     * do marco atual, não um por marco perdido.
     */
    public static function isDue(?int $notice, ?int $lastNotified): bool
    {
        return $notice !== null && ($lastNotified === null || $notice < $lastNotified);
    }
}
