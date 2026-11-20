

const FILE = 'fonts/lemon-milk.otf';
const LOCAL = ['LEMON MILK Medium', 'LEMONMILK-Medium', 'Lemon Milk'];

export const displayFontReady: Promise<void> =
  typeof document === 'undefined' || !('fonts' in document) ? Promise.resolve() : load();

async function load(): Promise<void> {
  try {
    const src = [...LOCAL.map((n) => `local("${n}")`), `url("${FILE}")`].join(', ');
    const face = new FontFace('Lemon Milk', src, { weight: '500', display: 'swap' });
    await face.load();
    document.fonts.add(face);
  } catch {

    console.info('[Actify] Lemon Milk not found in /fonts, headings use Poppins. See public/fonts/README.md.');
  }
}
