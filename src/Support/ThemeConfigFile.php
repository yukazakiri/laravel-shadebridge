<?php

declare(strict_types=1);

namespace Yukazakiri\Shadebridge\Support;

use Illuminate\Support\Facades\File;

final class ThemeConfigFile
{
    public function __construct(private readonly string $path) {}

    /**
     * @param  array{id: string, name: string, description: string, font: string, primary: string, secondary: string, accent: string}  $theme
     */
    public function addTheme(array $theme): bool
    {
        $content = File::get($this->path);
        $updated = $this->upsertColorThemeType($content, $theme['id']);

        $array = $this->parseThemesArray($updated);
        if ($array === null) {
            throw new \RuntimeException('Cannot parse theme catalog. Expected an exported ThemeConfig[] array.');
        }
        $objects = $this->extractThemeObjects($array['body']);
        $replaced = false;
        foreach ($objects as &$object) {
            if ($this->containsThemeObject($object, $theme['id'])) {
                $object = $this->formatThemeObject($theme);
                $replaced = true;
            }
        }
        unset($object);
        if (! $replaced) {
            $objects[] = $this->formatThemeObject($theme);
        }
        $updated = $array['before'].$array['open'].$this->formatThemesArrayBody($objects).$array['close'].$array['after'];

        if ($updated === $content) {
            return false;
        }

        File::put($this->path, $updated);

        return true;
    }

    /** @return list<string> */
    public function ids(): array
    {
        $array = $this->parseThemesArray(File::get($this->path));
        if ($array === null) {
            throw new \RuntimeException('Cannot parse theme catalog.');
        }
        preg_match_all('/\\bid:\\s*["\']([^"\']+)["\']/', $array['body'], $matches);

        return array_values(array_unique($matches[1]));
    }

    public function defaultTheme(): string
    {
        $content = File::get($this->path);
        preg_match('/export const defaultColorTheme(?::\\s*ColorTheme)?\\s*=\\s*["\']([^"\']+)["\'];/', $content, $matches);

        return $matches[1] ?? 'default';
    }

    public function setDefaultTheme(string $themeId): void
    {
        if (! in_array($themeId, $this->ids(), true)) {
            throw new \InvalidArgumentException('Default theme must be installed in the catalog.');
        }
        $content = File::get($this->path);
        $declaration = sprintf("export const defaultColorTheme: ColorTheme = '%s';", $this->escapeTypeScriptString($themeId));
        $pattern = '/export const defaultColorTheme(?::\\s*ColorTheme)?\\s*=\\s*["\'][^"\']+["\'];/';
        $content = preg_match($pattern, $content)
            ? preg_replace_callback($pattern, fn (): string => $declaration, $content)
            : $content."\n".$declaration."\n";
        File::put($this->path, $content);
    }

    public function removeTheme(string $themeId): bool
    {
        $content = File::get($this->path);
        $updated = $this->removeColorThemeType($content, $themeId);
        $updated = $this->removeThemeObject($updated, $themeId);

        if ($updated === $content) {
            return false;
        }

        File::put($this->path, $updated);
        if ($this->defaultTheme() === $themeId) {
            $this->setDefaultTheme('default');
        }

        return true;
    }

    private function upsertColorThemeType(string $content, string $themeId): string
    {
        return $this->replaceColorThemeTypes($content, function (array $types) use ($themeId): array {
            if (! in_array($themeId, $types, true)) {
                $types[] = $themeId;
            }

            return $types;
        });
    }

    private function removeColorThemeType(string $content, string $themeId): string
    {
        return $this->replaceColorThemeTypes($content, fn (array $types): array => array_values(array_filter(
            $types,
            fn (string $type): bool => $type !== $themeId,
        )));
    }

    /**
     * @param  callable(array<int, string>): array<int, string>  $callback
     */
    private function replaceColorThemeTypes(string $content, callable $callback): string
    {
        $pattern = '/export\s+type\s+ColorTheme\s*=\s*([\s\S]*?);/';

        if (! preg_match($pattern, $content, $matches)) {
            return $content;
        }

        preg_match_all('/["\']([^"\']+)["\']/', $matches[1], $typeMatches);

        $types = array_values(array_unique($callback($typeMatches[1])));

        if ($types === []) {
            $types = ['default'];
        }

        $replacement = "export type ColorTheme =\n".implode("\n", array_map(
            fn (string $type): string => sprintf("  | '%s'", $this->escapeTypeScriptString($type)),
            $types,
        )).';';

        return preg_replace($pattern, $replacement, $content) ?? $content;
    }

    private function containsThemeObject(string $content, string $themeId): bool
    {
        return preg_match('/id:\s*["\']'.preg_quote($themeId, '/').'["\']/', $content) === 1;
    }

    private function removeThemeObject(string $content, string $themeId): string
    {
        $array = $this->parseThemesArray($content);

        if ($array === null) {
            return $content;
        }

        $objects = $this->extractThemeObjects($array['body']);
        $remainingObjects = array_values(array_filter(
            $objects,
            fn (string $object): bool => ! $this->containsThemeObject($object, $themeId),
        ));

        if (count($remainingObjects) === count($objects)) {
            return $content;
        }

        return $array['before'].$array['open'].$this->formatThemesArrayBody($remainingObjects).$array['close'].$array['after'];
    }

    /**
     * @return array{before: string, open: string, body: string, close: string, after: string}|null
     */
    private function parseThemesArray(string $content): ?array
    {
        $pattern = '/(export\s+const\s+\w+\s*(?::\s*ThemeConfig\[\])?\s*=\s*\[)([\s\S]*?)(\n\];)/';

        if (! preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $matchStart = $matches[0][1];
        $matchEnd = $matchStart + strlen($matches[0][0]);

        return [
            'before' => substr($content, 0, $matchStart),
            'open' => $matches[1][0],
            'body' => $matches[2][0],
            'close' => $matches[3][0],
            'after' => substr($content, $matchEnd),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractThemeObjects(string $body): array
    {
        $objects = [];
        $depth = 0;
        $start = null;
        $quote = null;
        $escaped = false;
        $length = strlen($body);

        for ($index = 0; $index < $length; $index++) {
            $char = $body[$index];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;

                continue;
            }

            if ($char === '{') {
                if ($depth === 0) {
                    $start = $index;
                }

                $depth++;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $objects[] = $this->normalizeObjectIndentation(substr($body, $start, $index - $start + 1));
                    $start = null;
                }
            }
        }

        return $objects;
    }

    /**
     * @param  array<int, string>  $objects
     */
    private function formatThemesArrayBody(array $objects): string
    {
        if ($objects === []) {
            return "\n";
        }

        return "\n".implode(",\n", $objects).',';
    }

    /**
     * @param  array{id: string, name: string, description: string, font: string, primary: string, secondary: string, accent: string}  $theme
     */
    private function formatThemeObject(array $theme): string
    {
        return sprintf(
            <<<'TS'
  {
    id: '%s',
    name: '%s',
    description: '%s',
    font: '%s',
    colors: {
      primary: '%s',
      secondary: '%s',
      accent: '%s',
    },
  }
TS,
            $this->escapeTypeScriptString($theme['id']),
            $this->escapeTypeScriptString($theme['name']),
            $this->escapeTypeScriptString($theme['description']),
            $this->escapeTypeScriptString($theme['font']),
            $this->escapeTypeScriptString($theme['primary']),
            $this->escapeTypeScriptString($theme['secondary']),
            $this->escapeTypeScriptString($theme['accent']),
        );
    }

    private function normalizeObjectIndentation(string $object): string
    {
        $lines = explode("\n", trim($object));
        $indents = [];

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                preg_match('/^\s*/', $line, $matches);
                $indents[] = strlen($matches[0]);
            }
        }

        $remove = min($indents ?: [0]);

        return implode("\n", array_map(
            fn (string $line): string => '  '.substr($line, min($remove, strlen($line))),
            $lines,
        ));
    }

    private function escapeTypeScriptString(string $value): string
    {
        return str_replace(
            ['\\', "'", "\r", "\n"],
            ['\\\\', "\\'", ' ', ' '],
            $value,
        );
    }
}
