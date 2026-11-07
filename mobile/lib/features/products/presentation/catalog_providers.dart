import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../data/catalog_repository.dart';
import '../domain/catalog_models.dart';

final categoriesProvider = FutureProvider<List<CatalogOption>>(
  (ref) => ref.watch(catalogRepositoryProvider).options('/categories'),
);
final brandsProvider = FutureProvider<List<CatalogOption>>(
  (ref) => ref.watch(catalogRepositoryProvider).options('/brands'),
);
final productProvider = FutureProvider.autoDispose.family<Product, String>((
  ref,
  slug,
) {
  final cancel = CancelToken();
  ref.onDispose(cancel.cancel);
  return ref
      .watch(catalogRepositoryProvider)
      .product(slug, cancelToken: cancel);
});

class CatalogState {
  const CatalogState({
    this.items = const [],
    this.filters = const CatalogFilters(),
    this.loading = false,
    this.loadingMore = false,
    this.nextCursor,
    this.error,
  });
  final List<Product> items;
  final CatalogFilters filters;
  final bool loading, loadingMore;
  final String? nextCursor;
  final Object? error;
}

final catalogProvider = NotifierProvider<CatalogController, CatalogState>(
  CatalogController.new,
);

class CatalogController extends Notifier<CatalogState> {
  CancelToken? _cancel;
  int _generation = 0;
  @override
  CatalogState build() {
    ref.onDispose(() {
      _generation++;
      _cancel?.cancel();
    });
    return const CatalogState();
  }

  void setSearchPending(CatalogFilters filters) {
    _generation++;
    _cancel?.cancel();
    state = CatalogState(filters: filters, loading: true);
  }

  Future<void> refresh([CatalogFilters? filters]) =>
      _load(filters ?? state.filters);
  Future<void> loadMore() async {
    if (state.loading || state.loadingMore || state.nextCursor == null) return;
    await _load(state.filters, cursor: state.nextCursor);
  }

  Future<void> _load(CatalogFilters filters, {String? cursor}) async {
    _cancel?.cancel();
    final cancel = _cancel = CancelToken();
    final generation = ++_generation;
    final previous = cursor == null ? <Product>[] : state.items;
    state = CatalogState(
      items: previous,
      filters: filters,
      loading: cursor == null,
      loadingMore: cursor != null,
      nextCursor: cursor,
    );
    try {
      final page = await ref
          .read(catalogRepositoryProvider)
          .products(filters, cursor: cursor, cancelToken: cancel);
      if (generation != _generation) return;
      final seen = <int>{};
      state = CatalogState(
        items: [
          ...previous,
          ...page.items,
        ].where((p) => seen.add(p.id)).toList(),
        filters: filters,
        nextCursor: page.nextCursor == cursor ? null : page.nextCursor,
      );
    } catch (error) {
      if (generation != _generation || cancel.isCancelled) return;
      state = CatalogState(
        items: previous,
        filters: filters,
        nextCursor: cursor,
        error: error,
      );
    }
  }
}
