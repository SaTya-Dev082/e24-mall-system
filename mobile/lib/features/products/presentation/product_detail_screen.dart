import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../domain/catalog_models.dart';
import 'catalog_providers.dart';

class ProductDetailScreen extends ConsumerWidget {
  const ProductDetailScreen({super.key, required this.slug});
  final String slug;
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(title: const Text(AppStrings.productDetails)),
    body: ref
        .watch(productProvider(slug))
        .when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => Center(
            child: RetryState(
              error: error,
              onRetry: () => ref.invalidate(productProvider(slug)),
            ),
          ),
          data: (product) => _ProductDetail(
            key: ValueKey(product),
            product: product,
            onRefresh: () => ref.refresh(productProvider(slug).future),
          ),
        ),
  );
}

class _ProductDetail extends StatefulWidget {
  const _ProductDetail({
    super.key,
    required this.product,
    required this.onRefresh,
  });
  final Product product;
  final Future<void> Function() onRefresh;
  @override
  State<_ProductDetail> createState() => _ProductDetailState();
}

class _ProductDetailState extends State<_ProductDetail> {
  int? _variantId;
  int _page = 0;
  @override
  Widget build(BuildContext context) {
    final p = widget.product;
    final selected = p.variants.where((v) => v.id == _variantId).firstOrNull;
    return RefreshIndicator(
      onRefresh: widget.onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 700),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  AspectRatio(
                    aspectRatio: 1.15,
                    child: p.images.isEmpty
                        ? const ProductImage(null)
                        : PageView.builder(
                            itemCount: p.images.length,
                            onPageChanged: (index) =>
                                setState(() => _page = index),
                            itemBuilder: (_, index) => ProductImage(
                              p.images[index].url,
                              label: p.images[index].alt ?? p.name,
                            ),
                          ),
                  ),
                  if (p.images.isNotEmpty)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(
                          AppStrings.imagePage(_page + 1, p.images.length),
                        ),
                      ),
                    ),
                  const SizedBox(height: 16),
                  Text(
                    p.name,
                    style: Theme.of(context).textTheme.headlineSmall
                        ?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    catalogPrice(selected?.price ?? p.price),
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 20),
                  Text(
                    AppStrings.variants,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  if (p.variants.isEmpty) const Text(AppStrings.noVariants),
                  for (final variant in p.variants)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Card(
                        color: selected?.id == variant.id
                            ? Theme.of(context).colorScheme.secondaryContainer
                            : null,
                        child: ListTile(
                          onTap: () => setState(() => _variantId = variant.id),
                          leading: Icon(
                            selected?.id == variant.id
                                ? Icons.radio_button_checked
                                : Icons.radio_button_off,
                          ),
                          title: Text(variant.name),
                          subtitle: Text(
                            [
                              if (variant.attributes.isNotEmpty)
                                variant.attributes,
                              variant.available
                                  ? AppStrings.stockCount(variant.stock)
                                  : AppStrings.unavailable,
                            ].join('\n'),
                          ),
                          trailing: Text(
                            catalogPrice(variant.price ?? p.price),
                          ),
                        ),
                      ),
                    ),
                  if (selected != null)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Text(
                        selected.available
                            ? AppStrings.selected(selected.name)
                            : AppStrings.optionUnavailable,
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                    ),
                  const SizedBox(height: 16),
                  if (p.description.isNotEmpty) ...[
                    Text(
                      AppStrings.description,
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 12),
                    Text(p.description),
                  ],
                  const SizedBox(height: 32),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
