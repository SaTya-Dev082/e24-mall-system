import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/l10n/app_strings.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../domain/catalog_models.dart';
import 'catalog_providers.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();
  Timer? _debounce;
  @override
  void initState() {
    super.initState();
    _search.text = ref.read(catalogProvider).filters.search;
    Future.microtask(() => ref.read(catalogProvider.notifier).refresh());
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    _scroll.dispose();
    super.dispose();
  }

  CatalogFilters _withSearch(String search) {
    final f = ref.read(catalogProvider).filters;
    return CatalogFilters(
      search: search,
      category: f.category,
      brand: f.brand,
      minPrice: f.minPrice,
      maxPrice: f.maxPrice,
      availability: f.availability,
      sort: f.sort,
    );
  }

  void _searchChanged(String value) {
    _debounce?.cancel();
    // Invalidate in-flight results immediately, then debounce the next request.
    ref.read(catalogProvider.notifier).setSearchPending(_withSearch(value));
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (mounted) {
        ref.read(catalogProvider.notifier).refresh(_withSearch(value));
      }
    });
  }

  Future<void> _filters() async {
    _debounce?.cancel();
    final result = await showModalBottomSheet<CatalogFilters>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _FilterSheet(filters: _withSearch(_search.text)),
    );
    if (!mounted) return;
    if (result != null) {
      _search.text = result.search;
      await ref.read(catalogProvider.notifier).refresh(result);
    } else {
      await ref
          .read(catalogProvider.notifier)
          .refresh(_withSearch(_search.text));
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(catalogProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text(
          AppStrings.brand,
          style: TextStyle(fontWeight: FontWeight.w900, letterSpacing: -1),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () {
          _debounce?.cancel();
          return ref
              .read(catalogProvider.notifier)
              .refresh(_withSearch(_search.text));
        },
        child: CustomScrollView(
          controller: _scroll,
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      AppStrings.discover,
                      style: Theme.of(context).textTheme.headlineMedium
                          ?.copyWith(fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _search,
                            onChanged: _searchChanged,
                            textInputAction: TextInputAction.search,
                            onSubmitted: (value) {
                              _debounce?.cancel();
                              ref
                                  .read(catalogProvider.notifier)
                                  .refresh(_withSearch(value));
                            },
                            decoration: const InputDecoration(
                              labelText: AppStrings.search,
                              prefixIcon: Icon(Icons.search),
                              border: OutlineInputBorder(),
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        IconButton.filledTonal(
                          onPressed: _filters,
                          tooltip: AppStrings.filters,
                          icon: const Icon(Icons.tune),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    ref
                        .watch(categoriesProvider)
                        .when(
                          data: (categories) => SingleChildScrollView(
                            scrollDirection: Axis.horizontal,
                            child: Row(
                              children: [
                                ChoiceChip(
                                  label: const Text(AppStrings.all),
                                  selected: state.filters.category == null,
                                  onSelected: (_) => _selectCategory(null),
                                ),
                                for (final category in categories)
                                  Padding(
                                    padding: const EdgeInsets.only(left: 8),
                                    child: ChoiceChip(
                                      label: Text(category.name),
                                      selected:
                                          state.filters.category ==
                                          category.slug,
                                      onSelected: (_) =>
                                          _selectCategory(category.slug),
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          loading: () => const LinearProgressIndicator(),
                          error: (error, _) => TextButton.icon(
                            onPressed: () => ref.invalidate(categoriesProvider),
                            icon: const Icon(Icons.refresh),
                            label: const Text(AppStrings.reloadCategories),
                          ),
                        ),
                    const SizedBox(height: 20),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          state.filters.search.isEmpty &&
                                  state.filters.toQuery().length == 1
                              ? AppStrings.latest
                              : AppStrings.products,
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        TextButton(
                          onPressed: () {
                            _debounce?.cancel();
                            _search.clear();
                            ref
                                .read(catalogProvider.notifier)
                                .refresh(const CatalogFilters());
                          },
                          child: const Text(AppStrings.resetFilters),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            if (state.loading)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(child: CircularProgressIndicator()),
                ),
              )
            else if (state.items.isEmpty && state.error == null)
              const SliverToBoxAdapter(
                child: Padding(
                  padding: EdgeInsets.all(32),
                  child: Text(AppStrings.empty, textAlign: TextAlign.center),
                ),
              ),
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              sliver: SliverLayoutBuilder(
                builder: (context, constraints) {
                  final columns = constraints.crossAxisExtent >= 700
                      ? 3
                      : constraints.crossAxisExtent < 340
                      ? 1
                      : 2;
                  // Row layout keeps cards usable with large accessibility text.
                  return SliverList.builder(
                    itemCount: (state.items.length / columns).ceil(),
                    itemBuilder: (_, row) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          for (var column = 0; column < columns; column++) ...[
                            if (column > 0) const SizedBox(width: 12),
                            Expanded(
                              child: row * columns + column < state.items.length
                                  ? _ProductCard(
                                      state.items[row * columns + column],
                                    )
                                  : const SizedBox(),
                            ),
                          ],
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            if (state.error != null)
              SliverToBoxAdapter(
                child: RetryState(
                  error: state.error!,
                  onRetry: () {
                    if (state.nextCursor != null) {
                      ref.read(catalogProvider.notifier).loadMore();
                    } else {
                      ref.read(catalogProvider.notifier).refresh();
                    }
                  },
                ),
              ),
            if (state.loadingMore)
              const SliverToBoxAdapter(
                child: Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(),
                  ),
                ),
              )
            else if (state.nextCursor != null && state.error == null)
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: OutlinedButton(
                    onPressed: () =>
                        ref.read(catalogProvider.notifier).loadMore(),
                    child: const Text(AppStrings.loadMore),
                  ),
                ),
              ),
            const SliverToBoxAdapter(child: SizedBox(height: 32)),
          ],
        ),
      ),
    );
  }

  void _selectCategory(String? category) {
    _debounce?.cancel();
    final f = _withSearch(_search.text);
    ref
        .read(catalogProvider.notifier)
        .refresh(
          CatalogFilters(
            search: f.search,
            category: category,
            brand: f.brand,
            minPrice: f.minPrice,
            maxPrice: f.maxPrice,
            availability: f.availability,
            sort: f.sort,
          ),
        );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard(this.product);
  final Product product;
  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: () =>
          context.push('/products/${Uri.encodeComponent(product.slug)}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AspectRatio(
            aspectRatio: 1,
            child: ProductImage(product.image, label: product.name),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  product.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 8),
                Text(
                  catalogPrice(product.price),
                  style: Theme.of(context).textTheme.titleMedium
                      ?.copyWith(fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 4),
                Text(
                  product.available
                      ? AppStrings.available
                      : AppStrings.unavailable,
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _FilterSheet extends ConsumerStatefulWidget {
  const _FilterSheet({required this.filters});
  final CatalogFilters filters;
  @override
  ConsumerState<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends ConsumerState<_FilterSheet> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _min, _max;
  late String? _category, _brand, _availability;
  late String _sort;
  @override
  void initState() {
    super.initState();
    final f = widget.filters;
    _min = TextEditingController(text: f.minPrice);
    _max = TextEditingController(text: f.maxPrice);
    _category = f.category;
    _brand = f.brand;
    _availability = f.availability;
    _sort = f.sort;
  }

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  Widget _options(
    String label,
    AsyncValue<List<CatalogOption>> options,
    String? selected,
    ValueChanged<String?> onChanged,
    VoidCallback retry,
  ) => options.when(
    data: (items) => DropdownButtonFormField<String>(
      initialValue: items.any((i) => i.slug == selected) ? selected : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: [
        DropdownMenuItem<String>(value: null, child: Text(label)),
        ...items.map(
          (i) => DropdownMenuItem(
            value: i.slug,
            child: Text(i.name, overflow: TextOverflow.ellipsis),
          ),
        ),
      ],
      onChanged: onChanged,
    ),
    loading: () => const LinearProgressIndicator(),
    error: (_, _) =>
        TextButton(onPressed: retry, child: Text(AppStrings.reload(label))),
  );
  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      20,
      20,
      20,
      MediaQuery.viewInsetsOf(context).bottom + 20,
    ),
    child: Form(
      key: _form,
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              AppStrings.filters,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            _options(
              AppStrings.allCategories,
              ref.watch(categoriesProvider),
              _category,
              (v) => _category = v,
              () => ref.invalidate(categoriesProvider),
            ),
            _options(
              AppStrings.allBrands,
              ref.watch(brandsProvider),
              _brand,
              (v) => _brand = v,
              () => ref.invalidate(brandsProvider),
            ),
            DropdownButtonFormField<String>(
              initialValue: _sort,
              isExpanded: true,
              decoration: const InputDecoration(labelText: AppStrings.sortBy),
              items: const [
                DropdownMenuItem(
                  value: 'newest',
                  child: Text(AppStrings.newest),
                ),
                DropdownMenuItem(
                  value: 'price_low',
                  child: Text(AppStrings.priceLow),
                ),
                DropdownMenuItem(
                  value: 'price_high',
                  child: Text(AppStrings.priceHigh),
                ),
              ],
              onChanged: (v) => _sort = v!,
            ),
            DropdownButtonFormField<String>(
              initialValue: _availability,
              isExpanded: true,
              decoration: const InputDecoration(
                labelText: AppStrings.availability,
              ),
              items: const [
                DropdownMenuItem<String>(
                  value: null,
                  child: Text(AppStrings.allStock),
                ),
                DropdownMenuItem(
                  value: 'in_stock',
                  child: Text(AppStrings.available),
                ),
                DropdownMenuItem(
                  value: 'unavailable',
                  child: Text(AppStrings.unavailable),
                ),
              ],
              onChanged: (v) => _availability = v,
            ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: TextFormField(
                    controller: _min,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: AppStrings.minPrice,
                    ),
                    validator: _validatePrice,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextFormField(
                    controller: _max,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: const InputDecoration(
                      labelText: AppStrings.maxPrice,
                    ),
                    validator: (v) {
                      final error = _validatePrice(v);
                      if (error != null) return error;
                      final min = double.tryParse(_min.text);
                      final max = double.tryParse(v ?? '');
                      return min != null && max != null && max < min
                          ? AppStrings.aboveMinimum
                          : null;
                    },
                  ),
                ),
              ],
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () {
                  if (!_form.currentState!.validate()) return;
                  Navigator.pop(
                    context,
                    CatalogFilters(
                      search: widget.filters.search,
                      category: _category,
                      brand: _brand,
                      minPrice: _min.text.trim(),
                      maxPrice: _max.text.trim(),
                      availability: _availability,
                      sort: _sort,
                    ),
                  );
                },
                child: const Text(AppStrings.showProducts),
              ),
            ),
          ],
        ),
      ),
    ),
  );
  String? _validatePrice(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    final number = double.tryParse(value);
    return number == null ||
            !number.isFinite ||
            number < 0 ||
            number > 9999999999.99
        ? AppStrings.validPrice
        : null;
  }
}
