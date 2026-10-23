export type ColorTheme =
  | 'default'
  | 'rose'
  | 'ocean';

export type ThemeConfig = {
  id: ColorTheme;
  name: string;
  description: string;
  font: string;
  colors: {
    primary: string;
    secondary: string;
    accent: string;
  };
};

export const themes: ThemeConfig[] = [
  {
    id: 'default',
    name: 'Default',
    description: 'Neutral theme with the default shadcn palette.',
    font: 'Instrument Sans',
    colors: {
      primary: 'oklch(0.205 0 0)',
      secondary: 'oklch(0.97 0 0)',
      accent: 'oklch(0.97 0 0)',
    },
  },
  {
    id: 'rose',
    name: 'Rose',
    description: 'Warm reds with soft neutral accents.',
    font: 'Instrument Sans',
    colors: {
      primary: 'oklch(0.62 0.24 18)',
      secondary: 'oklch(0.96 0.03 20)',
      accent: 'oklch(0.9 0.08 20)',
    },
  },
  {
    id: 'ocean',
    name: 'Ocean',
    description: 'Cool blues inspired by deep water.',
    font: 'Instrument Sans',
    colors: {
      primary: 'oklch(0.55 0.2 230)',
      secondary: 'oklch(0.92 0.04 210)',
      accent: 'oklch(0.84 0.07 220)',
    },
  },
];

export const defaultColorTheme: ColorTheme = 'default';
