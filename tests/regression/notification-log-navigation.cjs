const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../modules/improved-notifications/assets/js/improved-notifications.js'), 'utf8');
for (const hasAddButton of [true, false]) {
    let inserted = null;
    const target = {length: 1, first() { return this; }};
    const button = {attr() { return this; }, addClass() { return this; }, text(value) { this.label = value; return this; }, insertAfter(value) { inserted = value; }};
    function $(selector) {
        if (selector === 'body') return {hasClass() { return true; }};
        if (selector === '<a>') return button;
        if (selector === '.wrap h1') return target;
        if (selector === '.wrap .page-title-action') return hasAddButton ? target : {length: 0, first() { return this; }};
        return {length: 0};
    }
    vm.runInNewContext(source, {jQuery(callback) { callback($); }, ppNotif: {log_url: '/log', log_text: 'Notifications Log', log_total: '5'}});
    assert.equal(inserted, target);
    assert.equal(button.label, 'Notifications Log (5)');
}
console.log('PASS: log navigation with and without an Add New button');
