import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../domain/catalog_models.dart';

final apiClientProvider = Provider<ApiClient>((ref) {
  final client = ApiClient();
  ref.onDispose(() => client.dio.close());
  return client;
});
final catalogRepositoryProvider = Provider<CatalogRepository>(
  (ref) => CatalogRepository(ref.watch(apiClientProvider)),
);

class CatalogRepository {
  CatalogRepository(this.client);
  final ApiClient client;

  T _decode<T>(T Function() parse) {
    try {
      return parse();
    } on TypeError {
      throw const ApiException('The store returned an unreadable response.');
    } on FormatException {
      throw const ApiException('The store returned an unreadable response.');
    }
  }

  Future<Map<String, dynamic>> _get(
    String path, {
    Map<String, dynamic>? query,
    CancelToken? cancelToken,
  }) async {
    try {
      final response = await client.dio.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
        cancelToken: cancelToken,
      );
      return response.data!;
    } on DioException catch (error) {
      if (CancelToken.isCancel(error)) rethrow;
      throw ApiException.fromDio(error);
    } on FormatException {
      throw const ApiException('The store returned an unreadable response.');
    }
  }

  Future<CatalogPage> products(
    CatalogFilters filters, {
    String? cursor,
    CancelToken? cancelToken,
  }) async {
    final json = await _get(
      '/products',
      query: {...filters.toQuery(), 'limit': 20, 'cursor': ?cursor},
      cancelToken: cancelToken,
    );
    return _decode(
      () => CatalogPage(
        (json['data'] as List)
            .map((p) => Product.fromJson(Map<String, dynamic>.from(p)))
            .toList(),
        (json['meta'] as Map?)?['next_cursor'] as String?,
      ),
    );
  }

  Future<Product> product(String slug, {CancelToken? cancelToken}) async {
    final json = await _get(
      '/products/${Uri.encodeComponent(slug)}',
      cancelToken: cancelToken,
    );
    return _decode(
      () => Product.fromJson(Map<String, dynamic>.from(json['data'])),
    );
  }

  Future<List<CatalogOption>> options(String path) async {
    final json = await _get(path);
    return _decode(() {
      final data = json['data'] as List;
      final result = <CatalogOption>[];
      void add(List items) {
        for (final item in items) {
          result.add(CatalogOption.fromJson(Map<String, dynamic>.from(item)));
          if (item['children'] is List) add(item['children'] as List);
        }
      }

      add(data);
      return result;
    });
  }
}
