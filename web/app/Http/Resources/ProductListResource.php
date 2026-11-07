<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'primary_image' => $this->primary_image_url,
            'rating_avg' => $this->rating_avg,
            'review_count' => $this->review_count,
            'sold_count' => $this->sold_count,
            'stock_status' => $this->stock_status,
            'has_available_stock' => $this->whenHas('has_available_stock', fn () => (bool) $this->has_available_stock),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => new BrandResource($this->whenLoaded('brand')),
        ];
    }
}
