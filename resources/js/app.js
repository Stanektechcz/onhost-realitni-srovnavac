import Alpine from 'alpinejs';
import { searchMap, detailMap, heatMap, amenities } from './map';
import { mortgageCalc, costCalc, yieldCalc, energyCalc, commuteCalc, mortgageLimitsCalc, rentBuyCalc, cashflowCalc, buyCostCalc, saleCalc, leaseCalc } from './calculators';

const store = {
    get(k, d) { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
};

// Side-by-side comparison (max 4), kept in the browser.
Alpine.store('compare', {
    items: store.get('compare', []),
    has(s) { return this.items.includes(s); },
    toggle(s) {
        this.items = this.has(s) ? this.items.filter((x) => x !== s) : [...this.items, s].slice(-4);
        store.set('compare', this.items);
    },
    clear() { this.items = []; store.set('compare', []); },
    get url() { return '/porovnat?l=' + this.items.join(','); },
});

Alpine.data('searchMap', searchMap);
Alpine.data('detailMap', detailMap);
Alpine.data('heatMap', heatMap);
Alpine.data('amenities', amenities);
Alpine.data('mortgageCalc', mortgageCalc);
Alpine.data('mortgageLimitsCalc', mortgageLimitsCalc);
Alpine.data('rentBuyCalc', rentBuyCalc);
Alpine.data('cashflowCalc', cashflowCalc);
Alpine.data('buyCostCalc', buyCostCalc);
Alpine.data('saleCalc', saleCalc);
Alpine.data('leaseCalc', leaseCalc);
Alpine.data('costCalc', costCalc);
Alpine.data('yieldCalc', yieldCalc);
Alpine.data('energyCalc', energyCalc);
Alpine.data('commuteCalc', commuteCalc);
Alpine.data('checklist', (key, n) => ({
    done: store.get('chk:' + key, []),
    toggle(i) { this.done = this.done.includes(i) ? this.done.filter((x) => x !== i) : [...this.done, i]; store.set('chk:' + key, this.done); },
    get pct() { return Math.round((this.done.length / n) * 100); },
}));

window.Alpine = Alpine;
Alpine.start();
