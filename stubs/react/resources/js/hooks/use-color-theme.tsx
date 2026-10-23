import { defaultColorTheme, themes, type ColorTheme } from "@/conf/themes";
import { useCallback, useSyncExternalStore } from "react";

export type UseColorThemeReturn = {
  readonly colorTheme: ColorTheme;
  readonly updateColorTheme: (theme: ColorTheme) => void;
};

const listeners = new Set<() => void>();
const themeClassPrefix = "theme-";
let currentTheme: ColorTheme = defaultColorTheme;

const isValidTheme = (theme: string | null): theme is ColorTheme => {
  if (!theme) {
    return false;
  }

  return themes.some((config) => config.id === theme);
};

const setCookie = (name: string, value: string, days = 365): void => {
  if (typeof document === "undefined") {
    return;
  }

  const maxAge = days * 24 * 60 * 60;
  document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getStoredTheme = (): ColorTheme => {
  if (typeof window === "undefined") {
    return defaultColorTheme;
  }

  const storedTheme = localStorage.getItem("color-theme");

  if (isValidTheme(storedTheme)) {
    return storedTheme;
  }

  localStorage.removeItem("color-theme");
  return defaultColorTheme;
};

const applyTheme = (theme: ColorTheme): void => {
  if (typeof document === "undefined") {
    return;
  }

  const root = document.documentElement;
  const themeClasses = Array.from(root.classList).filter((className) =>
    className.startsWith(themeClassPrefix),
  );

  themeClasses.forEach((className) => root.classList.remove(className));

  if (theme !== "default") {
    root.classList.add(`${themeClassPrefix}${theme}`);
  }
};

const subscribe = (callback: () => void) => {
  listeners.add(callback);

  return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializeColorTheme(): void {
  if (typeof window === "undefined") {
    return;
  }

  currentTheme = getStoredTheme();
  applyTheme(currentTheme);
}

export function useColorTheme(): UseColorThemeReturn {
  const colorTheme: ColorTheme = useSyncExternalStore(
    subscribe,
    () => currentTheme,
    () => defaultColorTheme,
  );

  const updateColorTheme = useCallback((theme: ColorTheme): void => {
    currentTheme = theme;
    localStorage.setItem("color-theme", theme);
    setCookie("color-theme", theme);
    applyTheme(theme);
    notify();
  }, []);

  return { colorTheme, updateColorTheme } as const;
}
