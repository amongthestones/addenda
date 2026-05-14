# Addenda

A small WordPress plugin that brings four iA Writer authoring conventions to the front end: `==highlights==`, `{{TOC}}`, H2–H3 anchors, and `[^inline footnotes]`.

---

## Features

### Highlights

Wrap text in `==double equals==` to render it as `<mark>`.

```
This is ==highlighted== text.
```

Works inside paragraphs, list items, blockquotes, and headings. Skipped inside `<code>` and `<pre>` blocks.

Also runs on excerpts and text widgets via the `the_excerpt` and `widget_text` filters.

---

### Table of Contents

Place `{{TOC}}` on its own line anywhere in a post to insert a linked, nested `<nav>` block built from all H2 and H3 headings in the content.

```
{{TOC}}

## Introduction

## Installation

### Requirements

## Usage
```

- Nesting mirrors heading level (H2 = top level, H3 = indented under its parent H2)
- Heading slugs are computed from heading text via `sanitize_title()`; duplicate headings get a `-2`, `-3` suffix
- Stripped from RSS feeds automatically
- Skipped when `{{TOC}}` appears inside a code span or block

---

### H2–H3 Anchors

Not specifically iA Writer but pairs with Table fo Contents: All H2 and H3 headings get an `id` attribute automatically so they can be linked to directly. No markup required.

To set a custom anchor instead of the auto-generated slug, add `{#your-id}` at the end of the heading in your editor:

```
## Installation {#install}
```

That heading renders as `<h2 id="install">Installation</h2>`. Headings that already have an `id` are left untouched.

---

### Inline Footnotes

Write footnote text directly in the flow of prose using `[^...]` — no separate reference block needed.

```
This claim needs a source.[^The source is a 2019 paper by Smith et al.]
```

Renders as a numbered superscript linked to a footnote list appended at the end of the post. Back-links (↩) are included on screen and omitted from RSS feeds.

Multiple footnotes in one post are numbered in document order.

---

## Installation

1. Copy `addenda.php` into `wp-content/plugins/addenda/`
2. Activate the plugin from the WordPress admin

No other configuration needed.

---

## Styling

The plugin outputs semantic HTML. Add CSS to your theme to style it.

| Selector | Element |
|---|---|
| `nav.adn-toc` | TOC wrapper |
| `.adn-toc-label` | "Contents" label above the list |
| `ol.adn-footnotes` | Footnote list at the bottom of the post |
| `hr.adn-footnotes-sep` | Rule above the footnote list |
| `.adn-fn-back` | Back-link (↩) in each footnote |

`<mark>` is a standard HTML element — style it with `mark { ... }`.

---

## Notes

- All four features are skipped inside `<code>` and `<pre>` blocks, so you can document the syntax itself without it expanding.
- The plugin hooks into `the_content` at priority 20, after most other content filters run.
- Only H2 and H3 headings are anchored and included in the TOC. H1, H4, and below are untouched.