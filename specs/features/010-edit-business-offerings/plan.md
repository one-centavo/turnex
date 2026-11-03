# 010 · Edit Business Offerings — Plan

## Approach

Extend the commercial offerings domain by introducing an `update()` method within `OfferingService`, centralizing input sanitization, transactional persistence, and secure image storage lifecycle management (preserving, replacing, or explicitly removing image files on the public disk). On the presentation layer, implement a dedicated Livewire 4 Single-File Component at `/business/offerings/{offering}/edit` (`⚡edit.blade.php`) using Flux UI and semantic design tokens, strictly enforcing tenant isolation by returning a 404 response for offerings belonging to other merchants or non-existent records. An edit action trigger is added to each row in the catalog table (`⚡index.blade.php`), enabling seamless navigation, pre-filling, validation, cancellation, and redirection.

## Implementation

1. **Domain & Service Layer (`app/Services/OfferingService.php`)**:
   - Implement `update(Offering $offering, array $data, ?UploadedFile $image = null, bool $removeImage = false): Offering`:
     - Wrap persistence in a database transaction (`DB::transaction`).
     - Handle image lifecycle:
       - If a new valid `$image` is uploaded: store the new file (`storeImage()`), schedule old image deletion (`deleteImage()`), and update `reference_image`.
       - If `$removeImage` is true: delete the old image (`deleteImage()`) and set `reference_image = null`.
       - If no new image and not removing: retain the current `$offering->reference_image`.
     - Sanitize text inputs using `DataNormalizer::text()` (name) and `DataNormalizer::nullableText()` (description).
     - Normalize price (float, defaulting to 0.00 if empty), duration (integer or null), and active status (boolean).
     - Persist updates to the `$offering` instance and return the fresh model.

2. **Presentation & Livewire Layer**:
   - Register route in `routes/web.php` inside the `auth:business` middleware group:
     `Route::livewire('business/offerings/{offering}/edit', 'pages::business.offerings.edit')->name('business.offerings.edit');`
   - Create Livewire 4 Single-File Component `resources/views/pages/business/offerings/⚡edit.blade.php` wrapped in `layouts.business`:
     - In `mount(Offering $offering)`: verify that `$offering->business_id === Auth::guard('business')->id()`, calling `abort(404)` if unauthorized.
     - Bind public properties pre-filled from `$offering`:
       - `$name`, `$category_id`, `$description`, `$price`, `$estimated_duration_minutes`, `$is_active`, `$existing_image`, `$image = null`, `$remove_image = false`.
     - Dynamic category selection: load active categories via `Category::active()->orderBy('name')->get()`. If the offering's assigned category is archived, include it in the select list with an "(Archived)" label so its name remains displayed while permitting changes.
     - Reactive image interactions:
       - Real-time temporary image preview using Livewire `WithFileUploads`.
       - Action `discardTempImage()`: clears the temporary upload, restoring the view of the previously saved image.
       - Action `removeExistingImage()`: marks `$remove_image = true`, clears temporary upload, and displays the default placeholder.
     - Cancel action: back link/button pointing to `route('business.offerings.index')`.
     - Save action: validate input against business rules, execute `OfferingService::update()`, flash success notification (_"Service updated successfully."_), and redirect to `business.offerings.index`.
   - Update `resources/views/pages/business/offerings/⚡index.blade.php`:
     - Add an actions column in `<flux:table.columns>` and action button in `<flux:table.rows>` with an edit icon linking to `route('business.offerings.edit', $offering)`.

3. **Testing & QA**:
   - Create Pest feature test suite in `tests/Feature/OfferingEditingTest.php` verifying:
     - Unauthenticated guests redirected to `/business/login`.
     - Tenant isolation: editing an offering belonging to another business yields 404.
     - Accessing a non-existent offering ID yields 404.
     - Form correctly pre-populates existing offering data (name, category, price, duration, status, image preview).
     - Category dropdown displays active categories and handles archived category preservation gracefully.
     - Validation constraints: name required (3-50 chars, no whitespace-only), valid category, non-negative price, positive duration.
     - Successful update of fields without changing the existing image.
     - Uploading a new image stores new file and deletes old file from disk.
     - Discarding a temporary upload restores the existing image.
     - Removing existing image deletes it from disk and leaves `reference_image` null.
     - Toggling active status between active and inactive.
     - Submitting the form with no changes succeeds and redirects cleanly.
     - Cancel button redirects to catalog list without persisting modifications.
   - Run Pint and Larastan checks via Docker.

## Decisions

- **Dedicated `⚡edit.blade.php` SFC over Single Shared Form Component** — Maintains clean separation between creation and editing lifecycles. Editing requires complex existing file retention, image replacement/removal flags, pre-population, and tenant authorization checks that would otherwise overcomplicate the creation SFC with conditional logic.
- **Strict 404 on Unauthorized Tenant Access** — Aborting with a 404 instead of 403 prevents attackers or competitor merchants from confirming the existence of offering IDs belonging to other businesses (tenant enumeration prevention).
- **Dual-State Image Management in Livewire** — Tracking `$existing_image`, `$image` (new temporary upload), and `$remove_image` (boolean flag) provides clear, deterministic client-side reactivity without premature disk writes before form submission.
- **Transaction-Safe Image Cleanup** — If the database update fails, newly uploaded files are cleaned up immediately; old files are only deleted once the new database record is safely committed.

## Risks

- **Orphaned Files on Storage Disk** — Replacing or removing images could leave dead files on disk if the update transaction fails.
  - *Mitigation:* Perform file deletion only after a successful database commit, and delete newly uploaded files in the catch block if the transaction fails.
- **Cross-Tenant Parameter Tampering (IDOR)** — Malicious merchants could attempt to alter the offering ID in the URL to view or edit competitor catalog items.
  - *Mitigation:* Explicitly verify `$offering->business_id === Auth::guard('business')->id()` inside `mount()`, returning `abort(404)` on any mismatch.
- **Accidental Deletion of Image on Validation Error** — If validation fails when a new image was uploaded, the user should not lose their previous valid image.
  - *Mitigation:* The `$existing_image` path is preserved in component state and only overwritten after successful validation and save.
