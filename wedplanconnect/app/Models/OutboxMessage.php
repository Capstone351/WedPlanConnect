<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['to', 'subject', 'html'])]
class OutboxMessage extends Model
{
    public const UPDATED_AT = null;

    /** Number of messages kept; older ones are pruned automatically. */
    public const KEEP = 100;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** True while emails are only written to the log instead of being delivered. */
    public static function enabled(): bool
    {
        return config('mail.default') === 'log';
    }

    /** The 6-digit PIN contained in an OTP email, if any. */
    public function pin(): ?string
    {
        return preg_match('/letter-spacing:10px;font-weight:bold">\s*(\d{6})/', (string) $this->html, $m) ? $m[1] : null;
    }
}
