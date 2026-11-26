<?php

declare(strict_types=1);

namespace DiDom\Tests;

use DiDom\Errors;

class ErrorsTest extends TestCase
{
    public function testGetLastErrorsReturnsEmptyArrayInitially()
    {
        // Reset errors by disabling and restoring without any libxml errors
        Errors::disable();
        Errors::restore(true);

        $errors = Errors::getLastErrors();

        $this->assertIsArray($errors);
    }

    public function testGetLastErrorsAfterSuccessfulLoad()
    {
        Errors::disable();

        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0"?><root><item>Test</item></root>');

        Errors::restore(true);

        $errors = Errors::getLastErrors();

        $this->assertIsArray($errors);
        $this->assertCount(0, $errors);
    }

    public function testGetLastErrorsAfterFailedXmlLoad()
    {
        Errors::disable();

        $dom = new \DOMDocument();
        $dom->loadXML('this is not valid xml <<<<>>>');

        Errors::restore(true);

        $errors = Errors::getLastErrors();

        $this->assertIsArray($errors);
        $this->assertGreaterThan(0, \count($errors));

        foreach ($errors as $error) {
            $this->assertInstanceOf(\LibXMLError::class, $error);
        }
    }

    public function testGetLastErrorsContainsErrorDetails()
    {
        Errors::disable();

        $dom = new \DOMDocument();
        $dom->loadXML('<root><unclosed></root>');

        Errors::restore(true);

        $errors = Errors::getLastErrors();

        $this->assertGreaterThan(0, \count($errors));

        $firstError = $errors[0];
        $this->assertObjectHasAttribute('level', $firstError);
        $this->assertObjectHasAttribute('message', $firstError);
        $this->assertObjectHasAttribute('line', $firstError);
        $this->assertObjectHasAttribute('column', $firstError);
    }

    public function testErrorsAreClearedAfterRestore()
    {
        Errors::disable();

        $dom = new \DOMDocument();
        $dom->loadXML('invalid xml <<<<');

        Errors::restore(true);

        $errors = Errors::getLastErrors();
        $this->assertGreaterThan(0, \count($errors));

        // Now do a clean restore - errors should be from this clean operation
        Errors::disable();

        $dom2 = new \DOMDocument();
        $dom2->loadXML('<?xml version="1.0"?><root/>');

        Errors::restore(true);

        $errors2 = Errors::getLastErrors();
        $this->assertCount(0, $errors2);
    }
}