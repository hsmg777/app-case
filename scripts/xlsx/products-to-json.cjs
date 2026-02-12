const XLSX = require("xlsx");

const inputPath = process.argv[2];

if (!inputPath) {
  console.error("Uso: node products-to-json.cjs <inputPath>");
  process.exit(1);
}

function normalizeHeader(value) {
  return String(value || "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/\s+/g, "_");
}

const wb = XLSX.readFile(inputPath, { cellDates: false });
const sheetName = wb.SheetNames[0];

if (!sheetName) {
  console.log("[]");
  process.exit(0);
}

const ws = wb.Sheets[sheetName];
const rawRows = XLSX.utils.sheet_to_json(ws, { defval: "", raw: false });

const rows = rawRows.map((row) => {
  const normalized = {};

  for (const [key, val] of Object.entries(row)) {
    normalized[normalizeHeader(key)] = val;
  }

  return {
    nombre: normalized.nombre || "",
    codigo_interno: normalized.codigo_interno || "",
    codigo_barras: normalized.codigo_barras || "",
    categoria: normalized.categoria || "",
    unidad_medida: normalized.unidad_medida || "",
    stock_minimo: normalized.stock_minimo || "",
    descripcion: normalized.descripcion || "",
    iva_porcentaje: normalized.iva_porcentaje || "",
    precio_unitario: normalized.precio_unitario || "",
  };
});

process.stdout.write(JSON.stringify(rows));
