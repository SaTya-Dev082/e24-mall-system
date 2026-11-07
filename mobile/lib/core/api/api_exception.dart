import 'package:dio/dio.dart';

class ApiException implements Exception {
  const ApiException(this.message, {this.status, this.fields = const {}});
  final String message;
  final int? status;
  final Map<String, dynamic> fields;

  factory ApiException.fromDio(DioException error) {
    final status = error.response?.statusCode;
    final body = error.response?.data;
    final message = switch (status) {
      401 => 'Your session has expired. Please sign in again.',
      403 => 'You do not have permission to view this content.',
      404 => 'This product is no longer available.',
      422 => 'Please check your search or filters.',
      429 => 'Too many requests. Please try again shortly.',
      _ =>
        status != null && status >= 500
            ? 'The store is temporarily unavailable. Please try again.'
            : 'Unable to connect. Check your connection and try again.',
    };
    return ApiException(
      message,
      status: status,
      fields: body is Map && body['errors'] is Map
          ? Map<String, dynamic>.from(body['errors'])
          : const {},
    );
  }
  @override
  String toString() => message;
}
