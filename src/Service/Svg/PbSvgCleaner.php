<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Svg;

use Drupal\Component\Utility\Html;

/**
 * Cleans the markup of an SVG file before it is printed inline.
 *
 * The files come from a theme's pb-svg-icons folder, so they are developer
 * files, not uploads. This is the safety net under that trust: it keeps every
 * drawing element (gradients, clip paths, masks, patterns, text, style) and
 * removes what can run code or load something from elsewhere.
 */
final class PbSvgCleaner {

  /**
   * Part of the cache key of cleaned files: raise it when the rules change.
   */
  public const int VERSION = 2;

  /**
   * The only elements kept (lower case); anything else goes with its content.
   *
   * An allow list, because a browser parses inline SVG differently from the
   * parser used here: a deny list would let through whatever it did not name.
   */
  private const array ALLOWED_ELEMENTS = [
    'svg', 'g', 'defs', 'symbol', 'use', 'switch', 'view', 'a', 'title', 'desc', 'style',
    'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
    'text', 'tspan', 'textpath',
    'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'marker',
    'animate', 'set', 'animatemotion', 'animatetransform', 'mpath',
    'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite',
    'feconvolvematrix', 'fediffuselighting', 'fedisplacementmap', 'fedistantlight',
    'fedropshadow', 'feflood', 'fefunca', 'fefuncb', 'fefuncg', 'fefuncr',
    'fegaussianblur', 'femerge', 'femergenode', 'femorphology', 'feoffset',
    'fepointlight', 'fespecularlighting', 'fespotlight', 'fetile', 'feturbulence',
  ];

  /**
   * Regex: CSS that can load from elsewhere (value already lower case).
   *
   * A url() that is not a reference inside the drawing, the functions that take
   * a bare address, and any CSS escape, which could spell one of them.
   */
  private const string CSS_LOADER = 'url\\((?![\'"]?#)|@import|image-set\\(|image\\(|cross-fade\\(|element\\(|src\\(|\\\\';

  /**
   * Animation elements, removed when they target a link attribute.
   */
  private const array ANIMATION_ELEMENTS = ['animate', 'set', 'animatemotion', 'animatetransform'];

  /**
   * Returns the cleaned `<svg>` markup, or NULL when there is no svg element.
   */
  public function clean(string $markup): ?string {
    // Keep only <svg> ... </svg>: an exported file's XML prolog, comments and
    // DOCTYPE before it would otherwise print as text.
    $start = stripos($markup, '<svg');
    $end = strripos($markup, '</svg>');
    if ($start === FALSE || $end === FALSE || $end < $start) {
      return NULL;
    }
    $markup = substr($markup, $start, $end - $start + strlen('</svg>'));

    $dom = Html::load($markup);
    $svg = $dom->getElementsByTagName('svg')->item(0);
    if (!$svg instanceof \DOMElement) {
      return NULL;
    }

    $this->cleanElement($svg);
    $out = $dom->saveHTML($svg);
    return $out === FALSE || $out === '' ? NULL : $out;
  }

  /**
   * Cleans one element and its descendants in place.
   */
  private function cleanElement(\DOMElement $element): void {
    $this->cleanAttributes($element);
    // Copy the list first: removing nodes while iterating a live list skips
    // the next sibling.
    $children = [];
    foreach ($element->childNodes as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      // Only elements and plain text stay. Every other node type (comment,
      // processing instruction, CDATA) prints unescaped, and a browser ends it
      // at a different place than this parser, so what follows would be live
      // markup. CDATA inside <style> is handled with the style element below.
      if ($child instanceof \DOMCdataSection || !($child instanceof \DOMElement || $child instanceof \DOMText)) {
        $element->removeChild($child);
        continue;
      }
      if (!$child instanceof \DOMElement) {
        continue;
      }
      $name = strtolower($child->localName ?? '');
      if (!in_array($name, self::ALLOWED_ELEMENTS, TRUE) || $this->isLinkAnimation($child, $name) || $this->isUnsafeStyle($child, $name)) {
        $element->removeChild($child);
        continue;
      }
      if ($name === 'style') {
        // Print only the CSS: the CDATA wrapper exporters add is not needed.
        $child->textContent = $this->styleText($child);
        continue;
      }
      $this->cleanElement($child);
    }
  }

  /**
   * Removes event handlers and links that leave the drawing.
   */
  private function cleanAttributes(\DOMElement $element): void {
    $remove = [];
    foreach ($element->attributes ?? [] as $attribute) {
      if (!$attribute instanceof \DOMAttr) {
        continue;
      }
      $name = strtolower($attribute->nodeName);
      $local = strtolower($attribute->localName ?? $name);
      $value = $this->normalizedValue($attribute->value);
      if (str_starts_with($local, 'on')) {
        $remove[] = $attribute;
      }
      // A link may only point inside the drawing (#gradient-1). External
      // files, javascript:, data: and vbscript: links are dropped.
      elseif ($local === 'href' && !str_starts_with($value, '#')) {
        $remove[] = $attribute;
      }
      elseif (in_array($local, ['src', 'ping'], TRUE) || preg_match('/(javascript|vbscript|data):|expression\(|' . self::CSS_LOADER . '/', $value)) {
        $remove[] = $attribute;
      }
    }
    foreach ($remove as $attribute) {
      $element->removeAttributeNode($attribute);
    }
  }

  /**
   * TRUE for an animation that rewrites a link, a handler or a style.
   */
  private function isLinkAnimation(\DOMElement $element, string $name): bool {
    if (!in_array($name, self::ANIMATION_ELEMENTS, TRUE)) {
      return FALSE;
    }
    $target = $this->normalizedValue($element->getAttribute('attributeName'));
    return $target === 'href' || $target === 'style' || str_ends_with($target, ':href') || str_starts_with($target, 'on');
  }

  /**
   * TRUE for a <style> block that loads, runs or could break out into markup.
   *
   * The block prints unescaped and SVG style is not raw text in a browser, so
   * a "<" in it would be parsed as an element.
   */
  private function isUnsafeStyle(\DOMElement $element, string $name): bool {
    if ($name !== 'style') {
      return FALSE;
    }
    return (bool) preg_match('/<|\/\/|(javascript|vbscript|data):|expression\(|' . self::CSS_LOADER . '/', $this->normalizedValue($this->styleText($element)));
  }

  /**
   * The CSS of a <style> element, without a CDATA wrapper.
   */
  private function styleText(\DOMElement $element): string {
    return str_replace(['<![CDATA[', ']]>'], '', $element->textContent);
  }

  /**
   * Lower-cases a value and removes the spaces and controls used to hide words.
   */
  private function normalizedValue(string $value): string {
    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', $decoded));
  }

}
