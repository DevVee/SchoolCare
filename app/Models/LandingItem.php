<?php

namespace App\Models;

use App\Services\LandingContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One piece of public website content: a service, a common question (faq)
 * or a clinic team member. Edited under Administration → Website.
 */
class LandingItem extends Model
{
    /** section key => [admin URL segment, singular label, plural label] */
    public const SECTIONS = [
        'service' => ['segment' => 'services', 'singular' => 'service',  'plural' => 'Services'],
        'faq'     => ['segment' => 'faqs',     'singular' => 'question', 'plural' => 'Common questions'],
        'team'    => ['segment' => 'team',     'singular' => 'team member', 'plural' => 'Clinic team'],
    ];

    protected $fillable = [
        'section', 'title', 'subtitle', 'body', 'icon', 'image',
        'link_label', 'link_url', 'sort_order', 'is_enabled',
    ];

    protected $attributes = [
        'is_enabled' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Public page content is cached; any change shows up on the next visit.
        static::saved(fn () => LandingContent::forget());
        static::deleted(fn () => LandingContent::forget());
    }

    /** "services" → "service" (admin URL segment to section key). */
    public static function sectionFromSegment(string $segment): ?string
    {
        foreach (self::SECTIONS as $key => $meta) {
            if ($meta['segment'] === $segment) {
                return $key;
            }
        }

        return null;
    }

    public function scopeSection(Builder $query, string $section): Builder
    {
        return $query->where('section', $section);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.ltrim($this->image, '/')) : null;
    }
}
