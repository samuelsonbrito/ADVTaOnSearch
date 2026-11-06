<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitizador por lista de permissões para o inteiro teor das comunicações,
 * que é exibido com v-html. Mantém só tags de formatação e remove todos os
 * atributos (inclusive estilos, eventos e links).
 */
final class HtmlSanitizer
{
    private const PERMITIDAS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'sub', 'sup',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'div', 'span', 'hr',
    ];

    /** Removidas com todo o conteúdo. */
    private const DESCARTAR = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'head', 'title', 'svg', 'math', 'form'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__raiz">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $raiz = $doc->getElementById('__raiz');

        if (! $raiz instanceof DOMElement) {
            return e(strip_tags($html));
        }

        $this->limpar($raiz);

        $saida = '';
        foreach (iterator_to_array($raiz->childNodes) as $filho) {
            $saida .= $doc->saveHTML($filho);
        }

        return trim($saida);
    }

    private function limpar(DOMNode $no): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho->nodeType === XML_COMMENT_NODE || $filho->nodeType === XML_PI_NODE) {
                $no->removeChild($filho);

                continue;
            }

            if (! $filho instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($filho->tagName);

            if (in_array($tag, self::DESCARTAR, true)) {
                $no->removeChild($filho);

                continue;
            }

            $this->limpar($filho);

            if (! in_array($tag, self::PERMITIDAS, true)) {
                // Desembrulha: mantém o conteúdo, descarta a tag.
                while ($filho->firstChild) {
                    $no->insertBefore($filho->firstChild, $filho);
                }
                $no->removeChild($filho);

                continue;
            }

            foreach (iterator_to_array($filho->attributes) as $atributo) {
                $filho->removeAttribute($atributo->nodeName);
            }
        }
    }
}
