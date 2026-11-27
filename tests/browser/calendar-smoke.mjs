import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const browser = await chromium.launch({headless: true});
const page = await browser.newPage({viewport: {width: 1440, height: 900}});
const errors = [];
page.on('pageerror', error => errors.push(error.message));
const tomorrow = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
const base = 'http://127.0.0.1:8000';
try {
    await page.goto(base + '/staff/login');
    await page.getByLabel('Email').fill('browser-calendar@example.test');
    await page.getByLabel('Password',{exact:true}).fill('browser-test-password-123');
    await page.getByRole('button', {name:'Sign in'}).click();
    await page.waitForURL('**/staff/calendar');
    await page.goto(base + '/staff/calendar?date=' + tomorrow);
    const dialog = page.locator('#calendar-dialog');
    const grid = page.locator('[data-calendar-grid]');
    const slot = page.locator('[data-calendar-key="slot-1-10:00"]');
    await slot.click();
    await dialog.getByRole('heading', {name:'What would you like to add?'}).waitFor();
    await dialog.getByRole('link', {name:/Court booking/}).click();
    await dialog.getByRole('heading', {name:'Add a booking'}).waitFor();
    assert.equal(await dialog.locator('input[name="start"]').inputValue(), '10:00');
    assert.equal(await dialog.locator('select[name="court_id"]').inputValue(), '1');
    assert.equal(await dialog.locator('input[name="date"]').inputValue(), tomorrow);
    await dialog.getByLabel('Full name').fill('Browser Test Player');
    await dialog.getByLabel('Mobile number').fill('09170000000');
    await dialog.getByRole('button', {name:'Confirm booking'}).click();
    await page.waitForFunction(() => !document.querySelector('#calendar-dialog').open);
    await grid.locator('.calendar-event', {hasText:'Browser Test Player'}).waitFor();
    assert.equal(await slot.count(), 0);
    assert.equal(new URL(page.url()).searchParams.get('date'), tomorrow);
    await grid.locator('.calendar-event', {hasText:'Browser Test Player'}).click();
    await dialog.getByRole('heading', {name:'Booking details'}).waitFor();
    await dialog.getByRole('button', {name:'Mark paid'}).click();
    await page.waitForFunction(() => !document.querySelector('#calendar-dialog').open);
    await grid.locator('.calendar-event--paid', {hasText:'Browser Test Player'}).waitFor();

    await page.setViewportSize({width: 390, height: 844});
    await grid.locator('.calendar-event--paid', {hasText:'Browser Test Player'}).click();
    await dialog.getByRole('heading', {name:'Booking details'}).waitFor();
    const bounds = await dialog.boundingBox();
    assert.ok(bounds && bounds.width <= 390 && bounds.y > 0, 'Mobile dialog must fit within the viewport');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), 'Page must not overflow horizontally');
    await dialog.getByText('Cancel booking', {exact:true}).click();
    await dialog.getByRole('button', {name:'Confirm cancellation'}).click();
    await page.waitForFunction(() => !document.querySelector('#calendar-dialog').open);
    await slot.waitFor();
    await page.getByRole('link', {name:'New',exact:true}).click();
    await dialog.getByRole('heading', {name:'What would you like to add?'}).waitFor();
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => !document.querySelector('#calendar-dialog').open);
    assert.deepEqual(errors, []);
    console.log('Calendar browser smoke passed: desktop create/mark paid, mobile cancel, date preserved, dialog and slot refresh.');
} finally {
    await browser.close();
}
