/* Brew & Bite Café — JavaScript entry.
   Plain JS only (no Livewire / Alpine). Every module binds through document-level event
   delegation, so it keeps working whatever the server renders. JS never saves anything:
   all writes are normal HTML form submits handled by Laravel controllers. */
import { initTheme } from './core/theme';
import { initToast } from './core/toast';
import { initModals } from './core/modal';
import { initClock } from './core/clock';
import { initConfirm } from './core/confirm';
import { initCustomer } from './modules/customer';
import { initCashier } from './modules/cashier';

initTheme();
initToast();
initModals();
initClock();
initConfirm();
initCustomer();
initCashier();
