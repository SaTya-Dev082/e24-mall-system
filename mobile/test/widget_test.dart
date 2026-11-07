import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:shop_mobile/core/api/api_client.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shop_mobile/core/api/api_exception.dart';
import 'package:shop_mobile/features/products/data/catalog_repository.dart';
import 'package:shop_mobile/features/products/domain/catalog_models.dart';
import 'package:shop_mobile/features/products/presentation/home_screen.dart';
import 'package:shop_mobile/features/products/presentation/product_detail_screen.dart';

class TestCatalog extends CatalogRepository {
  TestCatalog() : super(ApiClient());
  bool fail = false;
  int listRequests = 0;
  @override
  Future<CatalogPage> products(
    CatalogFilters filters, {
    String? cursor,
    cancelToken,
  }) async {
    listRequests++;
    if (fail) throw const ApiException('Offline');
    return CatalogPage([
      Product.fromJson({
        'id': 1,
        'name': 'Real API Lamp',
        'slug': 'lamp',
        'price': '25.00',
        'has_available_stock': true,
      }),
    ], null);
  }

  @override
  Future<List<CatalogOption>> options(String path) async => [];
  @override
  Future<Product> product(String slug, {cancelToken}) async =>
      Product.fromJson({
        'id': 1,
        'name': 'Lamp',
        'slug': 'lamp',
        'price': '25.00',
        'variants': [
          {
            'id': 1,
            'name': 'Blue',
            'price': '30.00',
            'available_stock': 2,
            'status': 'active',
          },
          {
            'id': 2,
            'name': 'Red',
            'price': '35.00',
            'available_stock': 0,
            'status': 'active',
          },
        ],
      });
}

void main() {
  testWidgets(
    'detail back navigation preserves search and does not refetch catalog',
    (tester) async {
      final repo = TestCatalog();
      final router = GoRouter(
        routes: [
          GoRoute(path: '/', builder: (_, _) => const HomeScreen()),
          GoRoute(
            path: '/products/:slug',
            builder: (_, state) =>
                ProductDetailScreen(slug: state.pathParameters['slug']!),
          ),
        ],
      );
      addTearDown(router.dispose);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [catalogRepositoryProvider.overrideWithValue(repo)],
          child: MaterialApp.router(routerConfig: router),
        ),
      );
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Lamp');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      final requests = repo.listRequests;
      await tester.ensureVisible(find.text('Real API Lamp'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Real API Lamp'));
      await tester.pumpAndSettle();
      expect(find.text('Product details'), findsOneWidget);
      router.pop();
      await tester.pumpAndSettle();
      expect(
        tester.widget<TextField>(find.byType(TextField)).controller!.text,
        'Lamp',
      );
      expect(repo.listRequests, requests);
    },
  );
  testWidgets('catalog error can retry on small screen with large text', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final repo = TestCatalog()..fail = true;
    await tester.pumpWidget(
      ProviderScope(
        overrides: [catalogRepositoryProvider.overrideWithValue(repo)],
        child: MaterialApp(
          builder: (context, child) => MediaQuery(
            data: MediaQuery.of(context)
                .copyWith(textScaler: TextScaler.linear(1.5)),
            child: child!,
          ),
          home: const HomeScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Offline'),
      200,
      scrollable: find
          .descendant(
            of: find.byType(CustomScrollView),
            matching: find.byType(Scrollable),
          )
          .first,
    );
    expect(find.text('Offline'), findsOneWidget);
    repo.fail = false;
    await tester.ensureVisible(find.text('Try again'));
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();
    expect(find.text('Real API Lamp'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
  testWidgets('variant selection displays server price and unavailable state', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [catalogRepositoryProvider.overrideWithValue(TestCatalog())],
        child: const MaterialApp(home: ProductDetailScreen(slug: 'lamp')),
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Red'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Red'));
    await tester.pumpAndSettle();
    expect(find.text('This option is unavailable.'), findsOneWidget);
    expect(find.text('\$35.00'), findsNWidgets(2));
    expect(find.text('Add to cart'), findsNothing);
  });
}
