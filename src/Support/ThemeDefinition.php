<?php

declare(strict_types=1);

namespace Yukzakiri\ThemeTools\Support;

use InvalidArgumentException;

/** Validates data before any source files are changed. No registry code is executed. */
final class ThemeDefinition
{
    public function validate(array $data): void
    {
        $vars = $data['cssVars'] ?? null;
        if (! is_array($vars) || ! is_array($vars['light'] ?? null) || $vars['light'] === []) {
            throw new InvalidArgumentException('Theme must contain a non-empty cssVars.light object.');
        }

        foreach ($vars as $section => $properties) {
            if (! in_array($section, ['theme', 'light', 'dark'], true) || ! is_array($properties)) {
                throw new InvalidArgumentException('cssVars supports only theme, light and dark objects.');
            }
            $this->validateProperties($properties);
        }

        $css = $data['css'] ?? [];
        if (! is_array($css)) {
            throw new InvalidArgumentException('Theme css must be an object.');
        }
        foreach ($css as $layer => $rules) {
            if ($layer !== '@layer base' || ! is_array($rules)) {
                throw new InvalidArgumentException('Only css @layer base rules are supported.');
            }
            foreach ($rules as $selector => $properties) {
                // Restrict selectors to simple descendants; reject nesting and global escape hatches.
                if (! is_string($selector) || ! preg_match('/^[a-zA-Z0-9.*#,:>+~_\s-]+$/D', $selector) || ! is_array($properties)) {
                    throw new InvalidArgumentException('Unsupported CSS selector or rule.');
                }
                $this->validateProperties($properties);
            }
        }
    }

    private function validateProperties(array $properties): void
    {
        foreach ($properties as $key => $value) {
            if (! is_string($key) || ! preg_match('/^(?:--)?[a-zA-Z][a-zA-Z0-9-]*$/D', $key)
                || ! is_string($value) || trim($value) === ''
                || preg_match('/[{};<>@\\\\\x00-\x1f]|\/\*|\*\/|url\s*\(|expression\s*\(/i', $value)) {
                throw new InvalidArgumentException('Invalid CSS property or value in theme definition.');
            }
        }
    }
}
