<?php

return [
    'banners' => ['title' => 'Banners', 'module' => 'promotions', 'table' => 'storefront_banners', 'name' => 'name', 'archive' => true, 'fields' => [
        'headline' => 'nullable|string|max:255', 'description' => 'nullable|string|max:1000', 'button_text' => 'nullable|string|max:80', 'name' => 'required|string|max:255', 'image_url' => 'required|url:http,https|max:2048', 'alt_text' => 'required|string|max:255', 'link_url' => 'nullable|url:http,https|max:2048', 'status' => 'required|in:active,inactive', 'sort_order' => 'required|integer|min:0|max:100000', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date',
    ]],

    'shipping-zones' => ['title' => 'Shipping zones', 'module' => 'shipping', 'table' => 'shipping_zones', 'name' => 'name', 'archive' => true, 'fields' => ['name' => 'required|string|max:255', 'shipping_method_id' => 'required|integer', 'province' => 'required|string|max:100', 'city' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:30', 'fee' => 'required|numeric|min:0|max:9999999999.99', 'status' => 'required|in:active,inactive']],
    'categories' => ['title' => 'Categories', 'module' => 'catalog', 'table' => 'categories', 'name' => 'name', 'archive' => true, 'fields' => [
        'name' => 'required|string|max:255', 'slug' => 'nullable|string|max:255', 'parent_id' => 'nullable|integer', 'description' => 'nullable|string|max:5000', 'status' => 'required|in:active,inactive', 'sort_order' => 'required|integer|min:0',
    ]],
    'brands' => ['title' => 'Brands', 'module' => 'catalog', 'table' => 'brands', 'name' => 'name', 'archive' => true, 'fields' => [
        'name' => 'required|string|max:255', 'slug' => 'nullable|string|max:255', 'description' => 'nullable|string|max:5000', 'status' => 'required|in:active,inactive',
    ]],
    'attributes' => ['title' => 'Attributes', 'module' => 'catalog', 'table' => 'attributes', 'name' => 'name', 'archive' => true, 'fields' => ['name' => 'required|string|max:100']],
    'attribute-values' => ['title' => 'Attribute values', 'module' => 'catalog', 'table' => 'attribute_values', 'name' => 'value', 'archive' => true, 'fields' => ['attribute_id' => 'required|integer', 'value' => 'required|string|max:100']],
    'coupons' => ['title' => 'Coupons', 'module' => 'promotions', 'table' => 'coupons', 'name' => 'code', 'archive' => true, 'fields' => [
        'code' => 'required|string|max:100', 'type' => 'required|in:fixed,percentage,free_shipping', 'value' => 'required|numeric|min:0|max:9999999999.99', 'min_order_amount' => 'required|numeric|min:0|max:9999999999.99', 'max_discount_amount' => 'nullable|numeric|min:0|max:9999999999.99', 'usage_limit' => 'nullable|integer|min:1|max:2147483647', 'start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date', 'status' => 'required|in:active,inactive',
    ]],
    'shipping-methods' => ['title' => 'Shipping methods', 'module' => 'shipping', 'table' => 'shipping_methods', 'name' => 'name', 'archive' => true, 'fields' => ['name' => 'required|string|max:255', 'description' => 'nullable|string|max:5000', 'fee' => 'required|numeric|min:0|max:9999999999.99', 'estimated_days' => 'nullable|string|max:100', 'status' => 'required|in:active,inactive']],
    'reviews' => ['title' => 'Review moderation', 'module' => 'reviews', 'table' => 'reviews', 'name' => 'comment', 'archive' => false, 'create' => false, 'fields' => ['status' => 'required|in:pending,approved,rejected']],
];
