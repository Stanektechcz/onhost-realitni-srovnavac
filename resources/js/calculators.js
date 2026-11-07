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

// --- Nástroje pro kupující, prodávající, nájemníky a pronajímatele (pravidla z config/reality_rules.php) ---

export function remainingBalance(P, ratePct, years, months) {
    const n = years * 12, r = ratePct / 100 / 12;
    if (P <= 0 || months >= n) return 0;
    if (r === 0) return P * (1 - months / n);
    const pmt = mortgagePayment(P, ratePct, years);
    return Math.max(0, P * Math.pow(1 + r, months) - pmt * ((Math.pow(1 + r, months) - 1) / r));
}

export function mortgageLimitsCalc(price, rules) {
    // Getters are copied as descriptors (spread would freeze them into values).
    const base = Object.defineProperties({}, Object.getOwnPropertyDescriptors(mortgageCalc(price)));
    return Object.defineProperties(base, Object.getOwnPropertyDescriptors({
        under36: false, investment: false, income: 60000, otherDebt: 0, rules,
        get ltvMax() { return this.investment ? rules.ltv_investment : (this.under36 ? rules.ltv_under36 : rules.ltv); },
        get maxLoan() { return this.price * this.ltvMax / 100; },
        get dti() { return this.income > 0 ? (this.loan + +this.otherDebt) / (this.income * 12) : null; },
        get dsti() { return this.income > 0 ? (this.payment / this.income) * 100 : null; },
        get dtiOk() { return !this.investment || this.dti === null || this.dti <= rules.dti_investment; },
    }));
}

export function rentVsBuy(o) {
    const loan = o.price * (1 - o.ownPct / 100);
    const pmt = mortgagePayment(loan, o.rate, o.loanYears);
    let portfolio = o.price * o.ownPct / 100 + o.buyCosts, value = o.price;
    const rows = [];
    for (let y = 1; y <= o.horizon; y++) {
        const rent = o.rent * 12 * Math.pow(1 + o.rentGrowth / 100, y - 1);
        const outlay = (y <= o.loanYears ? pmt * 12 : 0) + value * o.ownerCost / 100;
        portfolio = portfolio * (1 + o.invest / 100) + (outlay - rent);
        value *= 1 + o.priceGrowth / 100;
        rows.push({ y, buy: value - remainingBalance(loan, o.rate, o.loanYears, y * 12), rent: portfolio });
    }
    const last = rows[rows.length - 1] || { buy: o.price - loan, rent: portfolio };
    return { buy: last.buy, rent: last.rent, diff: last.buy - last.rent, payment: pmt, rows };
}

export function rentBuyCalc(price, rent) {
    return {
        price, rent, ownPct: 20, rate: 4.8, loanYears: 30, horizon: 15, priceGrowth: 3, rentGrowth: 3, ownerCost: 1, invest: 4, buyCosts: 40000,
        get r() { return rentVsBuy({ price: +this.price, ownPct: +this.ownPct, rate: +this.rate, loanYears: +this.loanYears, rent: +this.rent, horizon: +this.horizon, priceGrowth: +this.priceGrowth, rentGrowth: +this.rentGrowth, ownerCost: +this.ownerCost, invest: +this.invest, buyCosts: +this.buyCosts }); },
        fmt,
    };
}

export function rentalCashflow(o, rules) {
    const income = o.rent * 12 * (1 - o.vacancy / 100);
    const variant = (expenses) => {
        const base = Math.max(0, income - expenses), tax = Math.round(base * o.taxPct / 100);
        return { expenses, base, tax, cashflow: income - o.costs - o.debt - tax };
    };
    const flat = variant(Math.min(income * rules.flat_expense_pct / 100, rules.flat_expense_cap));
    const actual = variant(+o.costs + +o.interest + +o.depreciation);
    return { income, flat, actual, better: actual.tax < flat.tax ? 'actual' : 'flat' };
}

export function cashflowCalc(price, rent, rules) {
    return {
        price, rent, vacancy: 5, costs: 30000, ownPct: 30, rate: 5.2, years: 30, taxPct: 15, buildingPct: 80, rules,
        get loan() { return Math.max(0, this.price * (1 - this.ownPct / 100)); },
        get payment() { return mortgagePayment(this.loan, +this.rate, +this.years); },
        get interest() { return Math.max(0, this.payment * 12 - (this.loan - remainingBalance(this.loan, +this.rate, +this.years, 12))); },
        get depreciation() { return this.price * this.buildingPct / 100 * 0.034; },
        get r() { return rentalCashflow({ rent: +this.rent, vacancy: +this.vacancy, costs: +this.costs, debt: this.payment * 12, interest: this.interest, depreciation: this.depreciation, taxPct: +this.taxPct }, rules); },
        get grossYield() { return this.price ? (this.rent * 12 / this.price) * 100 : 0; },
        fmt,
    };
}

export function buyCostCalc(price, fee) {
    return {
        price, mortgage: true, valuation: 5000, escrowPct: 0.3, lawyer: 15000, brokerPct: 0, other: 3000, fee,
        get items() {
            return [
                ['Poplatek za vklad vlastnického práva', fee],
                ['Poplatek za vklad zástavního práva', this.mortgage ? fee : 0],
                ['Odhad nemovitosti', this.mortgage ? +this.valuation : 0],
                ['Úschova kupní ceny', Math.round(Math.max(10000, this.price * this.escrowPct / 100))],
                ['Sepis smlouvy a návrh na vklad', +this.lawyer],
                ['Provize makléře (platí-li ji kupující)', Math.round(this.price * this.brokerPct / 100)],
                ['Ostatní', +this.other],
            ];
        },
        get total() { return this.items.reduce((s, [, v]) => s + v, 0); },
        fmt,
    };
}

export function saleTax(o, rules) {
    const acq = new Date(o.acquired + 'T00:00:00'), sale = new Date(o.sold + 'T00:00:00');
    const testYears = acq < new Date('2021-01-01T00:00:00') ? 5 : 10;
    const from = new Date(acq); from.setFullYear(from.getFullYear() + testYears); from.setDate(from.getDate() + 1);
    const profit = Math.max(0, o.salePrice - o.purchasePrice - o.costs);
    let reason = '';
    if (sale >= from) reason = 'časový test ' + testYears + ' let splněn';
    else if (o.isHome && o.residenceYears >= 2) reason = 'bydliště alespoň 2 roky bezprostředně před prodejem';
    else if (o.reinvest) reason = 'prostředky použity na vlastní bytovou potřebu';
    let tax = 0;
    if (!reason) {
        const limit = Math.max(0, rules.high_rate_threshold - o.otherIncome);
        tax = Math.round(Math.min(profit, limit) * rules.rate / 100 + Math.max(0, profit - limit) * rules.rate_high / 100);
    }
    return { exempt: !!reason, reason, profit, tax, testYears, exemptFrom: from.toLocaleDateString('cs-CZ') };
}

export function saleCalc(price, rules) {
    return {
        salePrice: price, purchasePrice: Math.round(price * 0.7), costs: 0, acquired: '2020-06-01', sold: new Date().toISOString().slice(0, 10),
        residenceYears: 0, isHome: true, reinvest: false, otherIncome: 600000, brokerPct: 3, rules,
        get r() { return saleTax({ salePrice: +this.salePrice, purchasePrice: +this.purchasePrice, costs: +this.costs + this.broker, acquired: this.acquired, sold: this.sold, residenceYears: +this.residenceYears, isHome: this.isHome, reinvest: this.reinvest, otherIncome: +this.otherIncome }, rules); },
        get broker() { return Math.round(this.salePrice * this.brokerPct / 100); },
        get net() { return this.salePrice - this.broker - this.r.tax; },
        fmt,
    };
}

export function noticeEnd(delivered, months) {
    const d = new Date(delivered + 'T00:00:00');
    const start = new Date(d.getFullYear(), d.getMonth() + 1, 1);
    const end = new Date(start.getFullYear(), start.getMonth() + months, 0);
    return { start, end };
}

export function leaseCalc(rent, rules) {
    return {
        rent, deposit: rent * 2, delivered: new Date().toISOString().slice(0, 10), rules,
        get max() { return this.rent * rules.deposit_max_months; },
        get ok() { return +this.deposit <= this.max; },
        get notice() { return noticeEnd(this.delivered, rules.notice_months); },
        date(d) { return d.toLocaleDateString('cs-CZ'); },
        fmt,
    };
}
