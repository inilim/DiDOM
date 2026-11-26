<?php

declare(strict_types=1);

namespace DiDom;

class Errors
{
    protected static bool $internalErrors;

    protected static bool $disableEntities;

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
        if ($clear) {
            \libxml_clear_errors();
        }

        \libxml_use_internal_errors(self::$internalErrors);

        if (\LIBXML_VERSION < 20900) {
            \libxml_disable_entity_loader(self::$disableEntities);
        }
    }
}
