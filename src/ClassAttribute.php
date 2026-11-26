<?php

declare(strict_types=1);

namespace DiDom;

class ClassAttribute
{
    /**
     * The DOM element instance.
     */
    protected Element $element;

    /**
     */
    protected string $classesString = '';

    /**
     * @var string[]
     */
    protected array $classes = [];

    /**
     * @throws \InvalidArgumentException if parameter 1 is not an element node
     */
    public function __construct(Element $element)
    {
        if (false === $element->isElementNode()) {
            throw new \InvalidArgumentException(\sprintf('The element must contain DOMElement node.'));
        }

        $this->element = $element;

        $this->parseClassAttribute();
    }

    /**
     * Parses class attribute of the element.
     */
    protected function parseClassAttribute(): void
    {
        if (false === $this->element->hasAttribute('class')) {
            // possible if class attribute has been removed
            if ('' !== $this->classesString) {
                $this->classesString = '';
                $this->classes = [];
            }

            return;
        }

        // if class attribute is not changed
        if ($this->element->getAttribute('class') === $this->classesString) {
            return;
        }

        // save class attribute as is (without trimming)
        $this->classesString = $this->element->getAttribute('class');

        $classesString = \trim($this->classesString);

        if ('' === $classesString) {
            $this->classes = [];

            return;
        }

        $classes = [];
        foreach (\explode(' ', $classesString) as $class) {
            $class = \trim($class);
            if ('' === $class) {
                continue;
            }
            $classes[] = $class;
        }

        $this->classes = \array_values(\array_unique($classes));
    }

    /**
     * Updates class attribute of the element.
     */
    protected function updateClassAttribute(): void
    {
        $this->classesString = \implode(' ', $this->classes);

        $this->element->setAttribute('class', $this->classesString);
    }

    /**
     * @throws \InvalidArgumentException if class name is not a string
     */
    public function add(string $className): self
    {
        $this->parseClassAttribute();

        if (true === \in_array($className, $this->classes, true)) {
            return $this;
        }

        $this->classes[] = $className;

        $this->updateClassAttribute();

        return $this;
    }

    /**
     * @param array $classNames
     *
     * @throws \InvalidArgumentException if class name is not a string
     */
    public function addMultiple(array $classNames): self
    {
        $this->parseClassAttribute();

        foreach ($classNames as $className) {
            if (false === \is_string($className)) {
                throw new \InvalidArgumentException(\sprintf('Class name must be a string, %s given.', (\is_object($className) ? \get_class($className) : \gettype($className))));
            }

            if (true === \in_array($className, $this->classes, true)) {
                continue;
            }

            $this->classes[] = $className;
        }

        $this->updateClassAttribute();

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAll(): array
    {
        $this->parseClassAttribute();

        return $this->classes;
    }

    /**
     */
    public function contains(string $className): bool
    {
        $this->parseClassAttribute();

        return \in_array($className, $this->classes, true);
    }

    /**
     * @throws \InvalidArgumentException if class name is not a string
     */
    public function remove(string $className): self
    {
        $this->parseClassAttribute();

        $classIndex = \array_search($className, $this->classes);

        if (false === $classIndex) {
            return $this;
        }

        unset($this->classes[$classIndex]);

        $this->updateClassAttribute();

        return $this;
    }

    /**
     * @param array $classNames
     * @throws \InvalidArgumentException if class name is not a string
     */
    public function removeMultiple(array $classNames): self
    {
        $this->parseClassAttribute();

        foreach ($classNames as $className) {
            if (false === \is_string($className)) {
                throw new \InvalidArgumentException(\sprintf('Class name must be a string, %s given.', (\is_object($className) ? \get_class($className) : \gettype($className))));
            }

            $classIndex = \array_search($className, $this->classes);

            if (false === $classIndex) {
                continue;
            }

            unset($this->classes[$classIndex]);
        }

        $this->updateClassAttribute();

        return $this;
    }

    /**
     * @param string[] $preserved
     */
    public function removeAll(array $preserved = []): self
    {
        $this->parseClassAttribute();

        $preservedClasses = [];

        foreach ($preserved as $className) {
            if (false === \is_string($className)) {
                throw new \InvalidArgumentException(\sprintf('Class name must be a string, %s given.', (\is_object($className) ? \get_class($className) : \gettype($className))));
            }

            if (false === \in_array($className, $this->classes, true)) {
                continue;
            }

            $preservedClasses[] = $className;
        }

        $this->classes = $preservedClasses;

        $this->updateClassAttribute();

        return $this;
    }

    /**
     */
    public function getElement(): Element
    {
        return $this->element;
    }
}