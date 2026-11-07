import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../api/api_exception.dart';
import '../config/app_config.dart';

class ProductImage extends StatelessWidget {
  const ProductImage(this.url, {super.key, this.label});
  final String? url, label;
  @override
  Widget build(BuildContext context) {
    final fallback = ColoredBox(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      child: const Center(child: Icon(Icons.image_outlined, size: 40)),
    );
    if (url == null || url!.isEmpty) return fallback;
    final resolved = Uri.parse(AppConfig.apiBaseUrl).resolve(url!).toString();
    return CachedNetworkImage(
      imageUrl: resolved,
      fit: BoxFit.contain,
      imageBuilder: (context, image) => Semantics(
        label: label,
        image: true,
        child: Image(image: image, fit: BoxFit.contain),
      ),
      memCacheWidth: 900,
      maxWidthDiskCache: 1200,
      placeholder: (_, _) => fallback,
      errorWidget: (_, _, _) => fallback,
    );
  }
}

class RetryState extends StatelessWidget {
  const RetryState({super.key, required this.error, required this.onRetry});
  final Object error;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.cloud_off_outlined, size: 36),
        const SizedBox(height: 12),
        Text(
          error is ApiException
              ? (error as ApiException).message
              : AppStrings.loadError,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 12),
        FilledButton.tonal(
          onPressed: onRetry,
          child: const Text(AppStrings.retry),
        ),
      ],
    ),
  );
}

String catalogPrice(String price) => '\$$price';
