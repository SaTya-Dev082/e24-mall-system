import 'package:flutter/foundation.dart';

class AppConfig {
  const AppConfig._();
  static const _definedUrl = String.fromEnvironment('API_BASE_URL');
  static String get apiBaseUrl {
    final url = _definedUrl.isNotEmpty
        ? _definedUrl
        : !kIsWeb && defaultTargetPlatform == TargetPlatform.android
        ? 'http://10.0.2.2:8000/api/v1'
        : 'http://localhost:8000/api/v1';
    final uri = Uri.tryParse(url);
    if (uri == null ||
        !uri.hasAuthority ||
        !['http', 'https'].contains(uri.scheme) ||
        (kReleaseMode && uri.scheme != 'https')) {
      throw StateError(
        'Provide a valid API_BASE_URL (HTTPS required for release).',
      );
    }
    return url.replaceFirst(RegExp(r'/+$'), '');
  }
}
