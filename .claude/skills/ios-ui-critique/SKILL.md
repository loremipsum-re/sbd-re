---
name: ios-ui-critique
description: >
  Evaluate, critique, and audit iOS user interfaces against premium quality standards.
  Use this skill whenever someone asks you to review an iOS design, critique an app's UI,
  run a design audit, evaluate whether a screen follows Apple's Human Interface Guidelines,
  identify what feels "off" about an iOS interface, compare a design against native iOS
  standards, or assess whether an app feels premium or amateur. Also use when asked to
  review screenshots, SwiftUI layouts, design mockups, or Figma exports of iOS screens.
  This skill produces structured audit reports with specific, actionable findings scored
  against a five-dimension rubric, anti-pattern identification, and prioritized fix
  recommendations. It does NOT generate code or build design systems — use the
  ios-ui-design-system skill for that. It does NOT provide screen-by-screen flow guidance —
  use the ios-ui-screen-patterns skill for that.
---

# iOS UI Critique & Audit Skill

Systematically evaluate any iOS user interface — from a single screen to an entire app — against the concrete, measurable standards that separate premium native iOS experiences from generic, amateur, or cross-platform ports.

This skill is not about subjective taste. Every evaluation dimension has specific, observable criteria. A finding is only valid if it can point to a concrete rule violation or quality gap.

---

## What This Skill Does

- Evaluate an iOS interface across five quality dimensions
- Score each dimension on a 0–10 scale with specific justification
- Identify anti-patterns and HIG violations with precise descriptions
- Detect the "web app in a native shell" syndrome
- Flag accessibility failures and edge-case gaps
- Produce prioritized, actionable fix recommendations
- Distinguish between cosmetic polish issues and structural design failures

---

## When to Use This Skill

- A user wants feedback on an iOS app design (screenshots, mockups, descriptions)
- A design review needs structured, objective evaluation criteria
- An app feels "off" and the user wants to know why
- Someone wants to compare their iOS UI against Apple-quality standards
- A design team wants a pre-launch quality audit checklist
- An app has been rejected or poorly reviewed for UX reasons and needs diagnosis
- Someone wants to understand the difference between "functional" and "premium" iOS UI

---

## When NOT to Use This Skill

- Building a design system from scratch → use `ios-ui-design-system`
- Designing a specific screen type (onboarding, settings, dashboard) → use `ios-ui-screen-patterns`
- Generating SwiftUI code → use `ios-ui-design-system` for token/pattern guidance, then write code
- Reviewing Android or cross-platform designs → this skill is iOS-specific
- Building guided tours or onboarding flows → use `guided-app-tour`

---

## Required Inputs

| Input | Description |
|---|---|
| **App name or context** | What the app does, who it's for |
| **Interface to evaluate** | Screenshot(s), screen description, SwiftUI code, or Figma export |
| **Evaluation scope** | Single screen, flow, or full app audit |

## Optional Inputs

| Input | Description |
|---|---|
| **Target iOS version** | iOS 17, 18, 26 — affects which patterns are current |
| **App category** | Productivity, fitness, social, finance — sets context for density expectations |
| **Known constraints** | Cross-platform requirement, legacy codebase, specific framework |
| **Priority focus** | "Focus on accessibility" or "Focus on premium polish" |
| **Comparison targets** | Specific apps to benchmark against |

---

## The Five Evaluation Dimensions

Every iOS UI audit evaluates against exactly five dimensions. Each is scored 0–10 with specific criteria at three quality tiers.

---

### Dimension 1: Visual Quality & Consistency

**What it measures:** The mathematical precision, visual harmony, and brand coherence of the interface.

| Score | Tier | Observable Criteria |
|---|---|---|
| **0–3** | Weak / Amateur | Misaligned elements. Random, inconsistent spacing (e.g., 10px here, 15px there). Generic unoptimized fonts. Harsh, single-layer drop shadows. Pixelated or low-resolution assets. Mismatched icon styles from different stock libraries. No visual rhythm. |
| **4–7** | Functional / Standard | Clean layout with consistent spacing. Standard system fonts used correctly. Consistent element sizing. But: lacks distinct brand personality. No custom iconography. Depth is flat or uses basic shadows. Adequate but forgettable. |
| **8–10** | Premium / Native iOS | Strict 8pt or 16pt grid maintained across every screen. Custom, uniform iconography with consistent stroke weights. Continuous curvature (squircles) on cards and containers with mathematically nested child radii. Masterful use of negative space. Soft, multi-layered, tinted shadows or Liquid Glass translucency for depth. Pixel-perfect alignment on every axis. |

**Specific checks:**
- [ ] Is a consistent spatial grid used? (Measure: are margins, paddings, and heights multiples of 8?)
- [ ] Do all icons share the same stroke weight, corner radius, and visual density?
- [ ] Are card/container corners continuous curvature (squircles) or basic CSS rounded rectangles?
- [ ] When elements are nested (image inside card), is the inner corner radius mathematically proportional to the outer radius?
- [ ] Is depth achieved through translucency/blur/semantic materials or through harsh opaque shadows?
- [ ] Is there a clear, predictable visual rhythm when scanning from top to bottom?
- [ ] Are all assets high-resolution (@2x/@3x) and crisp on target devices?

---

### Dimension 2: Native iOS Fit

**What it measures:** How deeply the interface aligns with iOS platform conventions, gestures, and system integrations.

| Score | Tier | Observable Criteria |
|---|---|---|
| **0–3** | Weak / Non-Native | Feels like a ported web app or Android app. Uses top-tab navigation. Hamburger menu hiding primary navigation. Floating Action Button (FAB). Custom pickers instead of native iOS pickers. Back-swipe gesture is broken or disabled. HTML-style dropdowns instead of native components. No Dark Mode support. |
| **4–7** | Functional / Standard | Uses standard UIKit or SwiftUI components correctly. Familiar navigation patterns (push/pop, tab bar). Supports Dark Mode. But: lacks advanced OS integrations. No use of SF Symbols. No Widgets, Live Activities, or system-level features. Feels correct but not deeply native. |
| **8–10** | Premium / Native iOS | Deeply integrated with iOS. Uses Liquid Glass materials and system vibrancy. SF Symbols throughout. Apple Pay integration where applicable. Widgets, Live Activities, or App Intents if relevant. Respects all system settings (Reduce Motion, Increase Contrast, Dynamic Type). Navigation feels identical to Apple's own apps. Edge-swipe back gesture works flawlessly everywhere. |

**Specific checks:**
- [ ] Is the primary navigation a bottom Tab Bar with 3–5 items? (Not a hamburger menu, not top tabs)
- [ ] Does the Tab Bar contain only navigational modes — not actions like "Create" or "Take Photo"?
- [ ] Does the native left-edge swipe-to-go-back gesture work on every screen?
- [ ] Are date pickers, time pickers, and selection lists using native iOS components?
- [ ] Does the app support Dark Mode correctly (not just inverted colors)?
- [ ] Does the app use SF Symbols or custom icons that match SF Symbol conventions?
- [ ] Are modals presented as draggable bottom sheets that dismiss with a downward swipe?
- [ ] Does the app respect the "Reduce Motion" system setting?

**Critical anti-patterns to flag immediately:**
1. **Hamburger menu** as primary navigation → always flag
2. **Floating Action Button** → Android pattern, never native on iOS
3. **Top tab bar** → Android pattern, iOS uses segmented controls or section headers
4. **Broken back-swipe** → critical failure, deeply disorients iOS users
5. **Custom date/time pickers** → unnecessary friction, use native controls
6. **Full-screen opaque modals with tiny X buttons** → hostile, use swipeable sheets

---

### Dimension 3: Interaction & Motion Quality

**What it measures:** How the interface responds to touch, the physics of animations, and the quality of multisensory feedback.

| Score | Tier | Observable Criteria |
|---|---|---|
| **0–3** | Weak / Static | Static, rigid interface. No animations at all, or animations using linear/ease-in-out curves that lock the user out until completion. Broken back-swipes. Zero haptic feedback. Buttons provide no visual response on tap. The app feels "dead." |
| **4–7** | Functional / Standard | Basic ease-in-out animations present. Some visual feedback on tap (color change). Haptics used only for system alerts. Animations are not interruptible — starting a gesture during a transition causes glitches. Adequate but not delightful. |
| **8–10** | Premium / Native iOS | Spring-based, interruptible animations throughout. Gestures track the finger perfectly — if the user stops mid-swipe and reverses, the element follows without snapping. Rich, synchronized multisensory feedback: CoreHaptics for subtle confirmation taps, crisp audio cues for critical actions. Every button depresses slightly on touch-down with immediate haptic feedback before the action fires. Sub-100ms response to all taps. |

**Specific checks:**
- [ ] Are animations spring-based (`.spring(response:dampingFraction:)`) rather than fixed-duration easing curves?
- [ ] Can the user interrupt a gesture-driven animation mid-way and reverse it smoothly?
- [ ] Does every tappable element provide immediate visual feedback on touch-down (not just on release)?
- [ ] Is haptic feedback used for confirmations, toggles, and success states (not just errors)?
- [ ] Are loading states handled with skeleton screens or optimistic updates (not blocking spinners)?
- [ ] Do transitions between screens use meaningful, directional motion (push, slide) rather than cuts or fades?

**Spring animation reference values (for code review):**

| Context | Recommended Parameters |
|---|---|
| Button press | `.spring(response: 0.3, dampingFraction: 0.6)` or `.snappy` |
| Modal presentation | `.spring(response: 0.4, dampingFraction: 0.8)` or `.smooth` |
| Card expansion | `.spring(response: 0.35, dampingFraction: 0.7)` |
| Pull-to-refresh bounce | `.spring(response: 0.5, dampingFraction: 0.6)` or `.bouncy` |
| Sheet dismiss | `.spring(response: 0.3, dampingFraction: 0.85)` |
| Toggle switch | `.spring(response: 0.25, dampingFraction: 0.7)` or `.snappy` |

**Red flags:**
- Any `.easeInOut` or `.linear` animation on an interactive element → flag
- Any `withAnimation(.default)` without explicit spring parameters → flag
- Any transition that blocks user input until completion → flag

---

### Dimension 4: Clarity & Usability

**What it measures:** How effortlessly a user can understand the interface hierarchy, find critical actions, and accomplish tasks.

| Score | Tier | Observable Criteria |
|---|---|---|
| **0–3** | Weak / Cluttered | Cluttered screens with no clear focal point. Touch targets smaller than 44×44pt. Unclear visual hierarchy — headers and body text are similar size/weight. Dense, cramped text. Center-aligned body copy. Users must hunt for primary actions. Multiple competing CTAs. |
| **4–7** | Functional / Standard | Logical layout. Information is accessible but may require excessive scrolling or tapping to find core actions. Hierarchy is present but not dramatic — primary and secondary information aren't clearly differentiated. Touch targets meet minimums. Adequate but requires effort. |
| **8–10** | Premium / Native iOS | "Boringly obvious" UX — every action is exactly where the user expects it. Thumb-friendly CTA placement in the bottom third of the screen. Masterful progressive disclosure: complex data revealed in layers, not all at once. Dramatic typographic hierarchy (e.g., 28pt semibold title vs. 13pt regular caption). Strong left-alignment. The 60-30-10 color rule applied: 60% neutral background, 30% secondary elements, 10% accent/interactive color. |

**Specific checks:**
- [ ] Are all interactive elements at least 44×44pt touch targets?
- [ ] Is the primary action placed in the bottom third of the screen (thumb zone)?
- [ ] Is the typographic hierarchy dramatic enough that a user can scan the screen in under 2 seconds and know what's most important?
- [ ] Is body text left-aligned (not center-aligned)?
- [ ] Are interactive elements visually distinct from non-interactive elements through color consistency? (The accent color is used ONLY for tappable elements)
- [ ] Is progressive disclosure used for complex data instead of cramming everything on one screen?
- [ ] Does every form use the correct keyboard type automatically (numeric pad for numbers, email keyboard for email)?
- [ ] Are search bars sticky and do they provide recent/predictive suggestions before the user types?

**The Accent Color Affordance Rule:**
The app's accent or tint color must be used exclusively for interactive elements (buttons, links, toggle accents, navigation highlights). If the same color appears on non-interactive decorative text or background elements, it destroys affordance clarity — users can't tell what's tappable. Flag this immediately.

---

### Dimension 5: Accessibility & Edge Cases

**What it measures:** How robustly the interface handles real-world conditions: accessibility needs, error states, empty states, offline mode, and system setting overrides.

| Score | Tier | Observable Criteria |
|---|---|---|
| **0–3** | Weak / Brittle | Text overlaps and layouts break when Dynamic Type is increased. Contrast ratio below 4.5:1 on body text. Empty states are blank white screens or "No Data Found." Errors are unhandled or show raw error codes. No VoiceOver labels. App crashes or shows broken UI when offline. |
| **4–7** | Functional / Standard | Supports basic Dynamic Type without layout breaks. Meets minimum contrast ratios (4.5:1). Generic but functional empty states. Error messages are user-friendly but not actionable. VoiceOver labels present but may be generic ("Button," "Image"). Offline state shows a message but not cached content. |
| **8–10** | Premium / Native iOS | UI reflows perfectly at 200% text size — horizontal layouts shift to vertical stacks. 4.5:1+ contrast everywhere, with support for "Increase Contrast" toggle. Empty states are beautifully illustrated with specific context ("You haven't saved any flights yet") and a clear primary CTA. Errors provide actionable recovery ("Tap to retry" or "Check your connection"). VoiceOver reads semantically grouped content with descriptive labels. Offline mode shows cached content gracefully. Success states use transient haptic + animation, not blocking alerts. |

**Specific checks:**
- [ ] Set Dynamic Type to the largest accessibility size (AX5) — does the layout survive without truncation, overlap, or broken alignment?
- [ ] Does the body text against its background meet 4.5:1 contrast ratio?
- [ ] Does the app respect the "Increase Contrast" accessibility toggle?
- [ ] Is every empty state actionable (explains the situation + provides a CTA to resolve it)?
- [ ] Are success states handled with non-blocking feedback (haptic, toast, animation) rather than modal alerts?
- [ ] Are error states specific and actionable, not generic ("Something went wrong")?
- [ ] Does the app degrade gracefully when offline (cached content, helpful message)?
- [ ] Are system alerts used only for critical, destructive, irreversible actions?
- [ ] Do VoiceOver labels describe elements meaningfully (not "button123" or "image")?
- [ ] Does the app respect "Reduce Motion" (heavy animations degrade to crossfades)?

---

## The Anti-Pattern Detection Engine

When auditing, actively scan for these known iOS anti-patterns. Each is a specific, named failure that should be called out by name in the audit report.

### Navigation Anti-Patterns

| Anti-Pattern | Description | Severity |
|---|---|---|
| **Hamburger Burial** | Primary navigation hidden behind a slide-out hamburger menu instead of a visible bottom Tab Bar | 🔴 Critical |
| **Action-Stuffed Tab Bar** | Placing functional actions ("Create," "Take Photo") in the Tab Bar alongside navigation modes | 🔴 Critical |
| **Broken Back-Swipe** | The native iOS left-edge swipe-to-go-back gesture is disabled or overridden | 🔴 Critical |
| **Android Top Tabs** | Using a top tab strip for primary navigation instead of a bottom Tab Bar or segmented control | 🟡 Major |
| **Navigation Labyrinth** | Nesting more than 3 levels of push navigation without providing shortcuts back | 🟡 Major |

### Interaction Anti-Patterns

| Anti-Pattern | Description | Severity |
|---|---|---|
| **Dead Buttons** | Buttons that provide zero visual or haptic feedback on tap — user unsure if tap registered | 🔴 Critical |
| **Hostile Loading** | Blocking the entire UI with a generic spinning wheel, preventing all interaction while data fetches | 🟡 Major |
| **Over-Alerting** | Using modal system alerts for non-destructive confirmations ("Settings Saved!") or basic information | 🟡 Major |
| **Custom Pickers** | Building custom UI for dates, times, or selections instead of native iOS pickers | 🟡 Major |
| **Un-Swipeable Modals** | Full-screen opaque modals with tiny close buttons that cannot be dismissed by swiping down | 🟡 Major |

### Visual Anti-Patterns

| Anti-Pattern | Description | Severity |
|---|---|---|
| **Affordance Confusion** | Using the accent/tint color on non-interactive decorative elements, destroying tap clarity | 🔴 Critical |
| **Flat Depth** | Using opaque gray backgrounds and harsh single-layer drop shadows instead of translucency/blur | 🟠 Moderate |
| **Geometric Discord** | Mismatched corner radii between parent containers and child elements; standard rounded rectangles instead of squircles | 🟠 Moderate |
| **Stock Icon Salad** | Icons from multiple different libraries with inconsistent stroke weights, corners, and styles | 🟠 Moderate |
| **Hard-Coded Colors** | Using absolute hex values instead of semantic color tokens; app breaks in Dark Mode | 🟡 Major |

### Content Anti-Patterns

| Anti-Pattern | Description | Severity |
|---|---|---|
| **Dead-End Empty State** | Blank white screens or "No Data Found" text with no context or action | 🔴 Critical |
| **Login Wall** | Forcing account creation or login before the user has experienced any app value | 🟡 Major |
| **Tutorial Overload** | 10+ swipeable onboarding screens that users skip instantly | 🟡 Major |
| **Dark Pattern Settings** | Hiding account deletion, subscription cancellation, or privacy controls behind obfuscated navigation | 🔴 Critical |
| **Premature Permission Request** | Requesting notification/location/camera permission on first launch without explaining the value | 🟡 Major |

---

## Audit Workflow

### Step 1: Gather Context

Before evaluating, understand:
- What does this app do and who is it for?
- What iOS version is it targeting?
- What category is it in? (Sets density and complexity expectations)
- Is it native SwiftUI/UIKit or cross-platform?
- What is the scope of the audit? (Single screen, flow, full app)

### Step 2: First-Impression Scan (3-Second Test)

Look at the screen for 3 seconds and answer:
1. What is the single most important thing on this screen?
2. Does the interface immediately communicate what I can do here?
3. Does it feel native iOS, or does something feel "off"?
4. Does it feel premium, functional, or amateur?

Record your gut impressions before detailed analysis. These often reveal the most impactful issues.

### Step 3: Systematic Dimension Scoring

Score each of the five dimensions (0–10). For each:
1. Review the specific checks listed under that dimension
2. Identify concrete observations (not vague impressions)
3. Assign the score based on which tier the observations align with
4. Note the 2–3 most significant observations that determined the score

### Step 4: Anti-Pattern Scan

Run through the anti-pattern tables. For each anti-pattern detected:
1. Name it specifically (use the anti-pattern name from the table)
2. Describe exactly where it appears
3. Note its severity rating
4. Recommend the specific fix

### Step 5: Prioritize Findings

Organize all findings into three priority tiers:

| Priority | Criteria | Action |
|---|---|---|
| **P0 — Critical** | Anti-patterns that destroy trust, break native behavior, or cause accessibility failures | Fix before any release |
| **P1 — Major** | Issues that significantly degrade the experience or make the app feel generic/amateur | Fix in current sprint |
| **P2 — Polish** | Issues that distinguish "good" from "premium" — micro-interactions, edge cases, depth | Fix for quality milestone |

### Step 6: Produce the Audit Report

Use the output format below.

---

## Output Format

```markdown
# iOS UI Audit: [App Name]

**Audit Date:** [Date]
**Scope:** [Single screen / Flow / Full app]
**iOS Target:** [Version]
**Interface Type:** [Native SwiftUI / UIKit / Cross-platform / Web wrapper]

---

## First-Impression Assessment

**3-Second Verdict:** [One sentence — what a user feels in the first 3 seconds]
**Overall Tier:** [Amateur / Functional / Premium]
**Composite Score:** [Average of five dimension scores] / 10

---

## Dimension Scores

| Dimension | Score | Tier | Key Finding |
|---|---|---|---|
| Visual Quality & Consistency | X/10 | [Tier] | [One-line summary] |
| Native iOS Fit | X/10 | [Tier] | [One-line summary] |
| Interaction & Motion | X/10 | [Tier] | [One-line summary] |
| Clarity & Usability | X/10 | [Tier] | [One-line summary] |
| Accessibility & Edge Cases | X/10 | [Tier] | [One-line summary] |

---

## Detailed Findings

### [Dimension Name] — [Score]/10

**Observations:**
- [Specific, concrete observation with location]
- [Specific, concrete observation with location]

**What's working:**
- [Specific positive finding]

**What needs work:**
- [Specific issue] → [Specific fix recommendation]

[Repeat for each dimension]

---

## Anti-Patterns Detected

| # | Anti-Pattern Name | Severity | Location | Recommended Fix |
|---|---|---|---|---|
| 1 | [Name] | [🔴/🟡/🟠] | [Where it appears] | [What to do instead] |

---

## Prioritized Action Plan

### P0 — Critical (Fix Before Release)
1. [Finding] → [Action]

### P1 — Major (Fix This Sprint)
1. [Finding] → [Action]

### P2 — Polish (Quality Milestone)
1. [Finding] → [Action]

---

## Premium Opportunity Highlights

[2–3 specific opportunities to elevate the app from functional to premium, drawn from the
dimension criteria. These are not bugs — they are the things that separate a 7/10 app from
a 9/10 app.]
```

---

## Guardrails

### Audit Quality Guardrails

- [ ] Every finding cites a specific, observable issue — no vague "could be improved" statements
- [ ] Every negative finding includes a specific, actionable fix recommendation
- [ ] Scores are justified by concrete observations, not gut feeling
- [ ] At least one positive finding is noted per dimension (even if the overall score is low)
- [ ] Anti-patterns are identified by their specific name, not described generically
- [ ] The audit distinguishes between structural failures (P0) and polish opportunities (P2)
- [ ] The audit does not recommend Android or web patterns as fixes
- [ ] All fix recommendations are iOS-native solutions

### Objectivity Guardrails

- [ ] The audit does not criticize valid design choices that differ from personal preference
- [ ] The audit acknowledges known constraints (cross-platform, legacy, etc.) when relevant
- [ ] The audit does not penalize intentional deviations from HIG that are well-executed and justified
- [ ] The audit uses the same scoring standards regardless of the app's brand or budget

### Completeness Guardrails

- [ ] All five dimensions are scored — no dimension is skipped
- [ ] The anti-pattern scan covers all four categories (navigation, interaction, visual, content)
- [ ] The action plan has items in at least two priority tiers
- [ ] The first-impression assessment is recorded before detailed analysis

---

## Quick-Reference: The "Good vs. Bad" Decision Table

Use this table for rapid evaluation of specific UI components when you don't need a full audit.

| Component | Premium (Native iOS) | Amateur (Generic) |
|---|---|---|
| **Navigation** | Bottom Tab Bar, 3–5 modes | Hamburger menu, top tabs |
| **Tab Bar items** | Navigational modes only | Actions mixed with navigation |
| **Back navigation** | Left-edge swipe + back button | Custom back button, broken swipe |
| **Corners** | Continuous curvature (squircles) | Standard CSS rounded rectangles |
| **Shadows** | Soft, multi-layered, tinted | Harsh, dark, single-layer |
| **Depth** | Translucency, blur, Liquid Glass | Opaque flat backgrounds |
| **Colors** | Semantic tokens, Dark Mode support | Hard-coded hex values |
| **Accent color** | Interactive elements only | Decorative text, backgrounds |
| **Typography** | SF Pro, defined type scale, Dynamic Type | Generic fonts, static sizes |
| **Animations** | Spring-based, interruptible | Linear / ease-in-out, blocking |
| **Loading** | Skeleton screens, optimistic UI | Blocking spinner |
| **Success feedback** | Haptic + brief animation | Modal alert requiring "OK" tap |
| **Empty states** | Illustrated, contextual, actionable | Blank screen, "No Data" |
| **Modals** | Draggable bottom sheets | Full-screen opaque popups |
| **Forms** | Inline validation, auto keyboard type | Submit-only validation |
| **Icons** | Custom, uniform stroke/style | Mixed stock icon libraries |
| **Settings** | iOS grouped inset list style | Over-customized, confusing toggles |
| **Touch targets** | ≥ 44×44pt always | Small, cramped tap areas |
| **Contrast** | ≥ 4.5:1, supports Increase Contrast | Low contrast, fails in sunlight |
| **Onboarding** | Interactive, value-first, short | Login wall, 10+ tutorial screens |
