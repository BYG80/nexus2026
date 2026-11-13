<?php
/** HTML de noticias: solo etiquetas editoriales y enlaces HTTP(S), sin atributos arbitrarios. */
function nexus_news_html(string $html): string
{
    if (!class_exists('DOMDocument')) {
        return e(strip_tags($html));
    }
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NONET);
        $allowed = ['strong', 'em', 'b', 'i', 'br', 'p', 'ul', 'ol', 'li'];
        $render = function (DOMNode $node) use (&$render, $allowed): string {
            if ($node instanceof DOMText) return e($node->nodeValue);
            if (!($node instanceof DOMElement)) return '';
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math'], true)) return '';
            $content = '';
            foreach ($node->childNodes as $child) $content .= $render($child);
            if ($tag === 'a') {
                $url = trim($node->getAttribute('href'));
                if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) return $content;
                return '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">' . $content . '</a>';
            }
            if (!in_array($tag, $allowed, true)) return $content;
            return $tag === 'br' ? '<br>' : '<' . $tag . '>' . $content . '</' . $tag . '>';
        };
        $output = '';
        foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $node) $output .= $render($node);
        return $output;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}
