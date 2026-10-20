# Frontend Design System Specification

> **Version:** 1.0.0
>
> **Purpose:** Define the visual fundamentals, design tokens, component contracts and interaction rules to prevent inconsistencies in the frontend and accelerate the fullstack development.

## 01. Product Manifesto and Tone

### 1.1 Visual Personality

- **Archetype:** Utilitarian, precise, and restrained (high-performance operational tool / control panel focus).

- **User Feel:** Absolute control, predictability, and real-time certainty. The interface must convey frictionless speed and operational calm; zero distractions or unnecessary ornamentation.

- **Color Approach (60-30-10 Rule):**
    - **60% Surface / Neutrals:** Background structure, canvas, and base panels.

    - **30% Hierarchy / Contrast:** Primary typography, subtle 1px dividers, and data containers.

    - **10% Action Focus / Accent:** Exclusively reserved for primary interactive elements and critical states.

## 02. Color Tokens (Design Tokens)

### 2.1 Semantic Palette

Do not use arbitrary hex values in components. All elements must point to these semantic design tokens:

| Semantic Token           | Hex Value / CSS Token                  | Technical Role                | Exclusive Usage                                   |
| ------------------------ | -------------------------------------- | ----------------------------- | ------------------------------------------------- |
| `--color-surface-canvas` | `#0B0F17` (Dark) / `#F8FAFC` (Light)   | General background            | `body`, application backdrop.                    |
| `--color-surface-card`   | `#111827` (Dark) / `#FFFFFF` (Light)   | Base elevation                | Cards, modal panels, drawers.                     |
| `--color-surface-subtle` | `#1F2937` (Dark) / `#F1F5F9` (Light)   | Secondary surface             | Table headers, disabled inputs, subtle fills.     |
| `--color-border-subtle`  | `#1F2937` (Dark) / `#E2E8F0` (Light)   | Low-contrast dividers         | Row separators, container borders.                |
| `--color-border-strong`  | `#374151` (Dark) / `#CBD5E1` (Light)   | Interactive dividers          | Input borders, checkboxes, toggles.               |
| `--color-text-primary`   | `#F9FAFB` (Dark) / `#0F172A` (Light)   | Primary reading               | Headings (H1, H2), key data cells.                |
| `--color-text-secondary` | `#9CA3AF` (Dark) / `#64748B` (Light)   | Secondary reading             | Form labels, descriptions, breadcrumbs.           |
| `--color-brand-primary`  | `#2563EB` (Dark) / `#0284C7` (Light)   | Primary action (Electric Blue)| Primary buttons, active tabs, focus rings.        |
| `--color-brand-accent`   | `#FACC15` (Dark) / `#D97706` (Light)   | Dynamic accent (Lightning)    | High-priority indicators, VIP tags, energy highlights. |
| `--color-status-success` | `#10B981`                              | Positive feedback             | Completed/called ticket badges, online indicators.|
| `--color-status-warning` | `#F59E0B` (Dark) / `#D97706` (Light)   | Attention & waiting queue     | Approaching turns, pending review, non-blocking alerts. |
| `--color-status-danger`  | `#EF4444`                              | Destructive / Critical        | Validation errors, cancel ticket, delete actions. |

## 03. Typography and Text Scale

### 3.1 Font Families

- **Primary (UI & Body):** `Geist Sans` (Fallback: `Inter`, system-ui, sans-serif) — Clean, modern, high-legibility sans-serif for high-density interfaces.

- **Secondary (Data & Numbers):** `Geist Mono` (Fallback: `JetBrains Mono`, monospace) — Fixed-width font exclusively for ticket numbers (`#A-042`), timers (`04:15`), OTP codes, monetary amounts, UUIDs, and tabular figures.

### 3.2 Hierarchical Scale

| Level               | Size (rem / px)    | Font-Weight        | Line-Height | Intended Usage                                |
| ------------------- | ------------------ | ------------------ | ----------- | --------------------------------------------- |
| **H1 (Display)**    | `1.875rem` (30px)  | `700` (Bold)       | `1.2`       | Main module/page titles.                      |
| **H2 (Section)**    | `1.5rem` (24px)    | `600` (Semi-bold)  | `1.3`       | Card titles or major dashboard blocks.        |
| **H3 (Subsection)** | `1.125rem` (18px)  | `600` (Semi-bold)  | `1.4`       | Drawer and modal panel headers.               |
| **Body (Paragraph)**| `0.875rem` (14px)  | `400` (Regular)    | `1.5`       | General application text and descriptions.    |
| **Label / Caption** | `0.75rem` (12px)   | `500` (Medium)     | `1.4`       | Form input labels, table column headers, tags.|
| **Mono Data**       | `0.8125rem` (13px) | `500` (Medium)     | `1.0`       | Ticket IDs, timers, prices, UUIDs, logs.      |

## 04. Grid, Spacing and Layout Dimensions

### 4.1 Base Spacing Scale (Multiples of 4px / 8px)

Arbitrary values (`margin: 13px`, `padding: 7px`) are strictly prohibited. All layouts must conform to the standard spacing scale:

- `space-1` (`4px`): Micro-spacing (gap between icon and label inside badges or buttons).
- `space-2` (`8px`): Compact internal padding, gap between grouped action buttons.
- `space-3` (`12px`): Standard spacing between form inputs and compact table rows.
- `space-4` (`16px`): Base padding for cards, modals, and standard table cells (`p-4`).
- `space-6` (`24px`): Separation between sections within a single view or form block.
- `space-8` (`32px`): Top and bottom margins between primary layout containers.
- `space-12` (`48px`): Separation between full-page structural blocks.

### 4.2 Layout Architecture & Containers

- **Operational Dashboard (Split-Pane Architecture):**
  - **Desktop (≥ 1024px):** Fixed queue list pane on the left (`w-80` to `w-96`, 320px–384px) paired with an expansive ticket handling workspace on the right. Operators process and advance queues without page transitions.
  - **Fluid Bounds:** `w-full max-w-7xl` centered with a fixed navigation sidebar (`w-64`).

- **Customer Queue Tracking & Auth (Mobile-First Focused):**
  - **Container Constraint:** Centered card view strictly constrained to `max-w-md` (448px) on tablet and desktop.
  - **Mobile Interaction (< 640px):** 100% viewport width with ergonomic sticky bottom action bars (`fixed bottom-0 left-0 right-0 p-4`) for effortless one-handed thumb interaction.

---

## 05. Borders, Radius and Elevation (Depth)

### 5.1 Border Radius Scale (`border-radius`)

Precision compact geometry. Pill buttons (`rounded-full`) are strictly excluded to preserve the industrial control panel aesthetic:

- `rounded-sm` (`4px`): Status tags, tooltips, progress bars.
- `rounded-md` (`6px`): Standard buttons, input fields, selects, and dropdown trigger cells.
- `rounded-lg` (`8px` or `10px`): Cards, modal dialogs, flyout sheets, and contextual popovers.

### 5.2 Elevation & Depth Philosophy

- **Layered 1px Borders Over Heavy Shadows:** Elevation is driven primarily by 1px crisp borders rather than muddy dark shadows.
  - **Flat Base Surface (Cards & Panels):** `border: 1px solid var(--color-border-subtle); box-shadow: none;`
  - **Floating Elements (Modals, Dropdowns, Drawers):** `border: 1px solid var(--color-border-strong); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.25), 0 4px 6px -2px rgba(0, 0, 0, 0.1);`

## 06. Core Component Specifications

### 6.1 Buttons (`Button`)

- **Three Ergonomic Sizes:**
  1. **Small (`sm` / `h-8` - 32px):** Compact row-level actions inside data tables and secondary inline filters (`px-3 text-xs font-medium`).
  2. **Medium (`md` / `h-9` - 36px):** Standard operational buttons across dashboard headers, forms, and dialogs (`px-4 text-sm font-medium`).
  3. **Large (`lg` / `h-11` - 44px):** Primary mobile CTAs (e.g., "Join Queue", "Call Next Ticket") meeting touch-target accessibility standards (`px-6 text-base font-semibold`).

- **Mandatory Variants:**
  1. **Primary (Electric Blue):** Background `--color-brand-primary`, high-contrast text, borderless. Reserved for the primary positive action per screen.
  2. **Secondary / Outline:** Transparent or canvas background, 1px border `--color-border-strong`, text `--color-text-primary`. For filters, cancellations, or secondary workflows.
  3. **Ghost:** Transparent background in resting state; subtle highlight (`--color-surface-subtle`) on hover. For in-row actions and navigational tabs.
  4. **Destructive:** Background or text `--color-status-danger`. Strictly for irreversible or destructive operations (e.g., cancel ticket, delete record).

- **State Machine:** Explicit visual styling required for:
  - `Default`
  - `Hover` (5-10% brightness shift)
  - `Active / Pressed` (`scale-[0.98]`)
  - `Disabled` (`opacity-50 cursor-not-allowed`)
  - `Loading` (disables interactions, swaps icon for `animate-spin`).

### 6.2 Form Inputs (`Input / Select`)

- **Structural Layout:** Labels strictly positioned on top (`block text-xs font-medium text-secondary mb-1.5`).
- **Dimensions:** Fixed height `36px` (`h-9`), horizontal padding `12px` (`px-3`), font size `14px` (`text-sm`).
- **Focus Ring:** Browser default outline stripped. Use high-contrast brand ring: `focus:ring-2 focus:ring-brand-primary focus:border-transparent`.
- **Validation Errors:** Error message in `text-xs text-status-danger mt-1` accompanied by input border transitioning to `--color-status-danger`.

### 6.3 Data Tables (`Data Table`)

- **Header (`<thead>`):** Height `40px`, background `--color-surface-subtle`, font `text-xs font-semibold uppercase tracking-wider text-secondary`.

- **Rows (`<tr>`):** Minimum height `48px`. Bottom subtle border `1px solid var(--color-border-subtle)`.

- **Alignment Rules:**
    - Textual labels, client names, descriptions: **Left-aligned**.

    - Numerical quantities, prices, queue counters: **Right-aligned** using `Geist Mono`.

    - Status badges, short timestamps, row actions: **Center or Right-aligned**.

- **Real-Time Arrival Transition:** When a new ticket enters the queue via live polling (`wire:poll.5s`), the row enters with a gentle, non-intrusive highlight fade (`transition-colors duration-700`) without noisy pulsing banners.

### 6.4 Status Badges (`Badges`)

- **Structure & Style:** Soft Tint + Status Dot. Container with 10% background opacity and 20% border opacity of the status color, paired with a solid circular indicator dot (`size-1.5 rounded-full`) and medium weight text (`text-xs font-medium`).

- **Standard Roles:**
    - **Waiting / Approaching:** Amber / Yellow accent (`--color-status-warning` / `--color-brand-accent`).

    - **Called / Serving:** Electric Blue (`--color-brand-primary`).

    - **Completed / Verified:** Emerald Green (`--color-status-success`).

    - **Cancelled / Rejected:** Ruby Red (`--color-status-danger`).

## 07. Usability & Accessibility Heuristics (UX Heuristics)

1. **Reading Contrast (WCAG AA Compliance):** All text over background surfaces must satisfy a minimum contrast ratio of `4.5:1` for regular text and `3:1` for bold or large text (>18px).

2. **Keyboard Navigation & Focus Indicators:** Every interactive control must provide an unobstructed, visible focus indicator (`:focus-visible ring-2 ring-brand-primary`).

3. **Persistent Empty States:** No table or queue list may render empty. A dedicated container featuring an illustrative icon, explanatory text, and a primary CTA must guide the user.

4. **Data Mutation Collision Prevention:** Mutation buttons (calling tickets, changing status, submitting forms) must disable immediately upon click using `wire:loading.attr="disabled"` until the server acknowledges completion.
