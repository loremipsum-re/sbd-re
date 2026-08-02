---
name: ios-ui-screen-patterns
description: >
  Provide screen-specific design guidance for iOS apps — what each screen type should
  contain, how it should behave, common mistakes to avoid, and premium patterns to follow.
  Use this skill when designing a specific iOS screen or flow: onboarding, dashboard, feed,
  detail page, search, forms, settings, checkout, notifications, empty states, or success/error
  states. Also use when someone asks "how should my settings screen work on iOS," "what
  should my onboarding flow look like," "how do I design a good dashboard," or "what are
  best practices for iOS forms." This skill provides per-screen-type recipes with user goals,
  required elements, premium patterns, and anti-patterns. It does NOT evaluate existing
  designs — use ios-ui-critique for that. It does NOT define design tokens or code-level
  specs — use ios-ui-design-system for that.
---

# iOS Screen Patterns Skill

Screen-by-screen recipes for iOS interfaces. Each pattern defines what the user needs at that moment, what the screen must contain, what premium execution looks like, and what mistakes to avoid.

Use this skill when designing or reviewing a specific screen type. Combine with the `ios-ui-design-system` skill for token-level implementation details.

---

## How to Use This Skill

1. Identify which screen type you're designing from the table of contents below
2. Read the user's goal for that screen type — this frames every design decision
3. Apply the "must have" elements — these are non-negotiable
4. Choose from the premium patterns to elevate beyond functional
5. Check the anti-patterns to avoid known failures

---

## Screen Type Index

| Screen Type | User's Primary Question |
|---|---|
| [Onboarding](#onboarding) | "What does this app do and is it worth my time?" |
| [Home / Dashboard](#home--dashboard) | "What's happening and what should I do next?" |
| [Feed / List](#feed--list) | "Show me content and let me act on it quickly." |
| [Detail Page](#detail-page) | "Tell me everything about this specific item." |
| [Search](#search) | "Help me find something specific — fast." |
| [Create / Edit Form](#create--edit-form) | "Let me input this data with minimum friction." |
| [Settings / Profile](#settings--profile) | "Let me manage my preferences and account." |
| [Checkout / Payment](#checkout--payment) | "Let me finish this transaction with confidence." |
| [Empty States](#empty-states) | "Why is this screen blank and what do I do?" |
| [Success & Error States](#success--error-states) | "Did that work? What happened?" |
| [Permission Requests](#permission-requests) | "Why do you need this and what do I get?" |
| [Notifications Center](#notifications-center) | "What's new since I was last here?" |

---

## Onboarding

**User goal:** Understand the app's value proposition and reach the "aha moment" as fast as possible.

**The golden rule:** The user must experience core value before being asked to create an account or give up personal information.

### Must Have
- 3–5 screens maximum — completion drops sharply after 5
- Visual demonstration of the core value, not just text descriptions
- Ability to skip the entire onboarding at any point
- Progress indication (dots, not "Step 3 of 5")
- Delayed sign-up — account creation after value is demonstrated

### Premium Patterns
- **Interactive onboarding** — let the user take a meaningful action during the flow (not just swipe through screens). Interactive tours outperform passive slideshow tours on completion and retention.
- **Custom illustrations** that match the brand, not stock graphics
- **Contextual permission requests** — if you need camera access, show the camera feature first, then request permission when the user tries to use it
- **Personalization step** — a brief preference selection that customizes the experience ("What are your goals?" with 3–4 options)
- **Animated transitions** between steps that reinforce the spatial metaphor

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Login wall on first launch** | Users haven't decided the app is worth their email yet. Conversion rates drop dramatically. |
| **10+ swipeable tutorial screens** | Users skip these instantly. You've wasted engineering time on something nobody reads. |
| **Requesting all permissions immediately** | Location + notifications + camera + contacts on screen 1 = universal denial. |
| **Text-heavy explanations** | "Our revolutionary platform empowers you to..." — nobody reads marketing copy in an onboarding flow. Show, don't tell. |
| **No skip option** | Users who feel trapped abandon the app, not the onboarding. |

---

## Home / Dashboard

**User goal:** Get a high-level overview of current status and determine the immediate next action in under 5 seconds.

### Must Have
- A clear visual focal point — the single most important metric or status
- Dramatic typographic hierarchy (primary data at 24–34pt; labels at 13pt)
- A single, obvious primary CTA for the most common next action
- Progressive disclosure — surface summary data, hide details behind taps

### Premium Patterns
- **Narrative structure** — the dashboard reads like a story: "Here's the situation → here's what changed → here's what you should do next"
- **Time-contextual greetings** — "Good morning, [Name]" with relevant data for the time of day
- **Glanceable cards** with clear titles, one key metric each, and obvious tap-to-expand affordance
- **Status indicators** using color semantically (green = good, amber = attention, red = problem) — with text labels, not color alone
- **Recent activity** feed showing the last 3–5 relevant events

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Raw data tables** | A dashboard is not a spreadsheet. Users need interpreted, prioritized information. |
| **No focal point** | If everything is the same size and weight, nothing is important and the user doesn't know where to look. |
| **Non-actionable metrics** | Showing data the user can't act on wastes screen real estate and attention. Every metric should connect to an action. |
| **Too many cards** | More than 4–5 cards above the fold creates cognitive overload. Progressive disclosure is the answer. |
| **Greeting-only hero** | A big "Welcome back!" banner that takes up 30% of the screen and contains no useful information. |

---

## Feed / List

**User goal:** Browse content efficiently and act on individual items without navigating away.

### Must Have
- Edge-to-edge layout for media-heavy content
- Chevron indicators (>) on rows that support drill-down navigation
- Swipe actions for contextual commands (swipe-to-delete at minimum)
- Pull-to-refresh
- Clear row hierarchy: primary text (headline weight) + secondary text (subheadline, secondary color)

### Premium Patterns
- **Direct manipulation** — swipe-left for destructive action (delete), swipe-right for positive action (archive, favorite)
- **Contextual menus** on long-press for additional actions without navigating away
- **Sticky section headers** that pin while scrolling through grouped content
- **Skeleton loading** that matches the exact shape of incoming list rows
- **Batch editing mode** with multi-select and bulk actions
- **Search integration** — sticky search bar at the top with recent/predictive results

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **No swipe actions** | Forcing users to open a detail view just to delete or archive an item adds unnecessary friction. |
| **Missing chevrons** | Without a visual indicator, users don't know which rows are tappable for drill-down. |
| **Over-padded cells** | Excessive padding reduces visible content, forcing more scrolling. Aim for 12–16pt vertical cell padding. |
| **Inconsistent row heights** | Rows that randomly change height break the scanning rhythm. Use a consistent layout template. |
| **No empty state** | An empty list with no explanation is a dead end. See [Empty States](#empty-states). |

---

## Detail Page

**User goal:** Get comprehensive information about a specific item while maintaining clear orientation (how to get back).

### Must Have
- Full back-navigation support (back button + left-edge swipe gesture)
- Contextual header that collapses on scroll (large title → inline title)
- High-quality imagery with consistent aspect ratios
- Clear action buttons in the toolbar or bottom of the screen

### Premium Patterns
- **Collapsing Liquid Glass header** — the title and hero image compress into a frosted toolbar on scroll, maintaining context
- **Contextual toolbar** that appears at the bottom with the most relevant actions for this item
- **Related content** section at the bottom suggesting similar items
- **Share sheet** integration accessible from the toolbar
- **Visual content hierarchy** — hero image → title → key metadata → description → actions → related

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Buried back button** | If the user can't find "back" in under 1 second, they feel trapped. |
| **Disabled back-swipe** | The single most disorienting gesture failure on iOS. Never disable this. |
| **No collapsing header** | Static headers waste valuable screen real estate on scroll. |
| **Action buttons at the top** | Critical actions should be in the thumb zone (bottom third), not requiring a thumb stretch. |

---

## Search

**User goal:** Find a specific item as fast as possible, or understand why no results exist.

### Must Have
- Sticky search bar (always accessible without scrolling up)
- Auto-focus keyboard when search is activated
- Recent searches shown before the user types anything
- Predictive/suggested results that update as the user types
- Actionable empty state when no results are found

### Premium Patterns
- **Scoped search** — let the user filter by category (e.g., "People," "Projects," "Messages") using a segmented control below the search bar
- **Trending or popular** suggestions shown before any query
- **Highlighted matching text** in results (bold the matching characters)
- **Voice search** integration for hands-free use
- **Search history management** — swipe to delete individual recent searches

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Hidden search** | If search requires more than one tap to access, it won't be used. |
| **Blank "No Results" screen** | "No Results Found" with nothing else is a dead end. Suggest alternatives, check spelling, or offer to broaden the search. |
| **No recent searches** | Users often search for the same things. Not showing recent queries forces redundant typing. |
| **Search only after pressing Enter** | Real-time filtering as the user types is expected on iOS. Waiting for a submit action feels sluggish. |

---

## Create / Edit Form

**User goal:** Input data accurately with the absolute minimum amount of friction and typing.

### Must Have
- Automatic keyboard type for each field (numeric pad for phone numbers, email keyboard for email, etc.)
- Inline validation — feedback appears next to the field as the user types/moves away, before submission
- Smart defaults — pre-fill anything you can (country, timezone, date)
- Clear labels above or inside (as floating labels) each field
- A visible, accessible submit button that's always reachable

### Premium Patterns
- **Progressive disclosure** for long forms — break into steps or collapsible sections rather than one massive scroll
- **Full-screen modal** for complex data entry tasks, isolating focus
- **Input formatting** — automatically format phone numbers, credit cards, dates as the user types
- **Undo/redo support** for text fields
- **Draft auto-save** — the form saves state if the user navigates away or the app backgrounds
- **Haptic confirmation** on successful submission

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Validation only on submit** | Surfacing 5 errors at the top of the form after the user thought they were done is deeply frustrating. |
| **Wrong keyboard type** | Making users manually switch to the number keyboard for a phone field is unnecessary friction that's trivial to fix. |
| **Long single-page forms** | A 15-field form on one screen is overwhelming. Break it up. |
| **Tiny submit button** | The submit action is the most important element on a form. Make it large (full-width), clearly labeled, and thumb-accessible. |
| **No draft saving** | Users lose work when they accidentally swipe away. Auto-save is expected in premium apps. |

---

## Settings / Profile

**User goal:** Manage preferences and account details quickly, with confidence that changes are saved.

### Must Have
- Standard iOS grouped list style (`.insetGrouped`)
- Logical grouping of preferences (Account, Preferences, Notifications, Support, Legal)
- Visible subscription/plan status if applicable
- Account deletion option, clearly accessible (Apple requires this)
- App version number at the bottom

### Premium Patterns
- **Profile card at the top** with avatar, name, and plan status
- **Inline toggles** that save immediately (no "Save" button needed for toggles)
- **Descriptive footers** below each settings group explaining what the settings control
- **Subscription management** with clear display of current plan, renewal date, and easy access to manage/cancel
- **Support access** (link to help, contact, or feedback) prominently placed

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Over-customized toggles** | If the user can't tell whether a toggle is on or off, you've failed. Use the standard iOS UISwitch/Toggle. |
| **Hidden account deletion** | Apple will reject your app. Users will hate you. Put it in plain view under Account. |
| **Hidden subscription cancellation** | Dark pattern. Destroys trust permanently. |
| **No grouped sections** | A flat list of 30 settings with no grouping is unscannable. Group by category. |
| **Settings that require a "Save" button** | Toggles and selections should save immediately. If a complex setting requires explicit saving, make it modal. |

---

## Checkout / Payment

**User goal:** Complete the transaction with total confidence and security, as fast as possible.

### Must Have
- Apple Pay as the default/primary payment option (if the user has it configured)
- Minimal form fields — don't ask for information Apple Pay already provides
- Clear price display with itemized breakdown
- Visual security indicators (lock icon, "Secure Checkout" text)
- Confirmation screen before final charge

### Premium Patterns
- **Single-screen checkout** — Apple Pay makes this possible by eliminating form fields
- **Deferred sign-up** — let the user complete checkout first, then offer account creation with "save your details for next time"
- **Order summary** visible at all times (sticky at top or bottom)
- **Haptic confirmation** on successful purchase
- **Instant receipt** delivered via email and shown in-app

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Manual card entry when Apple Pay is available** | Unnecessary friction. Apple Pay should be the hero CTA. |
| **Forced account creation before payment** | Every additional screen between "Buy" and "Purchased" bleeds conversion. |
| **Multi-page checkout** | Fragment the flow = lose the user. Consolidate. |
| **Asking for billing address** | Apple Pay provides this. Don't make the user re-enter it. |

---

## Empty States

**User goal:** Understand why the screen is empty and know exactly what to do to populate it.

**The golden rule:** Empty states are conversion opportunities, not dead ends. Every empty state should move the user toward their first action.

### Must Have
- A clear, specific explanation of why the screen is empty (not "No Data Found")
- A prominent primary CTA that resolves the empty state
- Context-appropriate language ("You haven't saved any workouts yet" not "No items")

### Premium Patterns
- **Custom illustration** that matches the brand aesthetic and relates to the content type
- **Encouraging, friendly tone** — "Your reading list is empty — add articles to save them for later"
- **Secondary suggestion** — if the primary action isn't available, offer an alternative ("Browse popular articles")
- **Educational moment** — briefly explain what this section does for users who may not understand

### The Empty State Formula

```
[Illustration — relevant, branded, not generic]

[Headline — what this section is for]
"Your saved workouts"

[Explanation — why it's empty, phrased from the user's perspective]
"Workouts you save will appear here for easy access."

[Primary CTA — the action that populates this screen]
[ + Save Your First Workout ]

[Optional secondary link]
"Browse popular workouts →"
```

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Blank white screen** | User doesn't know if the app is broken or just empty. |
| **"No Data Found"** | Generic, cold, unhelpful. Offers no path forward. |
| **"No Results" without suggestions** | In search: suggest alternative queries, check for typos, or broaden the scope. |
| **Empty state without a CTA** | You've explained the void but not offered a way out of it. |

---

## Success & Error States

**User goal:** Confirm that something worked, or understand what went wrong and how to fix it.

### Success States

**The golden rule:** Success should be acknowledged quickly and quietly. It should never interrupt the user's workflow with a modal dialog.

| Method | When to Use |
|---|---|
| **Haptic + subtle animation** | Default for most success events (save, send, toggle). Haptic `.success` + brief checkmark or color pulse. |
| **Toast / banner notification** | When the user needs to see a message ("Message sent") but shouldn't be interrupted. Auto-dismiss after 3 seconds. |
| **Inline state change** | When the UI itself reflects success (e.g., a "Save" button becomes "Saved ✓" briefly). |

**Never do this:** Show a modal alert saying "Settings Saved!" that requires the user to tap "OK" to dismiss. This is the #1 most common success-state anti-pattern.

### Error States

**The golden rule:** Errors should be specific, actionable, and shown near the cause — not as a generic modal.

| Error Type | Handling |
|---|---|
| **Form validation error** | Inline, next to the field, as soon as the user moves away. Red text, specific message ("Email must include @"). |
| **Network error** | Non-blocking banner: "No internet connection. Showing cached data." with "Retry" action. |
| **Server error** | "Something went wrong. Tap to retry." with a clear retry button. |
| **Destructive action confirmation** | Modal alert (this is the ONE appropriate use of modal alerts). Destructive button in red, cancel button prominent. |
| **Permission denied** | Explain what the app needs and link directly to iOS Settings. |

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **Modal alert for success** | Interrupts workflow. Creates alert fatigue. Users stop reading alerts. |
| **"Something went wrong" with no action** | Useless. The user needs to know what to do next. |
| **Error shown at top of page, not near the cause** | User has to scroll to find which field failed. Put the error next to the problem. |
| **Raw error codes** | "Error 422: Unprocessable Entity" means nothing to users. Translate to human language. |

---

## Permission Requests

**User goal:** Understand why the app needs this permission and what specific benefit they'll get.

### The Two-Step Pattern

Never trigger the iOS system permission prompt cold. Always show a **pre-permission screen** first:

1. **Your custom screen** — explains the value ("Enable notifications to get reminders before your workouts") with a branded illustration and a clear "Enable" button + "Not Now" option
2. **The system prompt** — only shown if the user taps "Enable" on your custom screen

This pattern dramatically increases grant rates because:
- Users understand the value before seeing the system prompt
- Users who tap "Not Now" on your screen never see the system prompt, preserving the ability to ask again later
- Users who deny the system prompt can never be asked again (iOS restriction)

### Contextual Timing

| Permission | When to Request |
|---|---|
| **Notifications** | After the user completes an action that notifications would enhance (e.g., after saving a workout: "Want reminders for your next session?") |
| **Camera** | The moment the user taps a camera-related feature (e.g., "Scan barcode") |
| **Location** | When the user accesses a location-dependent feature |
| **Contacts** | When the user initiates a contact-related action (e.g., "Invite friends") |

**Never** request any permission on first launch with no context.

---

## Notifications Center

**User goal:** See what's new and relevant since the last visit, without feeling spammed.

### Must Have
- Clear distinction between read and unread notifications
- Timestamps (relative: "2 hours ago," not absolute: "2:15 PM")
- Tappable notifications that navigate to the relevant content
- Bulk clear / mark-all-read option

### Premium Patterns
- **Grouped notifications** by category or conversation
- **Swipe actions** (swipe to mark as read, swipe to delete)
- **Rich notification content** — not just text, but inline images, action buttons
- **Smart ordering** — most relevant/actionable first, not just chronological
- **Badge count** on the tab bar icon that matches actual unread count

### Anti-Patterns to Avoid
| Anti-Pattern | Why It Fails |
|---|---|
| **No distinction between read/unread** | Users can't tell what's new. They stop checking. |
| **Notifications that don't link anywhere** | A notification that just shows text with no action is a dead end. |
| **Aggressive badge counts** | If the badge says "47" and most are marketing/low-value, users learn to ignore it. |
| **No way to clear notifications** | Users need control. If they can't clear, they feel spammed. |

---

## Cross-Cutting Patterns

These apply regardless of screen type.

### Thumb Ergonomics

Primary actions go in the **bottom third** of the screen — the natural thumb zone for one-handed use. Navigation and status information can live at the top.

```
┌──────────────────────┐
│  Status / titles     │  ← View-only, low interaction
│                      │
│  Content area        │  ← Scrollable content
│                      │
│  Primary actions     │  ← Thumb zone: CTAs, key buttons
│  Tab bar             │  ← Navigation
└──────────────────────┘
```

### Information Density Principle

- **Fitness/utility apps:** Higher density is acceptable — users want data at a glance
- **Social/media apps:** Lower density — content breathes, whitespace is generous
- **Enterprise/productivity:** Medium density with progressive disclosure — show summaries, hide details behind expand/drill-down

The right density depends on the app category and user expertise level. A stock trading app for professionals can be denser than a habit tracker for casual users.

### Consistency Across Screens

A button that looks and behaves one way on the dashboard must look and behave the same way on every other screen. Padding, colors, font sizes, interaction feedback, and animation timing should come from the design system tokens — never ad-hoc per screen.
