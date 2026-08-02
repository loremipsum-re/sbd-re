---
name: ios-ui-design-system
description: >
  Build, define, and apply iOS design system foundations — typography, color, spacing,
  motion, haptics, depth, and component patterns — that produce premium native iOS
  interfaces. Use this skill when creating a new iOS app design system, defining design
  tokens (colors, type scales, spacing), choosing typography settings, setting up semantic
  color systems, configuring animation parameters, deciding between design approaches
  (blur vs. shadow, sheet vs. modal, etc.), writing SwiftUI view code that needs to look
  and feel premium, or when someone asks "how should I set up my design system for iOS."
  This skill provides concrete token values, code-ready parameters, and decision rules —
  not abstract theory. It does NOT evaluate existing designs — use ios-ui-critique for that.
  It does NOT provide screen-flow guidance — use ios-ui-screen-patterns for that.
---

# iOS Design System Skill

Concrete, code-ready design system foundations for premium iOS apps. Every value in this skill is specific — a point size, a color token name, a spring parameter, a spacing multiple. No theory without implementation.

---

## Typography System

### The SF Pro Scale

Apple's San Francisco font automatically uses SF Pro Text below 20pt and SF Pro Display at 20pt and above, adjusting tracking per size. Use Apple's predefined text styles to get Dynamic Type support automatically.

**The iOS type scale, with recommended use:**

| Style Name | Default Size | Weight | Tracking | Use For |
|---|---|---|---|---|
| `.largeTitle` | 34pt | Regular | 0.37 | Screen titles, hero numbers |
| `.title` | 28pt | Regular | 0.36 | Section headers, primary labels |
| `.title2` | 22pt | Regular | 0.35 | Secondary section headers |
| `.title3` | 20pt | Regular | 0.38 | Tertiary headers, card titles |
| `.headline` | 17pt | Semibold | -0.41 | List row primary text, emphasized labels |
| `.body` | 17pt | Regular | -0.41 | Default body text, descriptions |
| `.callout` | 16pt | Regular | -0.31 | Callout text, supporting labels |
| `.subheadline` | 15pt | Regular | -0.23 | Metadata, secondary row text |
| `.footnote` | 13pt | Regular | -0.08 | Timestamps, captions, tertiary info |
| `.caption1` | 12pt | Regular | 0.00 | Badge labels, small annotations |
| `.caption2` | 11pt | Regular | 0.07 | Legal text, minimum-size content |

**Decision rules:**
- Always use `.font(.body)`, `.font(.headline)`, etc. in SwiftUI — never hardcode point sizes. This gives you Dynamic Type for free.
- For custom fonts: map them to these size/weight slots and use `UIFontMetrics` to scale with Dynamic Type.
- Create hierarchy through **contrast**, not variety. A screen should use at most 3–4 type styles. Hierarchy comes from size jumps + weight differences + color differences acting together.
- Minimum readable size: 11pt. Never go smaller.
- Line height (leading): 120–130% of point size for body; 110–120% for display sizes.

**Hierarchy recipe — how to make important things look important:**

```
Primary data:    .title or .title2 + .semibold + .primary text color
Supporting label: .footnote or .caption1 + .regular + .secondary text color
```

This creates a dramatic visual gap. If your title and label look similar, the hierarchy is too weak.

### Custom Font Integration

If using a custom font instead of SF Pro:

1. Choose a font optimized for screen (Inter, Outfit, or similar variable fonts)
2. Map weights to the SF Pro scale: Regular → Regular, Semibold → Semibold
3. Adjust tracking manually — custom fonts don't get SF Pro's automatic optical tracking
4. Register with `UIFontMetrics` for Dynamic Type scaling:

```swift
let customFont = UIFont(name: "Inter-Regular", size: 17)!
let scaledFont = UIFontMetrics(forTextStyle: .body).scaledFont(for: customFont)
```

---

## Semantic Color System

### Why Semantic Colors

Hard-coded hex values are an anti-pattern. They break Dark Mode, ignore accessibility toggles (Increase Contrast), and require manual updates across the entire codebase when the palette changes.

Semantic colors define **purpose** — what the color does — not **appearance** — what the color looks like.

### The Token Architecture

Organize your color system into four layers:

**Layer 1 — Backgrounds**

| Token Name | Light Mode | Dark Mode | Purpose |
|---|---|---|---|
| `background.primary` | System white | System black | Full-screen backgrounds |
| `background.secondary` | `.secondarySystemBackground` | System dark gray | Grouped content, cards |
| `background.tertiary` | `.tertiarySystemBackground` | Deeper gray | Nested elements inside cards |
| `background.elevated` | White + subtle shadow | Slightly lighter dark gray | Floating elements, sheets |

**Layer 2 — Text / Labels**

| Token Name | Light Mode | Dark Mode | Purpose |
|---|---|---|---|
| `label.primary` | Near-black | Near-white | Primary content, titles |
| `label.secondary` | Medium gray | Medium light gray | Subtitles, descriptions |
| `label.tertiary` | Light gray | Darker gray | Placeholders, disabled text |
| `label.quaternary` | Very light gray | Very dark gray | Separator labels, hints |

**Layer 3 — Interactive**

| Token Name | Light Mode | Dark Mode | Purpose |
|---|---|---|---|
| `accent` | Your brand color | Adjusted brand color | All tappable elements, links, active states |
| `accent.secondary` | Lighter tint | Adjusted tint | Secondary buttons, selected backgrounds |
| `destructive` | System red | System red | Delete actions, destructive warnings |
| `success` | System green | System green | Success states, positive indicators |
| `warning` | System orange | System orange | Warning states, caution indicators |

**Layer 4 — Surfaces & Separators**

| Token Name | Light Mode | Dark Mode | Purpose |
|---|---|---|---|
| `separator` | `.separator` | `.separator` | List dividers, borders |
| `fill.primary` | `.systemFill` | `.systemFill` | Input field backgrounds |
| `fill.secondary` | `.secondarySystemFill` | `.secondarySystemFill` | Toggle tracks, slider backgrounds |
| `overlay` | Black @ 30% | Black @ 50% | Modal overlays, dimming layers |

**The accent color affordance rule (critical):**
Your accent color must appear ONLY on interactive elements — buttons, links, toggles (active state), navigation highlights. If it also appears on decorative text, background elements, or non-interactive icons, users can't tell what's tappable. This is one of the most common and damaging mistakes in iOS design.

### SwiftUI Implementation

```swift
// Use system semantic colors — they adapt automatically
.foregroundStyle(.primary)        // label.primary
.foregroundStyle(.secondary)      // label.secondary
.background(.background)          // background.primary (iOS 17+)

// For custom brand colors, define in Asset Catalog with
// Light/Dark/High Contrast variants, then reference:
Color("AccentBrand")

// System interactive colors
.tint(.accentColor)
```

### Contrast Requirements

| Text Type | Minimum Contrast Ratio | Check Against |
|---|---|---|
| Body text (17pt Regular) | 4.5:1 | Its background color |
| Large text (≥20pt or ≥14pt Bold) | 3:1 | Its background color |
| Interactive elements | 3:1 | Adjacent non-interactive elements |
| Small text (captions, footnotes) | 4.5:1 | Its background color |

Test in both Light and Dark modes. Test with "Increase Contrast" enabled in iOS Settings → Accessibility → Display & Text Size.

---

## Spacing System

### The 8pt Grid

All spacing values must be multiples of 8. This creates a mathematically predictable visual rhythm that the human brain perceives as "clean" even if the user can't articulate why.

**The spacing scale:**

| Token | Value | Use For |
|---|---|---|
| `space.xs` | 4pt | Tight internal padding (icon-to-label gap inside a button) |
| `space.sm` | 8pt | Compact spacing (between related elements, inner card padding on dense views) |
| `space.md` | 16pt | Standard spacing (default content margins, padding between list items) |
| `space.lg` | 24pt | Section spacing (between distinct content groups) |
| `space.xl` | 32pt | Major section spacing (between primary content blocks) |
| `space.2xl` | 48pt | Page-level spacing (top padding below navigation, hero spacing) |
| `space.3xl` | 64pt | Dramatic spacing (empty state illustrations, onboarding layouts) |

**Note:** 4pt is the only non-8-multiple allowed — it's the half-step for tight internal relationships.

**Decision rules:**
- Related elements → smaller spacing (8–16pt)
- Unrelated elements → larger spacing (24–32pt)
- If two things look too close together, they probably are → increase by one step
- Screen-edge margins: 16pt minimum on standard screens, 20pt on larger displays
- Apple's standard content margin: 16pt (matches system list insets)

### Layout Principles

- **Left-align** body text and data. Center alignment is only appropriate for titles in modal sheets or empty states — never for body copy.
- **Consistent margins**: the left margin of your content should create one strong vertical axis that the eye follows downward.
- **Progressive disclosure**: if a screen has more than 7–8 distinct data points, hide secondary data behind taps, expanding sections, or drill-down navigation.

---

## Corner Radii & Shape Language

### Continuous Curvature (Squircles)

Apple hardware uses continuous curvature — the transition from straight edge to curve is gradual, not abrupt. Standard CSS/UIKit `cornerRadius` creates a sharp transition. SwiftUI's `.clipShape(.rect(cornerRadius:))` with `cornerStyle: .continuous` produces the correct squircle:

```swift
.clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
```

**Always use `style: .continuous`** on any visible rounded rectangle. The `.circular` style (default) looks subtly wrong on iOS and doesn't match hardware geometry.

### Nesting Radii

When placing a child element inside a rounded parent (e.g., an image inside a card), the child's corner radius must be **mathematically nested** — not identical to the parent:

```
childRadius = parentRadius - paddingBetweenThem
```

Example: a card with `cornerRadius: 16` and `12pt` internal padding → the image inside should have `cornerRadius: 4` (16 - 12 = 4).

If the child radius matches the parent radius, the curves diverge visually and the result looks sloppy.

### Standard Corner Radii

| Element | Radius | Style |
|---|---|---|
| Full-screen modal sheets | 38pt (matches device corners) | `.continuous` |
| Cards, containers | 12–16pt | `.continuous` |
| Buttons | 10–12pt | `.continuous` |
| Input fields | 8–10pt | `.continuous` |
| Small chips, badges | 6–8pt | `.continuous` |
| Circular elements (avatars) | 50% (half of width/height) | `.circular` (exception) |

---

## Depth, Shadow & Translucency

### The Depth Hierarchy

iOS UI operates on a Z-axis. Content exists in layers. Higher layers cast shadows or use translucency to separate from lower layers.

**Decision framework — how to create depth:**

| Technique | When to Use | When NOT to Use |
|---|---|---|
| **Translucency / Blur (Liquid Glass)** | Navigation bars, tab bars, floating toolbars, sheets. When the user needs spatial awareness of what's behind the element. | On elements that need strong text contrast. Never blur behind body text. |
| **Soft shadow** | Cards, elevated buttons, floating action elements. When content needs to "lift" off the background. | On flat list items or inline elements. Shadows on every element creates visual noise. |
| **Background color change** | Grouped content (secondary background for cards on primary background). When separation is subtle. | When the element needs to feel "floating" — color change alone is flat. |

### Shadow Specifications

Premium iOS shadows are **soft, diffused, multi-layered, and optionally tinted** — never harsh or dark.

**Standard card shadow:**
```swift
.shadow(color: .black.opacity(0.04), radius: 1, y: 1)   // crisp edge
.shadow(color: .black.opacity(0.08), radius: 8, y: 4)    // soft diffusion
.shadow(color: .black.opacity(0.04), radius: 24, y: 12)  // ambient spread
```

**Elevated element (floating button, sheet):**
```swift
.shadow(color: .black.opacity(0.06), radius: 2, y: 1)
.shadow(color: .black.opacity(0.12), radius: 16, y: 8)
.shadow(color: .black.opacity(0.06), radius: 40, y: 20)
```

**What NOT to do:**
```swift
// ❌ Harsh single shadow — looks like 2015 Material Design
.shadow(color: .black.opacity(0.3), radius: 4, y: 2)
```

### Translucency (Liquid Glass)

In SwiftUI, system materials provide the blur/translucency effects:

```swift
.background(.ultraThinMaterial)    // Very subtle blur
.background(.thinMaterial)         // Standard navigation bar blur
.background(.regularMaterial)      // Moderate blur for sheets
.background(.thickMaterial)        // Heavy blur for prominent overlays
.background(.ultraThickMaterial)   // Nearly opaque
```

These adapt automatically to Light/Dark mode and accessibility settings (Increase Contrast replaces blur with opaque backgrounds).

---

## Motion & Animation

### The Core Rule: Springs, Not Curves

Every user-triggered animation should use spring physics. Springs are interruptible (the user can grab an element mid-animation), feel natural (they model real-world mass and momentum), and never have a fixed duration that locks the UI.

**When to use springs:** Any animation triggered by user interaction — taps, swipes, drags, toggles, navigation transitions.

**When NOT to use springs:** Automated processes — loading spinners, progress bars, typing cursors. Use linear or ease-in-out for these.

### Spring Parameter Reference

| Context | SwiftUI Code | Feel |
|---|---|---|
| **Button tap** | `.spring(.snappy)` or `.spring(duration: 0.3, bounce: 0.15)` | Quick, crisp, minimal bounce |
| **Toggle switch** | `.spring(.snappy)` | Responsive snap |
| **Modal present** | `.spring(.smooth)` or `.spring(duration: 0.4, bounce: 0.0)` | Smooth slide, no bounce |
| **Card expand** | `.spring(duration: 0.35, bounce: 0.2)` | Slight organic overshoot |
| **Pull-to-refresh** | `.spring(.bouncy)` or `.spring(duration: 0.5, bounce: 0.3)` | Playful feedback |
| **Sheet dismiss** | `.spring(duration: 0.3, bounce: 0.0)` | Clean exit |
| **List item appear** | `.spring(duration: 0.4, bounce: 0.1)` with staggered delay | Subtle cascade |
| **Error shake** | `.spring(duration: 0.3, bounce: 0.4)` | Noticeable wobble |

**What NOT to do:**
```swift
// ❌ Fixed-duration easing — feels robotic, not interruptible
withAnimation(.easeInOut(duration: 0.3)) { ... }

// ❌ Linear — feels mechanical
withAnimation(.linear(duration: 0.5)) { ... }

// ❌ Default animation with no parameters — unpredictable
withAnimation { ... }
```

### Performance Rules for Animation

- Animate GPU-friendly properties: `.scaleEffect`, `.rotationEffect`, `.offset`, `.opacity`
- Avoid animating layout properties: `.frame`, `.padding` — these trigger expensive CPU layout passes
- Use `.geometryGroup()` (iOS 17+) to isolate animation subtrees from parent layout changes
- Always bind animations to specific values: `.animation(.spring(.snappy), value: isExpanded)`

### Reduce Motion Support

When the user enables "Reduce Motion" in system settings, replace all spring animations with simple crossfades:

```swift
@Environment(\.accessibilityReduceMotion) var reduceMotion

withAnimation(reduceMotion ? .easeInOut(duration: 0.2) : .spring(.snappy)) {
    // state change
}
```

---

## Haptic Feedback

### The Haptic Hierarchy

Haptics are not optional polish — they are how premium iOS apps communicate through touch. Map haptic intensity to event significance.

| Event Type | Haptic | SwiftUI Code |
|---|---|---|
| **Tap confirmation** | Light impact | `UIImpactFeedbackGenerator(style: .light).impactOccurred()` |
| **Toggle / switch** | Light impact | `UIImpactFeedbackGenerator(style: .light).impactOccurred()` |
| **Selection change** (picker, segment) | Selection changed | `UISelectionFeedbackGenerator().selectionChanged()` |
| **Success** (save, complete) | Success notification | `UINotificationFeedbackGenerator().notificationOccurred(.success)` |
| **Warning** | Warning notification | `UINotificationFeedbackGenerator().notificationOccurred(.warning)` |
| **Error / failure** | Error notification | `UINotificationFeedbackGenerator().notificationOccurred(.error)` |
| **Heavy action** (delete, major state change) | Medium/heavy impact | `UIImpactFeedbackGenerator(style: .medium).impactOccurred()` |
| **Drag threshold** (snap point) | Rigid impact | `UIImpactFeedbackGenerator(style: .rigid).impactOccurred()` |

**Decision rules:**
- Light events (taps, selections) → light/selection haptics
- Significant events (save, toggle) → success notification or light impact
- Destructive events (delete) → medium impact or warning
- Critical failures → error notification
- Never use haptics for passive/automatic events (loading complete, background sync)
- Never use heavy impact for routine actions — it feels like an error

### Synchronization

Haptic feedback must fire at the **moment of state change** — when the toggle snaps, when the item deletes, when the save confirms. Not before (premature), not after (delayed). Fire the haptic in the same call that triggers the visual change.

---

## Iconography

### SF Symbols

SF Symbols is the default icon system for iOS. Over 5,000 symbols, designed to scale with Dynamic Type and match SF Pro's weight.

**Usage rules:**
- Use SF Symbols for all standard UI icons (navigation, actions, status indicators)
- Match symbol weight to the text weight it appears beside: `.regular` text → `.regular` symbol
- Use `.symbolRenderingMode(.hierarchical)` for subtle depth or `.palette` for multi-color
- Active tab bar items: filled variant (e.g., `house.fill`). Inactive: outline (e.g., `house`)

### Custom Icons

When SF Symbols don't cover your needs (brand-specific icons, category illustrations):

- Match **stroke weight** to SF Symbols (typically 1.5–2pt at standard sizes)
- Match **corner radii** to your element corner radius system
- Provide all icon weights if appearing alongside text (Regular, Medium, Semibold, Bold)
- Provide Light and Dark mode variants if using color fills
- Export at @1x, @2x, @3x for proper device resolution
- Maintain a consistent optical size — all icons should feel the same "weight" when placed side by side

---

## Component Decision Rules

Quick decision framework for common component choices:

| Decision | Choose A | Choose B |
|---|---|---|
| **Navigation: 3–5 sections?** | Bottom Tab Bar | — |
| **Navigation: 2 sections?** | Segmented Control at top | Bottom Tab Bar (if sections are heavy) |
| **Show detail?** | Push navigation (NavigationLink) | — |
| **Quick action on list item?** | Swipe actions (`.swipeActions`) | Context menu (long press) |
| **User input: date/time?** | Native `DatePicker` — always | Never build a custom one |
| **User input: selection from list?** | Native `Picker` or `.menu` | Never build a custom dropdown |
| **Show secondary content?** | Draggable bottom sheet (`.sheet`) | — |
| **Confirm destructive action?** | System alert (`Alert`) with destructive button | — |
| **Confirm non-destructive action?** | Don't confirm. Just do it with undo support. | — |
| **Loading content?** | Skeleton/shimmer placeholders | Never a blocking spinner |
| **Success feedback?** | Haptic + brief animation (checkmark, color flash) | Never a modal alert saying "Saved!" |
| **Error feedback?** | Inline message near the error location | Only use modal alert for critical/destructive errors |
| **Empty list?** | Illustrated empty state with context + CTA | Never blank screen or "No Data" |
| **Onboarding?** | Interactive, 3–5 screens, value-first | Never a login wall before value |
| **Notifications permission?** | Pre-permission screen explaining value, then system prompt | Never on first launch with no context |

---

## The Premium Polish Checklist

The difference between a 7/10 "good" app and a 9/10 "premium" app. Reference this after the design system is functional to elevate quality.

**Micro-interaction polish:**
- [ ] Buttons depress slightly on touch-down (not just on release) with haptic feedback
- [ ] Every toggle snaps with a selection haptic
- [ ] Pull-to-refresh has a spring bounce
- [ ] Success states play a brief, non-blocking animation (checkmark, color pulse)
- [ ] Navigation transitions use directional push/pop, not fades

**Visual polish:**
- [ ] All containers use `.continuous` corner style (squircles)
- [ ] Nested corner radii are calculated (parent radius - padding = child radius)
- [ ] Shadows are multi-layered and soft — no harsh single shadows
- [ ] Icons are uniform (same stroke weight, corner radius, optical size)
- [ ] The accent color appears only on interactive elements

**System integration polish:**
- [ ] Dynamic Type scaling to AX5 without layout breaks
- [ ] Dark Mode looks intentionally designed, not just inverted
- [ ] Reduce Motion replaces springs with crossfades
- [ ] Increase Contrast deepens colors and removes translucency
- [ ] Keyboard type matches the input field (number pad for phone, email keyboard for email)

**Edge-case polish:**
- [ ] Every empty state has an illustration, context sentence, and CTA
- [ ] Offline state shows cached content or a helpful message with retry
- [ ] Error states are specific and actionable, near the error location
- [ ] VoiceOver reads content in semantic groups with meaningful labels
- [ ] Long text wraps gracefully — no truncation that hides critical information
