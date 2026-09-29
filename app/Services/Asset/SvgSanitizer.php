<?php

namespace App\Services\Asset;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitises uploaded SVG before it is ever stored or rendered.
 *
 * Brief §12 and §32. This matters more than it looks: an SVG is an XML
 * document that browsers execute. Serving an untrusted one from your own
 * origin is equivalent to letting a contributor upload a JavaScript file
 * and running it on your domain — it can read cookies, call your API as
 * the logged-in user, and deface any page that embeds it.
 *
 * The approach is a strict ALLOWLIST, not a blocklist. Blocklists lose:
 * there are too many ways to spell an event handler, and new SVG/CSS
 * features keep adding vectors. Anything not explicitly permitted is
 * removed.
 */
class SvgSanitizer
{
    /** Elements permitted to survive sanitisation. */
    private const ALLOWED_ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern',
        'clipPath', 'mask', 'filter',
        'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite',
        'feConvolveMatrix', 'feDiffuseLighting', 'feDisplacementMap',
        'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB',
        'feFuncG', 'feFuncR', 'feGaussianBlur', 'feImage', 'feMerge',
        'feMergeNode', 'feMorphology', 'feOffset', 'fePointLight',
        'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence',
        'marker', 'switch', 'style',
    ];

    /**
     * Attributes permitted on any allowed element.
     *
     * Note what is absent: every on* handler, href/xlink:href (added back
     * only for internal fragment refs below), and anything that can load
     * a remote resource.
     */
    private const ALLOWED_ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'viewBox', 'xmlns', 'xmlns:xlink',
        'version', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
        'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'dx', 'dy',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width',
        'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-miterlimit',
        'opacity', 'color', 'display', 'visibility', 'overflow',
        'offset', 'stop-color', 'stop-opacity', 'gradientUnits',
        'gradientTransform', 'spreadMethod', 'patternUnits',
        'patternContentUnits', 'clip-path', 'clip-rule', 'mask', 'filter',
        'font-family', 'font-size', 'font-weight', 'font-style',
        'text-anchor', 'dominant-baseline', 'letter-spacing', 'word-spacing',
        'preserveAspectRatio', 'markerWidth', 'markerHeight', 'orient',
        'refX', 'refY', 'markerUnits', 'maskUnits', 'maskContentUnits',
        'filterUnits', 'primitiveUnits', 'result', 'in', 'in2', 'mode',
        'type', 'values', 'stdDeviation', 'operator', 'k1', 'k2', 'k3', 'k4',
        'baseFrequency', 'numOctaves', 'seed', 'scale', 'radius',
        'flood-color', 'flood-opacity', 'lighting-color', 'surfaceScale',
        'specularConstant', 'specularExponent', 'diffuseConstant',
        'azimuth', 'elevation', 'pointsAtX', 'pointsAtY', 'pointsAtZ',
        'limitingConeAngle', 'tableValues', 'slope', 'intercept',
        'amplitude', 'exponent', 'divisor', 'bias', 'targetX', 'targetY',
        'edgeMode', 'kernelUnitLength', 'preserveAlpha', 'order', 'kernelMatrix',
        'xChannelSelector', 'yChannelSelector', 'attributeName',
    ];

    /** @var list<string> Human-readable log of what was stripped. */
    private array $removals = [];

    /**
     * @return array{svg: string, removals: list<string>}
     */
    public function sanitize(string $svg): array
    {
        $this->removals = [];

        // Strip anything before the XML/SVG root — some payloads prepend
        // HTML or a BOM to confuse the parser into a different mode.
        if (($pos = stripos($svg, '<svg')) !== false && $pos > 0) {
            $prefix = substr($svg, 0, $pos);
            if (stripos($prefix, '<?xml') === false) {
                $svg = substr($svg, $pos);
                $this->removals[] = 'content before <svg> root';
            }
        }

        // DOCTYPE is the entry point for XXE and billion-laughs entity
        // expansion. There is no legitimate reason for an icon to carry one.
        if (preg_match('/<!DOCTYPE/i', $svg)) {
            $svg = preg_replace('/<!DOCTYPE[^>]*(\[[^\]]*\])?[^>]*>/is', '', $svg);
            $this->removals[] = 'DOCTYPE declaration';
        }

        if (preg_match('/<!ENTITY/i', $svg)) {
            $svg = preg_replace('/<!ENTITY[^>]*>/is', '', $svg);
            $this->removals[] = 'ENTITY declaration';
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;

        // libxml_use_internal_errors: malformed SVG is expected input, not
        // an exceptional case — we do not want warnings leaking to output.
        $previous = libxml_use_internal_errors(true);

        // LIBXML_NONET blocks network fetches during parse. NOENT is
        // deliberately NOT passed — expanding entities is the attack.
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $document->documentElement) {
            return ['svg' => '', 'removals' => ['file is not parseable XML']];
        }

        if (strtolower($document->documentElement->nodeName) !== 'svg') {
            return ['svg' => '', 'removals' => ['root element is not <svg>']];
        }

        $this->clean($document->documentElement);

        $output = $document->saveXML($document->documentElement);

        return [
            'svg'      => $output ?: '',
            'removals' => array_values(array_unique($this->removals)),
        ];
    }

    /** True when the file survived sanitisation with nothing dangerous found. */
    public function isClean(string $svg): bool
    {
        return $this->sanitize($svg)['removals'] === [];
    }

    private function clean(DOMNode $node): void
    {
        // Iterate over a snapshot: removing children while traversing a
        // live DOMNodeList silently skips siblings.
        foreach (iterator_to_array($node->childNodes ?? []) as $child) {
            if ($child instanceof DOMElement) {
                $name = $child->nodeName;

                if (! in_array($name, self::ALLOWED_ELEMENTS, true)) {
                    $this->removals[] = "<{$name}> element";
                    $child->parentNode?->removeChild($child);
                    continue;
                }

                // <style> can carry @import and url() fetches; keep the
                // element only if its text body is inert.
                if ($name === 'style') {
                    $this->cleanStyleElement($child);
                    continue;
                }

                $this->cleanAttributes($child);
                $this->clean($child);
                continue;
            }

            // Processing instructions can smuggle stylesheet references.
            if ($child->nodeType === XML_PI_NODE) {
                $this->removals[] = 'processing instruction';
                $child->parentNode?->removeChild($child);
            }
        }
    }

    private function cleanAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            /** @var DOMAttr $attribute */
            $name  = $attribute->nodeName;
            $lower = strtolower($name);
            $value = $attribute->nodeValue ?? '';

            // Every event handler in one rule. This is the single most
            // important line in the class.
            if (str_starts_with($lower, 'on')) {
                $this->removals[] = "{$name} event handler";
                $element->removeAttributeNode($attribute);
                continue;
            }

            // href / xlink:href are allowed ONLY as internal fragment
            // references (e.g. <use href="#icon">). Anything else can
            // pull in a remote or javascript: payload.
            if ($lower === 'href' || $lower === 'xlink:href') {
                if (! str_starts_with(trim($value), '#')) {
                    $this->removals[] = "external {$name} reference";
                    $element->removeAttributeNode($attribute);
                }
                continue;
            }

            if (! in_array($name, self::ALLOWED_ATTRIBUTES, true)) {
                $this->removals[] = "{$name} attribute";
                $element->removeAttributeNode($attribute);
                continue;
            }

            if ($this->valueIsDangerous($value)) {
                $this->removals[] = "unsafe value in {$name}";
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private function cleanStyleElement(DOMElement $element): void
    {
        $css = $element->textContent ?? '';

        if ($this->valueIsDangerous($css) || stripos($css, '@import') !== false) {
            $this->removals[] = '<style> block with unsafe rules';
            $element->parentNode?->removeChild($element);
        }
    }

    /**
     * Catches javascript:/data: URIs, CSS url() fetches and legacy IE
     * expression(). Whitespace and control characters are stripped first
     * because "java\nscript:" and "java&#9;script:" both still execute.
     */
    private function valueIsDangerous(string $value): bool
    {
        $normalised = strtolower(preg_replace('/[\s\x00-\x1F\x7F]+/', '', $value) ?? '');

        foreach (['javascript:', 'vbscript:', 'data:text/html', 'expression(', 'url(', '&#'] as $needle) {
            if (str_contains($normalised, $needle)) {
                return true;
            }
        }

        return false;
    }
}
