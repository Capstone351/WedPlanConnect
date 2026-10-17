<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['portfolio_entry_id', 'path', 'caption', 'sort_order'])]
class PortfolioPhoto extends Model
{
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PortfolioEntry::class, 'portfolio_entry_id')->withTrashed();
    }

    /** Public URL; the controller only serves photos of published entries to visitors. */
    public function url(): string
    {
        return route('weddings.photo', $this);
    }
}
