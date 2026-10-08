<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'faq_id', 'question', 'answer', 'api_used'])]
class ChatbotSession extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['api_used' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(Faq::class)->withTrashed();
    }
}
