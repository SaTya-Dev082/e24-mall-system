import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shop_mobile/core/api/api_client.dart';
import 'package:shop_mobile/core/api/api_exception.dart';
import 'package:shop_mobile/features/products/data/catalog_repository.dart';
import 'package:shop_mobile/features/products/domain/catalog_models.dart';
import 'package:shop_mobile/features/products/presentation/catalog_providers.dart';

Product product(int id) => Product.fromJson({
  'id': id,
  'name': 'Product $id',
  'slug': 'product-$id',
  'price': '25.00',
  'has_available_stock': true,
});

class ControlledRepository extends CatalogRepository {
  ControlledRepository() : super(ApiClient());
  final requests = <Completer<CatalogPage>>[];
  @override
  Future<CatalogPage> products(
    CatalogFilters filters, {
    String? cursor,
    CancelToken? cancelToken,
  }) {
    final request = Completer<CatalogPage>();
    requests.add(request);
    return request.future;
  }
}

class ResponseAdapter implements HttpClientAdapter {
  ResponseAdapter(this.json, {this.status = 200});
  final Map<String, dynamic> json;
  final int status;
  RequestOptions? request;
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    request = options;
    return ResponseBody.fromString(
      jsonEncode(json),
      status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  test('repository uses real Laravel envelope and opaque cursor', () async {
    final adapter = ResponseAdapter({
      'data': [
        {
          'id': 1,
          'name': 'Lamp',
          'slug': 'lamp',
          'price': '25.00',
          'has_available_stock': true,
        },
      ],
      'meta': {'next_cursor': 'opaque-next'},
    });
    final dio = Dio(BaseOptions(baseUrl: 'https://store.test/api/v1'))
      ..httpClientAdapter = adapter;
    final page = await CatalogRepository(ApiClient(dio: dio)).products(
      const CatalogFilters(
        search: 'Lamp',
        minPrice: '20',
        availability: 'in_stock',
      ),
      cursor: 'opaque-old',
    );
    expect(adapter.request!.uri.path, '/api/v1/products');
    expect(adapter.request!.queryParameters['cursor'], 'opaque-old');
    expect(adapter.request!.queryParameters['min_price'], '20');
    expect(page.nextCursor, 'opaque-next');
    expect(page.items.single.price, '25.00');
    expect(page.items.single.available, isTrue);
  });
  test('malformed successful payload becomes a typed error', () async {
    final dio = Dio()..httpClientAdapter = ResponseAdapter({'data': 'invalid'});
    await expectLater(
      CatalogRepository(ApiClient(dio: dio)).products(const CatalogFilters()),
      throwsA(isA<ApiException>()),
    );
  });
  test('repository returns typed validation errors', () async {
    final dio = Dio()
      ..httpClientAdapter = ResponseAdapter({
        'errors': {
          'min_price': ['Invalid'],
        },
      }, status: 422);
    await expectLater(
      CatalogRepository(ApiClient(dio: dio)).products(const CatalogFilters()),
      throwsA(
        isA<ApiException>()
            .having((e) => e.status, 'status', 422)
            .having((e) => e.fields.keys, 'fields', contains('min_price')),
      ),
    );
  });
  test('stale search results are ignored; pagination is deduplicated and failure can retry', () async {
    final repository = ControlledRepository();
    final container = ProviderContainer(
      overrides: [catalogRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);
    final controller = container.read(catalogProvider.notifier);
    final old = controller.refresh();
    final fresh = controller.refresh(const CatalogFilters(search: 'new'));
    repository.requests[1].complete(CatalogPage([product(2)], 'next'));
    await fresh;
    repository.requests[0].complete(CatalogPage([product(1)], null));
    await old;
    expect(container.read(catalogProvider).items.single.id, 2);
    final failed = controller.loadMore();
    repository.requests[2].completeError(const ApiException('Offline'));
    await failed;
    expect(container.read(catalogProvider).nextCursor, 'next');
    final next = controller.loadMore();
    repository.requests[3].complete(
      CatalogPage([product(2), product(3)], null),
    );
    await next;
    expect(container.read(catalogProvider).items.map((p) => p.id), [2, 3]);
    final count = repository.requests.length;
    await controller.loadMore();
    expect(repository.requests.length, count);
  });
  test(
    'pending debounced search invalidates the prior request immediately',
    () async {
      final repository = ControlledRepository();
      final container = ProviderContainer(
        overrides: [catalogRepositoryProvider.overrideWithValue(repository)],
      );
      addTearDown(container.dispose);
      final controller = container.read(catalogProvider.notifier);
      final old = controller.refresh();
      controller.setSearchPending(const CatalogFilters(search: 'new'));
      repository.requests.single.complete(CatalogPage([product(1)], null));
      await old;
      expect(container.read(catalogProvider).items, isEmpty);
      expect(container.read(catalogProvider).filters.search, 'new');
    },
  );
}
