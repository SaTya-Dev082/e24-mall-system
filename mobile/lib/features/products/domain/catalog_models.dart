class CatalogOption {
  const CatalogOption(this.name, this.slug);
  final String name, slug;
  factory CatalogOption.fromJson(Map<String, dynamic> json) =>
      CatalogOption(json['name'] as String, json['slug'] as String);
}

class ProductVariant {
  ProductVariant.fromJson(Map<String, dynamic> json)
    : id = json['id'] as int,
      name = (json['name'] ?? json['sku'] ?? '') as String,
      price = json['price']?.toString(),
      stock = (json['available_stock'] as num).toInt(),
      active = json['status'] == 'active',
      attributes = (json['attributes'] as List? ?? [])
          .map((a) => '${a['attribute']}: ${a['value']}')
          .join(' · ');
  final int id, stock;
  final String name, attributes;
  final String? price;
  final bool active;
  bool get available => active && stock > 0;
}

class Product {
  Product.fromJson(Map<String, dynamic> json)
    : id = json['id'] as int,
      name = json['name'] as String,
      slug = json['slug'] as String,
      price = json['price'].toString(),
      image = json['primary_image'] as String?,
      available = json['has_available_stock'] == true,
      description =
          (json['description'] ?? json['short_description'] ?? '') as String,
      images = (json['images'] as List? ?? [])
          .map(
            (i) =>
                (url: i['image_url'] as String, alt: i['alt_text'] as String?),
          )
          .toList(),
      variants = (json['variants'] as List? ?? [])
          .map((v) => ProductVariant.fromJson(Map<String, dynamic>.from(v)))
          .toList();
  final int id;
  final String name, slug, price, description;
  final String? image;
  final bool available;
  final List<({String url, String? alt})> images;
  final List<ProductVariant> variants;
}

class CatalogPage {
  const CatalogPage(this.items, this.nextCursor);
  final List<Product> items;
  final String? nextCursor;
}

class CatalogFilters {
  const CatalogFilters({
    this.search = '',
    this.category,
    this.brand,
    this.minPrice,
    this.maxPrice,
    this.availability,
    this.sort = 'newest',
  });
  final String search, sort;
  final String? category, brand, minPrice, maxPrice, availability;
  Map<String, dynamic> toQuery() => {
    if (search.trim().isNotEmpty) 'search': search.trim(),
    if (category != null) 'category': category,
    if (brand != null) 'brand': brand,
    if (minPrice != null && minPrice!.isNotEmpty) 'min_price': minPrice,
    if (maxPrice != null && maxPrice!.isNotEmpty) 'max_price': maxPrice,
    if (availability != null) 'availability': availability,
    'sort': sort,
  };
}
