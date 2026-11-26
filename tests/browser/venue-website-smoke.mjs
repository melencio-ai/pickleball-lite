import { chromium } from 'playwright';
import assert from 'node:assert/strict';
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:1440,height:900}});
const base = 'http://127.0.0.1:8000';
try {
    await page.goto(base + '/');
    await page.getByRole('heading',{name:'Your next game starts here.'}).waitFor();
    await page.getByRole('heading',{name:'Opening hours'}).waitFor();
    await page.locator('.site-navigation').getByRole('link',{name:'Book now'}).click();
    await page.waitForURL('**/book');
    await page.getByRole('heading',{name:'What would you like to book?'}).waitFor();
    await page.getByRole('link',{name:/Book a court/}).click();
    await page.getByRole('heading',{name:'When do you want to play?'}).waitFor();
    await page.goto(base + '/?step=2');
    await page.waitForURL('**/book?*');
    await page.getByRole('heading',{name:'How long do you want to play?'}).waitFor();
    await page.setViewportSize({width:390,height:844});
    await page.goto(base + '/');
    const cta = page.locator('.site-mobile-cta');
    await cta.waitFor({state:'visible'});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth <= window.innerWidth + 1),'Landing must fit mobile viewport');
    await cta.getByRole('link',{name:'Book now'}).click();
    await page.waitForURL('**/book');
    await page.getByRole('link',{name:/Join Open Play/}).click();
    await page.waitForURL('**/open-play');
    console.log('Venue website browser smoke passed.');
} finally {
    await browser.close();
}
