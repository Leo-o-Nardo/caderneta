/**
 * Gera a marca da Caderneta (o ícone A+1 de docs/icone-estudos.html): os SVG de referência em
 * mobile/assets/brand e os PNG que o app usa em mobile/assets/images.
 *
 * Não depende de nada instalado além do Node: o "C" sai direto da Fraunces SemiBold do app (o
 * contorno do glifo, lido do arquivo .ttf) e os PNG são desenhados aqui mesmo.
 *
 * Uso, na raiz do projeto: node scripts/brand-icons.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import zlib from 'node:zlib';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const FONT = path.join(ROOT, 'mobile/node_modules/@expo-google-fonts/fraunces/600SemiBold/Fraunces_600SemiBold.ttf');
const BRAND = path.join(ROOT, 'mobile/assets/brand');
const IMAGES = path.join(ROOT, 'mobile/assets/images');

// As cores do guia (docs/design-guide.html, parte 02).
const MUSGO = '#2C5440';
const PAPEL = '#F4F0E8';
const TINTA = '#1E1F1C';

/*
 * O desenho, numa grade de 1024 (a mesma dos estudos): a capa em Musgo, o elástico de Tinta perto
 * da borda direita, o "C" e o traço duplo dos totais em Papel, um pouco à esquerda do centro.
 */
const GRID = 1024;
const LETTER = { center: 432, baseline: 660, size: 600 };
const BAND = { x: 800, width: 60 };
const RULES = [742, 796].map((y) => ({ x: 242, y, width: 380, height: 24, radius: 4 }));
// O canto do ícone redondo (splash e favicon), o mesmo do iPhone: 22,4% do lado.
const TILE_RADIUS = 229;
// O Android mostra só o círculo do meio: lá o desenho fica 15% menor, para o traço não encostar na borda.
const ANDROID_SCALE = 0.85;

// ---------------------------------------------------------------------------------------------
// O "C" da Fraunces: lê o contorno do glifo no arquivo TrueType (tabelas cmap, loca, glyf e hmtx).

function readFont(file) {
  const buf = fs.readFileSync(file);
  const tables = {};

  for (let i = 0; i < buf.readUInt16BE(4); i++) {
    const record = 12 + i * 16;
    tables[buf.toString('ascii', record, record + 4)] = buf.readUInt32BE(record + 8);
  }

  return {
    buf,
    tables,
    unitsPerEm: buf.readUInt16BE(tables.head + 18),
    longOffsets: buf.readInt16BE(tables.head + 50) === 1,
    hMetrics: buf.readUInt16BE(tables.hhea + 34),
  };
}

/** O número do glifo de um caractere, pela subtabela Unicode de formato 4 do cmap. */
function glyphIndex(font, char) {
  const { buf, tables } = font;
  const code = char.codePointAt(0);

  for (let i = 0; i < buf.readUInt16BE(tables.cmap + 2); i++) {
    const record = tables.cmap + 4 + i * 8;
    const platform = buf.readUInt16BE(record);
    const sub = tables.cmap + buf.readUInt32BE(record + 4);

    if (platform !== 3 || buf.readUInt16BE(sub) !== 4) {
      continue;
    }

    const segments = buf.readUInt16BE(sub + 6) / 2;
    const ends = sub + 14;
    const starts = ends + segments * 2 + 2;
    const deltas = starts + segments * 2;
    const offsets = deltas + segments * 2;

    for (let s = 0; s < segments; s++) {
      if (code > buf.readUInt16BE(ends + s * 2)) {
        continue;
      }

      const start = buf.readUInt16BE(starts + s * 2);
      const delta = buf.readUInt16BE(deltas + s * 2);
      const offset = buf.readUInt16BE(offsets + s * 2);

      if (code < start) {
        return 0;
      }

      if (offset === 0) {
        return (code + delta) & 0xffff;
      }

      const glyph = buf.readUInt16BE(offsets + s * 2 + offset + (code - start) * 2);

      return glyph === 0 ? 0 : (glyph + delta) & 0xffff;
    }
  }

  throw new Error(`Sem o caractere ${char} na fonte.`);
}

/** Os contornos de um glifo simples: listas de pontos { x, y, on } em unidades da fonte. */
function glyphContours(font, glyph) {
  const { buf, tables } = font;
  const location = (index) =>
    font.longOffsets ? buf.readUInt32BE(tables.loca + index * 4) : buf.readUInt16BE(tables.loca + index * 2) * 2;
  let p = tables.glyf + location(glyph);
  const count = buf.readInt16BE(p);

  if (count < 0) {
    throw new Error('O glifo é composto: este gerador só lê glifos simples.');
  }

  p += 10;
  const ends = [];
  for (let i = 0; i < count; i++, p += 2) {
    ends.push(buf.readUInt16BE(p));
  }

  p += 2 + buf.readUInt16BE(p);
  const total = ends[count - 1] + 1;
  const flags = [];

  while (flags.length < total) {
    const flag = buf[p++];
    flags.push(flag);

    if (flag & 8) {
      for (let repeat = buf[p++]; repeat > 0; repeat--) {
        flags.push(flag);
      }
    }
  }

  const coordinates = (shortBit, sameBit) => {
    const values = [];
    let value = 0;

    for (const flag of flags) {
      if (flag & shortBit) {
        value += flag & sameBit ? buf[p] : -buf[p];
        p += 1;
      } else if (!(flag & sameBit)) {
        value += buf.readInt16BE(p);
        p += 2;
      }

      values.push(value);
    }

    return values;
  };

  const xs = coordinates(2, 16);
  const ys = coordinates(4, 32);
  const contours = [];
  let first = 0;

  for (const end of ends) {
    const contour = [];
    for (let i = first; i <= end; i++) {
      contour.push({ x: xs[i], y: ys[i], on: Boolean(flags[i] & 1) });
    }
    contours.push(contour);
    first = end + 1;
  }

  return contours;
}

function advanceWidth(font, glyph) {
  const index = Math.min(glyph, font.hMetrics - 1);

  return font.buf.readUInt16BE(font.tables.hmtx + index * 4);
}

/**
 * Um contorno TrueType em comandos de caminho (M, L, Q, Z). Entre dois pontos fora da curva fica
 * um ponto na curva implícito, no meio deles.
 */
function contourCommands(points) {
  const n = points.length;
  const mid = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2, on: true });
  let startIndex = points.findIndex((point) => point.on);
  const start = startIndex >= 0 ? points[startIndex] : mid(points[0], points[1]);
  if (startIndex < 0) {
    startIndex = 0;
  }

  const commands = [['M', start.x, start.y]];
  let control = null;

  for (let k = 1; k <= n; k++) {
    const point = points[(startIndex + k) % n];

    if (point.on) {
      commands.push(control ? ['Q', control.x, control.y, point.x, point.y] : ['L', point.x, point.y]);
      control = null;
    } else if (control) {
      const implied = mid(control, point);
      commands.push(['Q', control.x, control.y, implied.x, implied.y]);
      control = point;
    } else {
      control = point;
    }
  }

  if (control) {
    commands.push(['Q', control.x, control.y, start.x, start.y]);
  }

  commands.push(['Z']);

  return commands;
}

/** O "C" já na grade de 1024, com o centro e a linha de base do desenho (como o text-anchor middle do SVG). */
function letterCommands() {
  const font = readFont(FONT);
  const glyph = glyphIndex(font, 'C');
  const scale = LETTER.size / font.unitsPerEm;
  const left = LETTER.center - (advanceWidth(font, glyph) * scale) / 2;
  const point = (x, y) => [left + x * scale, LETTER.baseline - y * scale];

  return glyphContours(font, glyph).flatMap(contourCommands).map(([op, ...values]) => {
    const mapped = [];
    for (let i = 0; i < values.length; i += 2) {
      mapped.push(...point(values[i], values[i + 1]));
    }
    return [op, ...mapped];
  });
}

// ---------------------------------------------------------------------------------------------
// As formas do desenho, como comandos de caminho na grade de 1024.

function rectCommands(x, y, width, height, radius = 0) {
  if (radius === 0) {
    return [['M', x, y], ['L', x + width, y], ['L', x + width, y + height], ['L', x, y + height], ['Z']];
  }

  const r = radius;
  // Cada canto como uma curva quadrática: no tamanho do ícone, não dá para ver a diferença de um arco.
  return [
    ['M', x + r, y],
    ['L', x + width - r, y],
    ['Q', x + width, y, x + width, y + r],
    ['L', x + width, y + height - r],
    ['Q', x + width, y + height, x + width - r, y + height],
    ['L', x + r, y + height],
    ['Q', x, y + height, x, y + height - r],
    ['L', x, y + r],
    ['Q', x, y, x + r, y],
    ['Z'],
  ];
}

/** Ajusta um desenho para o Android: menor, a partir do centro. */
function scaled(commands, factor) {
  const center = GRID / 2;

  return commands.map(([op, ...values]) => [op, ...values.map((value) => center + (value - center) * factor)]);
}

function svgPath(commands) {
  const round = (value) => Math.round(value * 100) / 100;

  return commands.map(([op, ...values]) => op + values.map(round).join(' ')).join('');
}

/** As camadas do ícone: { commands, color }. Sem fundo, para a frente do ícone do Android. */
function layers({ background = true, factor = 1, color } = {}) {
  const letter = letterCommands();
  const shapes = [
    { commands: rectCommands(BAND.x, 0, BAND.width, GRID), color: TINTA },
    { commands: letter, color: PAPEL },
    ...RULES.map((rule) => ({ commands: rectCommands(rule.x, rule.y, rule.width, rule.height, rule.radius), color: PAPEL })),
  ].map((shape) => ({ commands: scaled(shape.commands, factor), color: color ?? shape.color }));

  return background ? [{ commands: rectCommands(0, 0, GRID, GRID), color: MUSGO }, ...shapes] : shapes;
}

function svg(shapes) {
  const body = shapes.map((shape) => `  <path fill="${shape.color}" d="${svgPath(shape.commands)}"/>`).join('\n');

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${GRID} ${GRID}" width="${GRID}" height="${GRID}">\n${body}\n</svg>\n`;
}

// ---------------------------------------------------------------------------------------------
// O desenho em PNG: preenchimento com regra nonzero e 4 × 4 amostras por pixel (as bordas suaves).

const SAMPLES = 4;

/** Os segmentos de reta de um caminho, já no tamanho da imagem (as curvas viram 16 retas). */
function edges(commands, size) {
  const k = size / GRID;
  const list = [];
  let start = null;
  let last = null;
  const line = (a, b) => {
    if (a[1] !== b[1]) {
      list.push([a[0] * k, a[1] * k, b[0] * k, b[1] * k]);
    }
  };

  for (const [op, ...v] of commands) {
    if (op === 'M') {
      start = [v[0], v[1]];
      last = start;
    } else if (op === 'L') {
      line(last, [v[0], v[1]]);
      last = [v[0], v[1]];
    } else if (op === 'Q') {
      let previous = last;
      for (let i = 1; i <= 16; i++) {
        const t = i / 16;
        const u = 1 - t;
        const point = [u * u * last[0] + 2 * u * t * v[0] + t * t * v[2], u * u * last[1] + 2 * u * t * v[1] + t * t * v[3]];
        line(previous, point);
        previous = point;
      }
      last = [v[2], v[3]];
    } else if (op === 'Z') {
      line(last, start);
      last = start;
    }
  }

  return list;
}

/** Quanto de cada pixel uma forma cobre, de 0 a 1. */
function coverage(commands, size) {
  const cover = new Float32Array(size * size);
  const shapeEdges = edges(commands, size);
  const step = 1 / (SAMPLES * SAMPLES);

  for (let row = 0; row < size * SAMPLES; row++) {
    const y = (row + 0.5) / SAMPLES;
    const crossings = [];

    for (const [x0, y0, x1, y1] of shapeEdges) {
      if ((y0 <= y && y1 > y) || (y1 <= y && y0 > y)) {
        crossings.push([x0 + ((y - y0) * (x1 - x0)) / (y1 - y0), y1 > y0 ? 1 : -1]);
      }
    }

    crossings.sort((a, b) => a[0] - b[0]);
    const pixelRow = Math.floor(row / SAMPLES) * size;
    let winding = 0;

    for (let i = 0; i < crossings.length - 1; i++) {
      winding += crossings[i][1];

      if (winding === 0) {
        continue;
      }

      const from = Math.max(0, Math.ceil(crossings[i][0] * SAMPLES - 0.5));
      const to = Math.min(size * SAMPLES - 1, Math.floor(crossings[i + 1][0] * SAMPLES - 0.5));

      for (let column = from; column <= to; column++) {
        cover[pixelRow + Math.floor(column / SAMPLES)] += step;
      }
    }
  }

  return cover;
}

function hex(color) {
  return [1, 3, 5].map((i) => Number.parseInt(color.slice(i, i + 2), 16) / 255);
}

/** As camadas, uma por cima da outra, em RGBA de 0 a 1. Com clip, recorta tudo pelo canto arredondado. */
function render(shapes, size, { clip = null } = {}) {
  const pixels = new Float32Array(size * size * 4);

  for (const shape of shapes) {
    const cover = coverage(shape.commands, size);
    const [r, g, b] = hex(shape.color);

    for (let i = 0; i < size * size; i++) {
      const alpha = Math.min(1, cover[i]);
      if (alpha === 0) {
        continue;
      }

      const o = i * 4;
      const below = pixels[o + 3] * (1 - alpha);
      const out = alpha + below;
      pixels[o] = (r * alpha + pixels[o] * below) / out;
      pixels[o + 1] = (g * alpha + pixels[o + 1] * below) / out;
      pixels[o + 2] = (b * alpha + pixels[o + 2] * below) / out;
      pixels[o + 3] = out;
    }
  }

  if (clip) {
    const mask = coverage(clip, size);
    for (let i = 0; i < size * size; i++) {
      pixels[i * 4 + 3] *= Math.min(1, mask[i]);
    }
  }

  return pixels;
}

// ---------------------------------------------------------------------------------------------
// O arquivo PNG: cabeçalho, os pixels comprimidos com zlib e o fim, cada parte com o seu CRC.

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
  let c = n;
  for (let k = 0; k < 8; k++) {
    c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  }
  return c >>> 0;
});

function crc32(bytes) {
  let c = 0xffffffff;
  for (const byte of bytes) {
    c = CRC_TABLE[(c ^ byte) & 0xff] ^ (c >>> 8);
  }
  return (c ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
  const length = Buffer.alloc(4);
  length.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));

  return Buffer.concat([length, body, crc]);
}

/** Sem transparência (alpha false), o PNG sai em RGB: a App Store recusa ícone com canal alfa. */
function png(pixels, size, { alpha = true } = {}) {
  const channels = alpha ? 4 : 3;
  const raw = Buffer.alloc((size * channels + 1) * size);

  for (let y = 0; y < size; y++) {
    const row = y * (size * channels + 1);
    for (let x = 0; x < size; x++) {
      const i = (y * size + x) * 4;
      const o = row + 1 + x * channels;
      for (let c = 0; c < channels; c++) {
        raw[o + c] = Math.round(Math.max(0, Math.min(1, pixels[i + c])) * 255);
      }
    }
  }

  const header = Buffer.alloc(13);
  header.writeUInt32BE(size, 0);
  header.writeUInt32BE(size, 4);
  header[8] = 8;
  header[9] = alpha ? 6 : 2;

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', zlib.deflateSync(raw, { level: 9 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

// ---------------------------------------------------------------------------------------------

function write(file, content) {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, content);
  console.log(`${path.relative(ROOT, file)}  ${(fs.statSync(file).size / 1024).toFixed(1)} KB`);
}

const tile = rectCommands(0, 0, GRID, GRID, TILE_RADIUS);

// SVG de referência (README, documentação, site): o ícone inteiro e a versão de uma cor.
write(path.join(BRAND, 'caderneta-icone.svg'), svg(layers()));
write(path.join(BRAND, 'caderneta-icone-uma-cor.svg'), svg(layers({ background: false, color: TINTA })));

// Ícone geral e do iPhone: quadrado e opaco (o sistema arredonda os cantos).
write(path.join(IMAGES, 'icon.png'), png(render(layers(), 1024), 1024, { alpha: false }));

// Android: a frente sem fundo (o fundo é a cor Musgo do app.json) e a versão temática, de uma cor.
write(path.join(IMAGES, 'android-icon-foreground.png'), png(render(layers({ background: false, factor: ANDROID_SCALE }), 1024), 1024));
write(
  path.join(IMAGES, 'android-icon-monochrome.png'),
  png(render(layers({ background: false, factor: ANDROID_SCALE, color: '#FFFFFF' }), 1024), 1024),
);

// Splash e favicon: o ícone com o canto arredondado, sobre fundo transparente.
write(path.join(IMAGES, 'splash-icon.png'), png(render(layers(), 512, { clip: tile }), 512));
write(path.join(IMAGES, 'favicon.png'), png(render(layers(), 48, { clip: tile }), 48));

// O ícone dos e-mails e da página de aprovação, servido pelo backend (aparece com 28 px; 112 para telas densas).
write(path.join(ROOT, 'backend/public/images/caderneta-icone.png'), png(render(layers(), 112, { clip: tile }), 112));

// O desenho para o código do app (a animação de abertura), com a caixa da marca para o viewBox.
const markShapes = [letterCommands(), ...RULES.map((rule) => rectCommands(rule.x, rule.y, rule.width, rule.height, rule.radius))];
const coordinates = markShapes.flat().flatMap(([, ...values]) => values);
const xs = coordinates.filter((_, i) => i % 2 === 0);
const ys = coordinates.filter((_, i) => i % 2 === 1);
const PAD = 4;
const box = {
  x: Math.floor(Math.min(...xs)) - PAD,
  y: Math.floor(Math.min(...ys)) - PAD,
  width: Math.ceil(Math.max(...xs) - Math.min(...xs)) + PAD * 2,
  height: Math.ceil(Math.max(...ys) - Math.min(...ys)) + PAD * 2,
};

write(
  path.join(ROOT, 'mobile/src/theme/brand.ts'),
  `/*
 * O desenho do ícone da Caderneta, para o código do app (a animação de abertura).
 * Gerado por scripts/brand-icons.mjs, junto com os PNG: não edite à mão.
 */
export const BRAND = {
  // A grade do desenho: o ícone é um quadrado de ${GRID} de lado.
  grid: ${GRID},
  // O "C" e o traço duplo, em Papel, como caminhos SVG na grade.
  mark: [
${markShapes.map((commands) => `    '${svgPath(commands)}',`).join('\n')}
  ],
  // A caixa que contém a marca, para o viewBox do SVG.
  markBox: { x: ${box.x}, y: ${box.y}, width: ${box.width}, height: ${box.height} },
  // O elástico, de cima a baixo.
  band: { x: ${BAND.x}, width: ${BAND.width} },
  // O canto do ícone redondo da splash, em fração do lado (o mesmo do iPhone).
  tileRadius: ${TILE_RADIUS / GRID},
  // O tamanho do ícone na splash do sistema, em dp (o imageWidth do expo-splash-screen no app.json).
  splashSize: 96,
} as const;
`,
);
