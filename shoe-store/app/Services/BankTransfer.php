<?php

namespace App\Services;

use App\Models\SalesOrder;
use Illuminate\Support\Str;

class BankTransfer
{
    public function snapshot(): ?array
    {
        $bank = [
            'bin' => trim((string) config('payment.bank_bin')),
            'bank' => trim((string) config('payment.bank_name')),
            'number' => trim((string) config('payment.account_number')),
            'name' => strtoupper(Str::ascii(trim((string) config('payment.account_name')))),
        ];

        return $this->valid($bank) ? $bank : null;
    }

    private function valid(array $bank): bool
    {
        return preg_match('/^[0-9]{6}$/', $bank['bin'] ?? '')
            && preg_match('/^[0-9]{6,25}$/', $bank['number'] ?? '')
            && preg_match('/^[A-Z0-9 .-]{2,60}$/', $bank['name'] ?? '')
            && mb_strlen($bank['bank'] ?? '') >= 2 && mb_strlen($bank['bank']) <= 100;
    }

    public function reference(SalesOrder $order): string
    {
        return 'SOLE'.$order->code;
    }

    public function payload(SalesOrder $order): ?string
    {
        $bank = $order->bank_snapshot;
        if (! is_array($bank) || ! $this->valid($bank) || $order->total <= 0) {
            return null;
        }
        // EMVCo TLV / NAPAS VietQR account transfer. All payload values are ASCII.
        $receiver = $this->tlv('00', $bank['bin']).$this->tlv('01', $bank['number']);
        $merchant = $this->tlv('00', 'A000000727').$this->tlv('01', $receiver).$this->tlv('02', 'QRIBFTTA');
        $payload = '000201010212'.$this->tlv('38', $merchant).'5303704'
            .$this->tlv('54', (string) $order->total).'5802VN'
            .$this->tlv('62', $this->tlv('08', $this->reference($order))).'6304';

        return $payload.$this->crc($payload);
    }

    private function tlv(string $tag, string $value): string
    {
        return $tag.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    public function crc(string $value): string
    {
        $crc = 0xFFFF;
        foreach (str_split($value) as $byte) {
            $crc ^= ord($byte) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
