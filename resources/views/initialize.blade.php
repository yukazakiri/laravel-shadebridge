@php
    $catalog = new \Yukzakiri\ThemeTools\Support\ThemeConfigFile(resource_path('js/conf/themes.ts'));
    $themeIds = $catalog->ids();
    $defaultTheme = $catalog->defaultTheme();
    if (! in_array($defaultTheme, $themeIds, true)) {
        $defaultTheme = 'default';
    }
@endphp
<script>
    (function () {
        var ids = {{ \Illuminate\Support\Js::from($themeIds) }};
        var fallback = {{ \Illuminate\Support\Js::from($defaultTheme) }};
        var key = {{ \Illuminate\Support\Js::from($storageKey ?? 'color-theme') }};
        var selected = fallback;
        try {
            var stored = localStorage.getItem(key);
            if (ids.indexOf(stored) !== -1) selected = stored;
            else localStorage.removeItem(key);
        } catch (e) {}
        var root = document.documentElement;
        Array.from(root.classList).forEach(function (name) {
            if (name.indexOf('theme-') === 0) root.classList.remove(name);
        });
        root.classList.add('theme-' + selected);
    })();
</script>
