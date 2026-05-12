<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiPublicProduct extends Model
{
    protected $table = 'ai_public_products';

    protected $fillable = [
        'feed_product_id',
        'title',
        'description',
        'public_url',
        'image_url',
        'price',
        'sale_price',
        'availability',
        'brand',
        'product_type',
        'google_product_category',
        'color',
        'size',
        'gender',
        'material',
        'condition',
        // Enrichment tags
        'frame_shape',
        'frame_material',
        'frame_size_category',
        'style_tags',
        'lightweight',
        'progressive_friendly',
        'strong_rx_friendly',
        'smart_glasses_relevant',
        'blue_light_relevant',
        'budget_tier',
        'is_active',
        'is_recommendable',
        'last_seen_in_feed',
    ];

    protected $casts = [
        'price'                 => 'decimal:2',
        'sale_price'            => 'decimal:2',
        'style_tags'            => 'array',
        'lightweight'           => 'boolean',
        'progressive_friendly'  => 'boolean',
        'strong_rx_friendly'    => 'boolean',
        'smart_glasses_relevant'=> 'boolean',
        'blue_light_relevant'   => 'boolean',
        'is_active'             => 'boolean',
        'is_recommendable'      => 'boolean',
        'last_seen_in_feed'     => 'datetime',
    ];

    public function scopeRecommendable(Builder $query): Builder
    {
        return $query->where('is_recommendable', true)
                     ->where('is_active', true)
                     ->where('availability', 'in stock');
    }

    public function scopeShape(Builder $query, string $shape): Builder
    {
        return $query->where('frame_shape', $shape);
    }

    public function scopeMaterial(Builder $query, string $material): Builder
    {
        return $query->where('frame_material', $material);
    }

    public function scopeSize(Builder $query, string $size): Builder
    {
        return $query->where('frame_size_category', $size);
    }

    public function scopeLightweight(Builder $query): Builder
    {
        return $query->where('lightweight', true);
    }

    public function scopeProgressiveFriendly(Builder $query): Builder
    {
        return $query->where('progressive_friendly', true);
    }

    public function scopeStrongRxFriendly(Builder $query): Builder
    {
        return $query->where('strong_rx_friendly', true);
    }

    public function scopeBudgetTier(Builder $query, string $tier): Builder
    {
        return $query->where('budget_tier', $tier);
    }

    public function getDisplayPriceAttribute(): string
    {
        $active = $this->sale_price ?? $this->price;

        return $active ? '$' . number_format((float) $active, 2) : 'See site for pricing';
    }

    public function toAdvisorArray(): array
    {
        return [
            'product_id'  => (string) $this->id,
            'title'       => $this->title,
            'price'       => $this->display_price,
            'image_url'   => $this->image_url,
            'public_url'  => $this->public_url,
            'color'       => $this->color,
            'size'        => $this->size,
            'shape'       => $this->frame_shape,
            'material'    => $this->frame_material,
            'style_tags'  => $this->style_tags ?? [],
        ];
    }
}
