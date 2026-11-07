// Centralized English copy; add locale delegates/translations here in a later phase.
abstract final class AppStrings {
  static const brand = 'E24';
  static const search = 'Search products';
  static const latest = 'Latest products';
  static const retry = 'Try again';
  static const empty = 'No products found. Try changing your filters.';
  static const available = 'In stock';
  static const unavailable = 'Unavailable';
  static const loadMore = 'Load more';
  static const filters = 'Filters';
  static const allCategories = 'All categories';
  static const allBrands = 'All brands';
  static const allStock = 'Any availability';
  static const variants = 'Choose an option';
  static const noVariants = 'No purchasable options are currently available.';
  static const discover = 'Discover your next favorite';
  static const all = 'All';
  static const reloadCategories = 'Reload categories';
  static const products = 'Products';
  static const resetFilters = 'Reset filters';
  static const sortBy = 'Sort by';
  static const newest = 'Newest';
  static const priceLow = 'Price: low to high';
  static const priceHigh = 'Price: high to low';
  static const availability = 'Availability';
  static const minPrice = r'Min price ($)';
  static const maxPrice = r'Max price ($)';
  static const showProducts = 'Show products';
  static const validPrice = 'Enter a valid price';
  static const aboveMinimum = 'Must be ≥ minimum';
  static const productDetails = 'Product details';
  static const optionUnavailable = 'This option is unavailable.';
  static const description = 'Description';
  static const loadError = 'Unable to load the store. Please try again.';
  static String reload(String label) => 'Reload $label';
  static String imagePage(int page, int count) =>
      '$page / $count · Swipe to view images';
  static String stockCount(int count) => '$available ($count)';
  static String selected(String name) => 'Selected: $name';
}
