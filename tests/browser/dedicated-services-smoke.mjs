import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import http from 'node:http';

const web='http://pickle-web.test';
const app='http://pickle-app.test';
const book='http://pickle-book.test';
const slug='pickleball-lite-courts';
const browser=await chromium.launch({headless:true,args:[
    '--no-proxy-server',
    '--host-resolver-rules=MAP pickle-web.test 127.0.0.1:8001, MAP pickle-app.test 127.0.0.1:8002, MAP pickle-book.test 127.0.0.1:8003',
]});
const context=await browser.newContext({viewport:{width:1440,height:900}});
const page=await context.newPage();

function status(port,host,path='/') {
    return new Promise((resolve,reject)=>{
        const req=http.request({host:'127.0.0.1',port,path,headers:{Host:host}},res=>{
            res.resume(); resolve(res.statusCode);
        });
        req.on('error',reject); req.end();
    });
}
try {
    assert.equal(await status(8001,'pickle-app.test','/login'),404);
    assert.equal(await status(8002,'pickle-book.test','/'+slug),404);
    assert.equal(await status(8003,'pickle-app.test','/login'),404);
    assert.equal(await status(8003,'pickle-web.test'),404);
    await page.goto(web);
    await page.getByRole('heading',{name:'More games. Less admin work.'}).waitFor();
    await page.locator('.site-navigation nav').getByRole('link',{name:'Sign in'}).click();
    await page.waitForURL(app+'/login');
    await page.getByLabel('Email').fill('browser-calendar@example.test');
    await page.getByLabel('Password').fill('browser-test-password-123');
    await page.getByRole('button',{name:'Sign in'}).click();
    await page.waitForURL(app+'/venues');
    await page.getByRole('link',{name:/Manage venue/}).click();
    await page.waitForURL(app+'/venues/'+slug+'/calendar');
    const website=page.locator('.site-header').getByRole('link',{name:'Venue website'});
    assert.equal(await website.getAttribute('href'),book+'/'+slug);
    await page.goto(book+'/'+slug);
    await page.getByRole('heading',{name:'Your next game starts here.'}).waitFor();
    await page.locator('.site-navigation').getByRole('link',{name:'Book now'}).click();
    await page.waitForURL(book+'/'+slug+'/book');
    await page.getByRole('heading',{name:'What would you like to book?'}).waitFor();
    await page.getByRole('link',{name:/Book a court/}).click();
    await page.getByRole('heading',{name:'When do you want to play?'}).waitFor();
    const cookies=await context.cookies();
    for(const [domain,name] of [['pickle-web.test','pickle_web_session'],['pickle-app.test','pickle_app_test_session'],['pickle-book.test','pickle_book_session']]) {
        assert.ok(cookies.some(c=>c.name===name&&c.domain===domain&&c.httpOnly), 'Missing host-only session cookie for '+domain);
        assert.ok(!cookies.some(c=>c.name===name&&c.domain!==domain), 'Session cookie leaked across service hosts');
    }
    await page.goto(app+'/venues');
    await page.getByRole('link',{name:/Manage venue/}).waitFor();
    console.log('Three dedicated service browser checks passed: product, sign-in, venue selection, public booking and isolated cookies.');
} finally {
    await browser.close();
}
