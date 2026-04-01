<?php

namespace App\Services;

class DocsHtmlConverter
{
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'hr',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'a', 'img',
        'blockquote', 'code', 'pre',
        'strong', 'em', 'b', 'i', 'u',
        'figure', 'figcaption',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
    ];

    public function convert(string $docsHtml): ConversionResult
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $docsHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return new ConversionResult('', '', []);
        }

        $title = $this->extractTitle($body);
        $images = [];
        $this->walkNode($body, $doc, $images);
        $this->removeEmptyParagraphs($body);

        $html = '';
        foreach ($body->childNodes as $child) {
            $html .= $doc->saveHTML($child);
        }

        $html = $this->cleanupHtml($html);

        return new ConversionResult($title, $html, $images);
    }

    private function extractTitle(\DOMElement $body): string
    {
        $h1 = $body->getElementsByTagName('h1')->item(0);
        if ($h1) {
            $title = trim($h1->textContent);
            $h1->parentNode->removeChild($h1);
            return $title;
        }
        return '';
    }

    private function walkNode(\DOMNode $node, \DOMDocument $doc, array &$images): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $this->processElement($child, $doc, $images);
            }
        }
    }

    private function processElement(\DOMElement $el, \DOMDocument $doc, array &$images): void
    {
        $tag = strtolower($el->tagName);

        // Convert styled spans to semantic elements
        if ($tag === 'span') {
            $style = $el->getAttribute('style');
            $newTag = $this->spanToSemantic($style);
            if ($newTag) {
                $new = $doc->createElement($newTag);
                while ($el->firstChild) {
                    $new->appendChild($el->firstChild);
                }
                $el->parentNode->replaceChild($new, $el);
                $this->walkNode($new, $doc, $images);
                return;
            }
            // Unwrap plain spans
            $this->unwrapNode($el);
            return;
        }

        // Handle images - extract and replace with placeholder
        if ($tag === 'img') {
            $src = $el->getAttribute('src');
            if ($src) {
                $idx = count($images);
                $images[] = [
                    'url' => $src,
                    'alt' => $el->getAttribute('alt') ?: '',
                    'position' => $idx,
                ];
                $placeholder = $doc->createComment(" sakusaku-image:{$idx} ");
                $el->parentNode->replaceChild($placeholder, $el);
            }
            return;
        }

        // Remove disallowed tags but keep children
        if (!in_array($tag, self::ALLOWED_TAGS)) {
            $this->unwrapNode($el);
            return;
        }

        // Strip disallowed attributes
        $this->stripAttributes($el, $tag);

        // Recurse into children
        $this->walkNode($el, $doc, $images);
    }

    private function spanToSemantic(string $style): ?string
    {
        if (str_contains($style, 'font-weight') && preg_match('/font-weight\s*:\s*(bold|[7-9]\d{2})/i', $style)) {
            return 'strong';
        }
        if (str_contains($style, 'font-style') && str_contains($style, 'italic')) {
            return 'em';
        }
        if (str_contains($style, 'text-decoration') && str_contains($style, 'underline')) {
            return 'u';
        }
        return null;
    }

    private function unwrapNode(\DOMElement $el): void
    {
        $parent = $el->parentNode;
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    private function stripAttributes(\DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        $toRemove = [];

        foreach ($el->attributes as $attr) {
            if (!in_array($attr->name, $allowed)) {
                $toRemove[] = $attr->name;
            }
        }

        foreach ($toRemove as $name) {
            $el->removeAttribute($name);
        }
    }

    private function removeEmptyParagraphs(\DOMElement $body): void
    {
        $paragraphs = $body->getElementsByTagName('p');
        $toRemove = [];

        for ($i = 0; $i < $paragraphs->length; $i++) {
            $p = $paragraphs->item($i);
            if (trim($p->textContent) === '' && $p->childNodes->length === 0) {
                $toRemove[] = $p;
            }
        }

        foreach ($toRemove as $p) {
            $p->parentNode->removeChild($p);
        }
    }

    private function cleanupHtml(string $html): string
    {
        // Remove consecutive <br> tags
        $html = preg_replace('/(<br\s*\/?>){3,}/i', '<br><br>', $html);
        // Trim whitespace
        $html = trim($html);
        return $html;
    }
}

class ConversionResult
{
    public function __construct(
        public readonly string $title,
        public readonly string $cleanHtml,
        public readonly array $images,
    ) {}
}
