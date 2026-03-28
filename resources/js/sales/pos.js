import { initCart } from './pos-cart';
import { initPayment } from './pos-payment';
import { initClientSelector } from './pos-client';
import { initProductSearch } from './pos-product-search';
import { initTemporarySales } from './pos-temporary-sales';

document.addEventListener('DOMContentLoaded', () => {
    initCart();
    initPayment();
    initClientSelector();
    initProductSearch();
    initTemporarySales();
});
