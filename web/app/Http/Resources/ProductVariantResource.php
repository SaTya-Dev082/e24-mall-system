<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'available_stock' => $this->available_stock,
            'status' => $this->status,
            'attributes' => $this->whenLoaded('values', fn () => $this->values->map(fn ($selection) => [
                'attribute_id' => $selection->attribute_id,
                'attribute' => $selection->attribute?->name,
                'value_id' => $selection->attribute_value_id,
                'value' => $selection->value?->value,
            ])),
        ];
    }
}
