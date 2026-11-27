import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:1440,height:900}});
const base = 'http://127.0.0.1:8000';
const style = (selector, property) => page.locator(selector).first().evaluate((node, key) => getComputedStyle(node)[key], property);
const token = name => page.evaluate(name => getComputedStyle(document.documentElement).getPropertyValue(name).trim(), name);
const rgb = hex => 'rgb(' + hex.slice(1).match(/../g).map(n=>parseInt(n,16)).join(', ') + ')';

async function saveBrand(color) {
    await page.setViewportSize({width:1440,height:900});
    await page.goto(base + '/staff/booking-setup');
    await page.locator('input[name="brand_color"]').fill(color);
    await page.getByRole('button',{name:'Save branding',exact:true}).click();
    await page.waitForFunction(expected=>getComputedStyle(document.documentElement).getPropertyValue('--brand-primary').trim()===expected,color);
    await page.getByRole('status').filter({hasText:'Booking page branding saved.'}).waitFor();
}

async function verifyBrand(color, onColor, mobile) {
    if (mobile) await page.setViewportSize({width:390,height:844});
    await page.goto(base + '/');
    await page.mouse.move(0,0);
    assert.equal(await token('--brand-primary'),color);
    assert.equal(await style('.site-navigation .site-primary','backgroundColor'),rgb(color));
    assert.equal(await style('.site-navigation .site-primary','color'),rgb(onColor));
    assert.equal(await style('.venue-site-header','borderTopColor'),rgb(color));
    const ink = await token('--brand-ink');
    const tint = await token('--brand-tint');
    const soft = await token('--brand-soft');
    assert.equal(await style('.site-hero h1','color'),rgb(ink));
    assert.equal(await style('.site-icon','color'),rgb(ink));
    assert.equal(await style('.site-icon','backgroundColor'),rgb(soft));
    assert.equal(await style('.site-band','backgroundColor'),rgb(tint));
    assert.equal(await style('.site-faq summary','color'),rgb(ink));
    if (mobile) assert.equal(await style('.site-mobile-cta .site-primary','backgroundColor'),rgb(color));
    await page.goto(base+'/book');
    assert.equal(await style('.booking-banner','backgroundColor'),rgb(color));
    assert.equal(await style('.booking-banner','color'),rgb(onColor));
    await page.goto(base+'/book?step=1');
    assert.equal(await style('.survey-progress .complete','backgroundColor'),rgb(ink));
    assert.equal(await style('.booking-button','backgroundColor'),rgb(color));
    assert.equal(await style('.booking-button','color'),rgb(onColor));
    await page.goto(base+'/open-play');
    assert.equal(await style('.survey-progress .complete','backgroundColor'),rgb(ink));
    await page.goto(base+'/staff/calendar');
    assert.equal(await style('.calendar-heading .button','backgroundColor'),rgb(color));
    assert.equal(await style('.calendar-heading .button','color'),rgb(onColor));
}
try {
    await page.goto(base+'/staff/login');
    await page.getByLabel('Email').fill('browser-calendar@example.test');
    await page.getByLabel('Password',{exact:true}).fill('browser-test-password-123');
    await page.getByRole('button',{name:'Sign in'}).click();
    await page.waitForURL('**/staff/calendar');
    await saveBrand('#5b21b6');
    await verifyBrand('#5b21b6','#ffffff',false);
    await saveBrand('#facc15');
    await verifyBrand('#facc15','#000000',true);
    await saveBrand('#155542');
    console.log('Venue theme browser smoke passed: saved dark and light branding, desktop and mobile, landing, booking, Open Play and staff calendar.');
} finally {
    await browser.close();
}
