<?php

declare(strict_types=1);

namespace DiDom;

use DiDom\Exceptions\InvalidSelectorException;

class Query
{
    /**
     * Types of expression.
     *
     * @const string
     */
    const TYPE_XPATH = 'XPATH';
    const TYPE_CSS = 'CSS';

    /**
     * @var array
     */
    protected static array $compiled = [];

    /**
     * Converts a CSS selector into an XPath expression.
     *
     * @param string $expression XPath expression or CSS selector
     * @param string $type The type of the expression
     *
     * @return string XPath expression
     *
     * @throws InvalidSelectorException if the expression is empty
     */
    public static function compile(string $expression, string $type = self::TYPE_CSS): string
    {
        if (0 !== \strcasecmp($type, self::TYPE_XPATH) && 0 !== \strcasecmp($type, self::TYPE_CSS)) {
            throw new \RuntimeException(\sprintf('Unknown expression type "%s".', $type));
        }

        $expression = \trim($expression);

        if ('' === $expression) {
            throw new InvalidSelectorException('The expression must not be empty.');
        }

        if (0 === \strcasecmp($type, self::TYPE_XPATH)) {
            return $expression;
        }

        if (false === \array_key_exists($expression, static::$compiled)) {
            static::$compiled[$expression] = static::cssToXpath($expression);
        }

        return static::$compiled[$expression];
    }

    /**
     * Converts a CSS selector into an XPath expression.
     *
     * @param string $selector A CSS selector
     * @param string $prefix Specifies the nesting of nodes
     *
     * @return string XPath expression
     *
     * @throws InvalidSelectorException
     */
    public static function cssToXpath(string $selector, string $prefix = '//'): string
    {
        $paths = [];

        while ('' !== $selector) {
            list($xpath, $selector) = static::parseAndConvertSelector($selector, $prefix);

            if (',' === \substr($selector, 0, 1)) {
                $selector = \trim($selector, ', ');
            }

            $paths[] = $xpath;
        }

        return \implode('|', $paths);
    }

    /**
     * @param string $selector
     * @param string $prefix
     *
     * @return array
     *
     * @throws InvalidSelectorException
     */
    protected static function parseAndConvertSelector(string $selector, string $prefix = '//'): array
    {
        if ('>' === \substr($selector, 0, 1)) {
            $prefix = '/';

            $selector = \ltrim($selector, '> ');
        }

        $segments = self::getSegments($selector);
        $xpath = '';

        while ([] !== $segments) {
            $xpath .= self::buildXpath($segments, $prefix);

            $selector = \trim(\substr($selector, \strlen($segments['selector'])));
            $prefix = true === isset($segments['rel']) ? '/' : '//';

            if ('' === $selector || '::' === \substr($selector, 0, 2) || ',' === \substr($selector, 0, 1)) {
                break;
            }

            $segments = self::getSegments($selector);
        }

        // if selector has property
        if ('::' === \substr($selector, 0, 2)) {
            $property = self::parseProperty($selector);
            $propertyXpath = self::convertProperty($property['name'], $property['args']);

            $selector = \substr($selector, \strlen($property['property']));
            $selector = \trim($selector);

            $xpath .= '/' . $propertyXpath;
        }

        return [$xpath, $selector];
    }

    /**
     * @param string $selector
     *
     * @return array
     *
     * @throws InvalidSelectorException
     */
    protected static function parseProperty(string $selector): array
    {
        $name = '(?P<name>[\w\-]+)';
        $args = '(?:\((?P<args>[^\)]+)?\))?';

        $regexp = '/^::' . $name . $args . '/is';

        if (1 !== \preg_match($regexp, $selector, $matches)) {
            throw new InvalidSelectorException(\sprintf('Invalid property "%s".', $selector));
        }

        $result = [];

        $result['property'] = $matches[0];
        $result['name'] = $matches['name'];
        $result['args'] = true === isset($matches['args']) ? \explode(',', $matches['args']) : [];

        $result['args'] = \array_map('trim', $result['args']);

        return $result;
    }

    /**
     * @param string $name
     * @param array $parameters
     *
     * @return string
     *
     * @throws InvalidSelectorException if the specified property is unknown
     */
    protected static function convertProperty(string $name, array $parameters = []): string
    {
        if ('text' === $name) {
            return 'text()';
        }

        if ('attr' === $name) {
            if ([] === $parameters) {
                return '@*';
            }

            $attributes = [];

            foreach ($parameters as $attribute) {
                $attributes[] = \sprintf('name() = "%s"', $attribute);
            }

            return \sprintf('@*[%s]', \implode(' or ', $attributes));
        }

        throw new InvalidSelectorException(\sprintf('Unknown property "%s".', $name));
    }

    /**
     * Converts a CSS pseudo-class into an XPath expression.
     *
     * @param string $pseudo Pseudo-class
     * @param string $tagName
     * @param array $parameters
     *
     * @return string
     *
     * @throws InvalidSelectorException if the specified pseudo-class is unknown
     */
    protected static function convertPseudo(string $pseudo, string &$tagName, array $parameters = []): string
    {
        switch ($pseudo) {
            case 'first-child':
                return 'position() = 1';
            case 'last-child':
                return 'position() = last()';
            case 'nth-child':
                $xpath = \sprintf('(name()="%s") and (%s)', $tagName, self::convertNthExpression($parameters[0]));
                $tagName = '*';

                return $xpath;
            case 'contains':
                $string = \trim($parameters[0], '\'"');

                if (1 === \count($parameters)) {
                    return self::convertContains($string);
                }

                if ('true' !== $parameters[1] && 'false' !== $parameters[1]) {
                    throw new InvalidSelectorException(\sprintf('Parameter 2 of "contains" pseudo-class must be equal true or false, "%s" given.', $parameters[1]));
                }

                $caseSensitive = 'true' === $parameters[1];

                if (2 === \count($parameters)) {
                    return self::convertContains($string, $caseSensitive);
                }

                if ('true' !== $parameters[2] && 'false' !== $parameters[2]) {
                    throw new InvalidSelectorException(\sprintf('Parameter 3 of "contains" pseudo-class must be equal true or false, "%s" given.', $parameters[2]));
                }

                $fullMatch = 'true' === $parameters[2];

                return self::convertContains($string, $caseSensitive, $fullMatch);
            case 'has':
                return self::cssToXpath($parameters[0], './/');
            case 'not':
                return \sprintf('not(self::%s)', self::cssToXpath($parameters[0], ''));

            case 'nth-of-type':
                return self::convertNthExpression($parameters[0]);
            case 'empty':
                return 'count(descendant::*) = 0';
            case 'not-empty':
                return 'count(descendant::*) > 0';
        }

        throw new InvalidSelectorException(\sprintf('Unknown pseudo-class "%s".', $pseudo));
    }

    /**
     * @param array $segments
     * @param string $prefix Specifies the nesting of nodes
     *
     * @return string XPath expression
     *
     * @throws \InvalidArgumentException if you neither specify tag name nor attributes
     */
    public static function buildXpath(array $segments, string $prefix = '//'): string
    {
        $tagName = isset($segments['tag']) ? $segments['tag'] : '*';

        $attributes = [];

        // if the id attribute specified
        if (true === isset($segments['id'])) {
            $attributes[] = \sprintf('@id="%s"', $segments['id']);
        }

        // if the class attribute specified
        if (true === isset($segments['classes'])) {
            foreach ($segments['classes'] as $class) {
                $attributes[] = \sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
            }
        }

        // if the attributes specified
        if (true === isset($segments['attributes'])) {
            foreach ($segments['attributes'] as $name => $value) {
                $attributes[] = self::convertAttribute($name, $value);
            }
        }

        // if the pseudo class specified
        if (true === \array_key_exists('pseudo', $segments)) {
            foreach ($segments['pseudo'] as $pseudo) {
                $expression = null !== $pseudo['expression'] ? $pseudo['expression'] : '';

                $parameters = \explode(',', $expression);
                $parameters = \array_map('trim', $parameters);

                $attributes[] = self::convertPseudo($pseudo['type'], $tagName, $parameters);
            }
        }

        if ([] === $attributes && false === isset($segments['tag'])) {
            throw new \InvalidArgumentException('The array of segments must contain the name of the tag or at least one attribute.');
        }

        $xpath = $prefix . $tagName;

        if (0 !== ($count = \count($attributes))) {
            $xpath .= (1 < $count) ? \sprintf('[(%s)]', \implode(') and (', $attributes)) : \sprintf('[%s]', $attributes[0]);
        }

        return $xpath;
    }

    /**
     * @param string $name The name of an attribute
     * @param string|null $value The value of an attribute
     *
     * @return string
     */
    protected static function convertAttribute(string $name, ?string $value): string
    {
        $isSimpleSelector = false === \in_array(\substr($name, 0, 1), ['^', '!'], true);
        $isSimpleSelector = true === $isSimpleSelector && false === \in_array(\substr($name, -1), ['^', '$', '*', '!', '~'], true);

        if (true === $isSimpleSelector) {
            // if specified only the attribute name
            $xpath = null === $value ? '@' . $name : \sprintf('@%s="%s"', $name, $value);

            return $xpath;
        }

        // if the attribute name starts with ^
        // example: *[^data-]
        if ('^' === \substr($name, 0, 1)) {
            $xpath = \sprintf('@*[starts-with(name(), "%s")]', \substr($name, 1));

            return null === $value ? $xpath : \sprintf('%s="%s"', $xpath, $value);
        }

        // if the attribute name starts with !
        // example: input[!disabled]
        if ('!' === \substr($name, 0, 1)) {
            $xpath = \sprintf('not(@%s)', \substr($name, 1));

            return $xpath;
        }

        $symbol = \substr($name, -1);
        $name = \substr($name, 0, -1);

        switch ($symbol) {
            case '^':
                $xpath = \sprintf('starts-with(@%s, "%s")', $name, $value);

                break;
            case '$':
                $xpath = \sprintf('substring(@%s, string-length(@%s) - string-length("%s") + 1) = "%s"', $name, $name, $value, $value);

                break;
            case '*':
                $xpath = \sprintf('contains(@%s, "%s")', $name, $value);

                break;
            case '!':
                $xpath = \sprintf('not(@%s="%s")', $name, $value);

                break;
            case '~':
                $xpath = \sprintf('contains(concat(" ", normalize-space(@%s), " "), " %s ")', $name, $value);

                break;
        }

        return $xpath;
    }

    /**
     * Converts nth-expression into an XPath expression.
     *
     * @param string $expression nth-expression
     *
     * @return string
     *
     * @throws InvalidSelectorException if the given nth-child expression is empty or invalid
     */
    protected static function convertNthExpression(string $expression): string
    {
        if ('' === $expression) {
            throw new InvalidSelectorException('nth-child (or nth-last-child) expression must not be empty.');
        }

        if ('odd' === $expression) {
            return 'position() mod 2 = 1 and position() >= 1';
        }

        if ('even' === $expression) {
            return 'position() mod 2 = 0 and position() >= 0';
        }

        if (true === \is_numeric($expression)) {
            return \sprintf('position() = %d', $expression);
        }

        if (1 === \preg_match("/^(?P<mul>[0-9]?n)(?:(?P<sign>\+|\-)(?P<pos>[0-9]+))?$/is", $expression, $segments)) {
            if (true === isset($segments['mul'])) {
                $multiplier = 'n' === $segments['mul'] ? 1 : \trim($segments['mul'], 'n');
                $sign = (true === isset($segments['sign']) && '+' === $segments['sign']) ? '-' : '+';
                $position = true === isset($segments['pos']) ? $segments['pos'] : 0;

                return \sprintf('(position() %s %d) mod %d = 0 and position() >= %d', $sign, $position, $multiplier, $position);
            }
        }

        throw new InvalidSelectorException(\sprintf('Invalid nth-child expression "%s".', $expression));
    }

    /**
     * @param string $string
     * @param bool $caseSensitive
     * @param bool $fullMatch
     */
    protected static function convertContains(string $string, bool $caseSensitive = true, bool $fullMatch = false): string
    {
        if (true === $caseSensitive && true === $fullMatch) {
            return \sprintf('text() = "%s"', $string);
        }

        if (true === $caseSensitive && false === $fullMatch) {
            return \sprintf('contains(text(), "%s")', $string);
        }

        $strToLowerFunction = true === \function_exists('mb_strtolower') ? 'mb_strtolower' : 'strtolower';

        if (false === $caseSensitive && true === $fullMatch) {
            return \sprintf("php:functionString(\"{$strToLowerFunction}\", .) = php:functionString(\"{$strToLowerFunction}\", \"%s\")", $string);
        }

        // if false === $caseSensitive and false === $fullMatch
        return \sprintf("contains(php:functionString(\"{$strToLowerFunction}\", .), php:functionString(\"{$strToLowerFunction}\", \"%s\"))", $string);
    }

    /**
     * Splits the CSS selector into parts (tag name, ID, classes, attributes, pseudo-class).
     *
     * @param string $selector CSS selector
     *
     * @return array
     *
     * @throws InvalidSelectorException if the selector is empty or not valid
     */
    public static function getSegments(string $selector): array
    {
        $selector = \trim($selector);

        if ('' === $selector) {
            throw new InvalidSelectorException('The selector must not be empty.');
        }

        $pregMatchResult = \preg_match(self::getSelectorRegex(), $selector, $segments);

        if (false === $pregMatchResult || 0 === $pregMatchResult || '' === $segments[0]) {
            throw new InvalidSelectorException(\sprintf('Invalid selector "%s".', $selector));
        }

        $result = ['selector' => $segments[0]];

        if (true === isset($segments['tag']) && '' !== $segments['tag']) {
            $result['tag'] = $segments['tag'];
        }

        // if the id attribute specified
        if (true === isset($segments['id']) && '' !== $segments['id']) {
            $result['id'] = $segments['id'];
        }

        // if the attributes specified
        if (true === isset($segments['attrs'])) {
            $attributes = \trim($segments['attrs'], '[]');
            $attributes = \explode('][', $attributes);

            foreach ($attributes as $attribute) {
                if ('' !== $attribute) {
                    list($name, $value) = \array_pad(\explode('=', $attribute, 2), 2, null);

                    if ('' === $name) {
                        throw new InvalidSelectorException(\sprintf('Invalid selector "%s": attribute name must not be empty.', $selector));
                    }

                    // equal null if specified only the attribute name
                    $result['attributes'][$name] = true === \is_string($value) ? \trim($value, '\'"') : null;
                }
            }
        }

        // if the class attribute specified
        if (true === isset($segments['classes'])) {
            $classes = \trim($segments['classes'], '.');
            $classes = \explode('.', $classes);

            foreach ($classes as $class) {
                if ('' !== $class) {
                    $result['classes'][] = $class;
                }
            }
        }

        // if the pseudo class specified
        if (true === isset($segments['pseudo']) && '' !== $segments['pseudo']) {
            \preg_match_all('/:(?P<type>[\w\-]+)(?:\((?P<expr>[^\)]+)\))?/', $segments['pseudo'], $pseudoClasses);

            $result['pseudo'] = [];

            foreach ($pseudoClasses['type'] as $index => $pseudoType) {
                $result['pseudo'][] = [
                    'type' => $pseudoType,
                    'expression' => '' !== $pseudoClasses['expr'][$index] ? $pseudoClasses['expr'][$index] : null,
                ];
            }
        }

        // if it is a direct descendant
        if (true === isset($segments['rel'])) {
            $result['rel'] = $segments['rel'];
        }

        return $result;
    }

    private static function getSelectorRegex(): string
    {
        $tag = '(?P<tag>[\*|\w|\-]+)?';
        $id = '(?:#(?P<id>[\w|\-]+))?';
        $classes = '(?P<classes>\.[\w|\-|\.]+)*';
        $attrs = '(?P<attrs>(?:\[.+?\])*)?';
        $pseudoType = '[\w\-]+';
        $pseudoExpr = '(?:\([^\)]+\))?';
        $pseudo = '(?P<pseudo>(?::' . $pseudoType  . $pseudoExpr . ')+)?';
        $rel = '\s*(?P<rel>>)?';

        return '/' . $tag . $id . $classes . $attrs . $pseudo . $rel . '/is';
    }

    /**
     * @return array
     */
    public static function getCompiled(): array
    {
        return static::$compiled;
    }

    /**
     * @param array $compiled
     *
     * @throws \InvalidArgumentException if the attributes is not an array
     */
    public static function setCompiled(array $compiled): void
    {
        static::$compiled = $compiled;
    }
}