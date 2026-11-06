# 015 · Explore Businesses Directory

**Status:** Complete

## What it does

This feature provides customers and public visitors with a centralized directory to discover verified local businesses, search by name or location, filter by commercial category, and check live queue occupancy before visiting.

From the top navigation bar or mobile menu, users can access the public "/explore" page. Here, visitors browse a responsive grid of establishment cards displaying the business cover banner, logo, commercial name, category badge, physical location, and real-time line occupancy (e.g., "Sin fila" or "3 personas en espera"). If an authenticated customer currently holds an active ticket in one of the displayed venues, that establishment card highlights this with an active turn badge.

Users can type keywords into a search bar to find establishments by commercial name or physical address with instant debounce, and select commercial category chips (such as Gastronomy, Health & Beauty, Professional Services, etc.) to narrow results without full page reloads. Search and category filter states synchronize with the URL query parameters so filtered listings can be bookmarked or shared. Selecting any business card navigates directly to that business's public storefront (`/b/{slug}`), where visitors can inspect offerings and request a virtual turn.

## Why

Currently, customers can only access a business storefront if they possess its direct URL or physical QR code. There is no central directory where patrons can discover nearby registered businesses, compare queue wait times, or plan errands around venue congestion. 

A public exploration directory transforms Turnex into a discovery hub, helping customers find venues with low wait times while driving organic customer traffic to registered merchants.

## Acceptance Criteria

- [x] Customers and public visitors can access the `/explore` directory directly via the navigation bar and header brand links.
- [x] Only verified, approved businesses (`VerificationStatus::Approved`) with at least one active offering are displayed in the directory; unapproved, suspended, or catalog-empty businesses are excluded.
- [x] The directory displays businesses in a responsive grid displaying commercial name, category, cover photo or branded fallback placeholder, logo thumbnail, physical location, and live line occupancy.
- [x] Venues with 0 customers in line display a clear indicator (e.g., "Sin fila"), while venues with active tickets display the waiting count (e.g., "3 personas en espera").
- [x] If an authenticated customer currently holds an active queue ticket at a displayed business, the card highlights an active turn badge (e.g., "Turno activo").
- [x] Clicking any business card navigates directly to the business's public storefront (`/b/{slug}`).
- [x] Users can filter establishments by commercial category using category filter chips.
- [x] Users can search establishments in real time by commercial name or physical address.
- [x] Filter selection and search queries update the business list reactively without full page reloads.
- [x] Search query and category filter parameters are synchronized in the URL query string (`?search=...&category=...`) to allow bookmarking and link sharing.
- [x] Search input automatically trims leading and trailing whitespace, ignores whitespace-only inputs, and handles special characters safely.
- [x] Search matching is case-insensitive and accent-insensitive across business names and physical addresses.
- [x] Modifying any search query or category filter automatically resets directory pagination to page 1.
- [x] If search or category filter criteria produce no matching businesses, the system displays an informative empty state with an action button to reset all filters.
- [x] If an establishment has not uploaded a logo or cover banner, the card renders high-contrast, brand-compliant fallback graphics without broken image links.
- [x] Businesses with zero active offerings are automatically excluded from the directory listing.
- [x] The exploration directory is fully accessible to anonymous guests without requiring authentication or account creation.
- [x] The directory implements pagination (e.g., 9 or 12 businesses per page) to ensure fast initial page loads and light database queries.
- [x] Business cards and filters adapt seamlessly across mobile (single-column cards, scrollable category chips), tablet (2 columns), and desktop (3 to 4 columns).
- [x] All UI copy is strictly in Spanish, adhering to semantic design tokens (`bg-surface-canvas`, `bg-surface-card`, `text-text-primary`, `border-border-subtle`) without arbitrary Tailwind values.

## Out of Scope

- Device GPS geolocation and distance radius filtering ("Cerca de mí").
- Public customer reviews, star ratings, and written testimonials.
- Sponsored business ranking promotions and featured advertising banners.
- Advance table reservations or calendar-based appointment scheduling.
