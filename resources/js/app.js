import './bootstrap';

import Alpine from 'alpinejs';
import './sales/pos';
import { Chart, registerables } from 'chart.js';

window.Alpine = Alpine;
Chart.register(...registerables);
window.Chart = Chart;

Alpine.start();
