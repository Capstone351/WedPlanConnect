<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['booking_id', 'title', 'event_date', 'venue', 'theme', 'summary', 'story', 'testimonial', 'testimonial_by', 'consent_confirmed', 'is_published', 'is_featured'])]
class PortfolioEntry extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'consent_confirmed' => 'boolean',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PortfolioEntry $entry) {
            if (! $entry->slug) {
                $base = Str::slug($entry->title) ?: 'wedding';
                $slug = $base;
                for ($i = 2; static::withTrashed()->where('slug', $slug)->whereKeyNot($entry->id)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $entry->slug = $slug;
            }

            // Never publish without the couple's consent (RA 10173).
            if (! $entry->consent_confirmed) {
                $entry->is_published = false;
                $entry->is_featured = false;
            }

            if ($entry->is_published && ! $entry->published_at) {
                $entry->published_at = now();
            }
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PortfolioPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('consent_confirmed', true);
    }

    /** First photo in display order serves as the cover. */
    public function cover(): ?PortfolioPhoto
    {
        return $this->relationLoaded('photos') ? $this->photos->first() : $this->photos()->first();
    }

    public function initials(): string
    {
        return collect(preg_split('/\s*&\s*|\s+and\s+/i', $this->title))
            ->map(fn ($part) => mb_substr(trim($part), 0, 1))->filter()->take(2)->join(' & ');
    }
}
