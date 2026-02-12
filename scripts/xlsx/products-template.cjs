const fs = require("fs");
const path = require("path");
const XLSX = require("xlsx");

const outputPath = process.argv[2];

if (!outputPath) {
  console.error("Uso: node products-template.cjs <outputPath>");
  process.exit(1);
}

const headers = [
  "nombre",
  "codigo_interno",
  "codigo_barras",
  "categoria",
  "unidad_medida",
  "stock_minimo",
  "descripcion",
  "iva_porcentaje",
  "precio_unitario",
];

const exampleRow = [
  "Papel bond A4",
  "PB-A4-001",
  "7501234567890",
  "Utiles escolares",
  "unidad",
  10,
  "Resma de papel bond",
  15,
  4.25,
];

const ws = XLSX.utils.aoa_to_sheet([headers, exampleRow]);
const wb = XLSX.utils.book_new();
XLSX.utils.book_append_sheet(wb, ws, "productos");

fs.mkdirSync(path.dirname(outputPath), { recursive: true });
XLSX.writeFile(wb, outputPath, { bookType: "xlsx" });
