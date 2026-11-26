<?php
declare(strict_types=1);
namespace DiDom;
use RuntimeException;
/**
* @internal DiDom
*/
class Errors
{
protected static bool $internalErrors;
protected static bool $disableEntities;
/**
* @var \LibXMLError[]
*/
protected static array $lastErrors = [];
/**
* Disable error reporting.
*/
public static function disable(): void
{
self::$internalErrors = \libxml_use_internal_errors(true);
if (\LIBXML_VERSION < 20900) {
self::$disableEntities = \libxml_disable_entity_loader(true);
}
}
/**
* Restore error reporting.
*/
public static function restore(bool $clear = true): void
{
self::$lastErrors = \libxml_get_errors();
if ($clear) {
\libxml_clear_errors();
}
\libxml_use_internal_errors(self::$internalErrors);
if (\LIBXML_VERSION < 20900) {
\libxml_disable_entity_loader(self::$disableEntities);
}
}
/**
* Returns the last errors collected during document loading.
*
* @return \LibXMLError[]
*/
public static function getLastErrors(): array
{
return self::$lastErrors;
}
/**
* Returns a single formatted string containing all collected errors.
*
* @return string The formatted error message
*
* @throws RuntimeException if there are no errors to format
*/
public static function getFormattedErrors(): string
{
if (self::$lastErrors === []) {
throw new RuntimeException('No errors to format.');
}
$levelNames = [
\LibXML_ERR_WARNING => 'Warning',
\LibXML_ERR_ERROR => 'Error',
\LibXML_ERR_FATAL => 'Fatal',
];
$messages = [];
foreach (self::$lastErrors as $error) {
$level = $levelNames[$error->level] ?? 'Unknown';
$messages[] = \sprintf(
'[%s] Line %d, Column %d: %s',
$level,
$error->line,
$error->column,
\trim($error->message)
);
}
return \implode("\n", $messages);
}
}