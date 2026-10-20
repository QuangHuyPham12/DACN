<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'category_id', 'brand_id', 'description', 'is_active', 'material', 'fit_note', 'care_note', 'size_note'];

    protected $casts = ['is_active' => 'boolean'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): string
    {
        $image = $this->images->first();

        return $image ? route('media', ['filename' => basename($image->path)]) : asset('assets/shoe-'.(($this->id % 4) + 1).'.svg');
    }
}
