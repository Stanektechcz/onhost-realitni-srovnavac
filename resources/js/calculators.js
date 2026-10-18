// Orientační kalkulačky – zrcadlí App\Support\Calculators. Nejde o finanční poradenství.
export const PENB_KWH = { A: 40, B: 75, C: 112, D: 155, E: 200, F: 260, G: 330 };
export const fmt = (n) => (isFinite(n) ? Math.round(n).toLocaleString('cs-CZ') : '—');

export function mortgagePayment(P, ratePct, years) {
    const n = years * 12, r = ratePct / 100 / 12;
    if (n <= 0 || P <= 0) return 0;
    return r === 0 ? P / n : (P * r) / (1 - Math.pow(1 + r, -n));
}

export function mortgageCalc(price) {
    return {
        price, ownPct: 20, rate: 4.9, years: 30,
        get loan() { return Math.max(0, this.price * (1 - this.ownPct / 100)); },
        get payment() { return mortgagePayment(this.loan, +this.rate, +this.years); },
        get total() { return this.payment * this.years * 12; },
        get interest() { return this.total - this.loan; },
        get ltv() { return this.price ? (this.loan / this.price) * 100 : 0; },
        get minIncome() { return this.payment / 0.45; },
        fmt,
    };
}

export function costCalc(rent, area, penb) {
    return {
        rent, fees: 4000, energy: Math.round(((PENB_KWH[penb] || 155) * area * 4) / 12), internet: 450, insurance: 150, other: 0,
        get total() { return +this.rent + +this.fees + +this.energy + +this.internet + +this.insurance + +this.other; },
        get yearly() { return this.total * 12; },
        fmt,
    };
}

export function yieldCalc(price, rent) {
    return {
        price, rent, costs: 24000, vacancy: 5, taxPct: 15, flat: 30,
        get income() { return this.rent * 12 * (1 - this.vacancy / 100); },
        get gross() { return this.price ? (this.rent * 12 / this.price) * 100 : 0; },
        get net() { return this.price ? ((this.income - this.costs) / this.price) * 100 : 0; },
        get tax() { return Math.max(0, this.income * (1 - this.flat / 100)) * this.taxPct / 100; },
        get netAfterTax() { return this.price ? ((this.income - this.costs - this.tax) / this.price) * 100 : 0; },
        get payback() { const y = this.income - this.costs - this.tax; return y > 0 ? this.price / y : Infinity; },
        fmt,
    };
}

export function energyCalc(area, penb) {
    return {
        area, penb, kwhPrice: 4,
        get kwh() { return (PENB_KWH[this.penb] || 0) * this.area; },
        get monthly() { return (this.kwh * this.kwhPrice) / 12; },
        compare(c) { return ((PENB_KWH[c] * this.area * this.kwhPrice) / 12); },
        classes: Object.keys(PENB_KWH), fmt,
    };
}

export function commuteCalc() {
    return {
        km: 12, days: 21, mode: 'pt', fuel: 6.5, fuelPrice: 38, pass: 550, minutes: 35,
        get monthly() { return this.mode === 'car' ? (this.km * 2 * this.days * this.fuel / 100) * this.fuelPrice : +this.pass; },
        get hours() { return (this.minutes * 2 * this.days) / 60; },
        fmt,
    };
}
