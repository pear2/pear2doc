<?php
/**
* Encapsulates <programlisting> and <screen> tags CDATA sections if not done yet.
*
* @author Christian Weiske <cweiske@php.net>
*/
array_shift($argv);
$files = $argv;

foreach ($files as $file) {
    $doc = new DOMDocument();
    $doc->load($file);
    $prl = $doc->getElementsByTagName('programlisting');
    foreach ($prl as $node) {
        $cd = $doc->createCDATASection($node->nodeValue);
        $node->nodeValue = null;
        $node->appendChild($cd);
    }
    echo $doc->saveXML();
}

?>