<?php

declare(strict_types=1);

namespace DiDom\Tests;

use DiDom\Errors;
use RuntimeException;

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
    // =========================
    // getFormattedErrors tests
    // =========================
    public function testGetFormattedErrorsThrowsExceptionWhenNoErrors()
    {
        Errors::disable();
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0"?><root/>');
        Errors::restore(true);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No errors to format.');
        Errors::getFormattedErrors();
    }
    public function testGetFormattedErrorsReturnsString()
    {
        Errors::disable();
        $dom = new \DOMDocument();
        $dom->loadXML('this is not valid xml <<<<>>>');
        Errors::restore(true);
        $result = Errors::getFormattedErrors();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
    public function testGetFormattedErrorsContainsErrorDetails()
    {
        Errors::disable();
        $dom = new \DOMDocument();
        $dom->loadXML('<root><unclosed></root>');
        Errors::restore(true);
        $result = Errors::getFormattedErrors();
        // Should contain level label
        $this->assertRegExp('/\[(Warning|Error|Fatal)\]/', $result);
        // Should contain line info
        $this->assertRegExp('/Line \d+/', $result);
        // Should contain column info
        $this->assertRegExp('/Column \d+/', $result);
    }
    public function testGetFormattedErrorsWithMultipleErrors()
    {
        Errors::disable();
        $dom = new \DOMDocument();
        $dom->loadXML('<root><unclosed><another></root>');
        Errors::restore(true);
        $errors = Errors::getLastErrors();
        $this->assertGreaterThan(0, \count($errors));
        $result = Errors::getFormattedErrors();
        $this->assertIsString($result);
        // If multiple errors, result should contain newlines separating them
        if (\count($errors) > 1) {
            $this->assertStringContainsString("\n", $result);
            $lines = \explode("\n", $result);
            $this->assertCount(\count($errors), $lines);
        }
    }
    public function testGetFormattedErrorsContainsOriginalMessage()
    {
        Errors::disable();
        $dom = new \DOMDocument();
        $dom->loadXML('<root><unclosed></root>');
        Errors::restore(true);
        $errors = Errors::getLastErrors();
        $this->assertGreaterThan(0, \count($errors));
        $result = Errors::getFormattedErrors();
        // The formatted message should contain the original error message text
        $firstErrorMessage = \trim($errors[0]->message);
        $this->assertStringContainsString($firstErrorMessage, $result);
    }
    public function testGetFormattedErrorsAfterCleanLoadThenFailedLoad()
    {
        // First: clean load
        Errors::disable();
        $dom = new \DOMDocument();
        $dom->loadXML('<?xml version="1.0"?><root/>');
        Errors::restore(true);
        // Should throw since no errors
        try {
            Errors::getFormattedErrors();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertEquals('No errors to format.', $e->getMessage());
        }
        // Second: failed load
        Errors::disable();
        $dom2 = new \DOMDocument();
        $dom2->loadXML('broken xml <<<');
        Errors::restore(true);
        // Should not throw now
        $result = Errors::getFormattedErrors();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}
