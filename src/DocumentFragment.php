<?php

declare(strict_types=1);

namespace DiDom;

/**
 * @property string $tag
 */
class DocumentFragment extends Node
{
    /**
     */
    public function __construct(\DOMDocumentFragment $documentFragment)
    {
        $this->setNode($documentFragment);
    }

    /**
     * Append raw XML data.
     */
    public function appendXml(string $data): void
    {
        $this->node->appendXML($data);
    }
}