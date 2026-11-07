import 'package:go_router/go_router.dart';

import '../../features/products/presentation/home_screen.dart';
import '../../features/products/presentation/product_detail_screen.dart';

final appRouter = GoRouter(
  initialLocation: '/',
  errorBuilder: (context, state) => const HomeScreen(),
  routes: [
    GoRoute(
      path: '/products/:slug',
      name: 'product',
      builder: (context, state) =>
          ProductDetailScreen(slug: state.pathParameters['slug']!),
    ),
    GoRoute(
      path: '/',
      name: 'home',
      builder: (context, state) => const HomeScreen(),
    ),
  ],
);
