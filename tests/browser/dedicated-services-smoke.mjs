import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import http from 'node:http';
import { execFileSync } from 'node:child_process';

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
    await page.getByRole('heading',{name:'Welcome back'}).waitFor();
    assert.equal(await page.locator('.auth-aside').isVisible(),true);
    await page.setViewportSize({width:390,height:844});
    assert.equal(await page.locator('.auth-aside').isVisible(),false);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth));
    await page.getByLabel('Email address',{exact:true}).fill('browser-calendar@example.test');
    const password=page.getByLabel('Password',{exact:true});
    await password.fill('wrong-password');
    await page.getByRole('button',{name:'Show password',exact:true}).click();
    assert.equal(await password.getAttribute('type'),'text');
    await page.getByRole('button',{name:'Hide password',exact:true}).click();
    assert.equal(await password.getAttribute('type'),'password');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await page.getByRole('alert').filter({hasText:'The email or password is incorrect.'}).waitFor();
    assert.equal(await page.getByLabel('Email address',{exact:true}).inputValue(),'browser-calendar@example.test');
    await page.getByLabel('Password',{exact:true}).fill('browser-test-password-123');
    await page.getByLabel('Remember me',{exact:true}).check();
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await page.waitForURL(app+'/venues/'+slug+'/calendar');
    await page.setViewportSize({width:1440,height:900});
    const navigation = page.locator('#workspace-navigation');
    assert.equal(await navigation.isVisible(),true);
    await page.getByRole('button',{name:'Toggle navigation'}).click();
    assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('workspace-collapsed')));
    await page.getByRole('button',{name:'Toggle navigation'}).click();
    await page.setViewportSize({width:320,height:740});
    await page.waitForFunction(()=>getComputedStyle(document.querySelector('#workspace-navigation')).visibility==='hidden');
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile management page must not overflow');
    await page.getByRole('button',{name:'Toggle navigation'}).click();
    await navigation.waitFor({state:'visible'});
    assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('workspace-open')));
    await page.keyboard.press('Escape');
    await page.waitForFunction(()=>!document.body.classList.contains('workspace-open'));
    await page.locator('.workspace-bell').click();
    await page.waitForURL(app+'/notifications');
    await page.getByRole('button',{name:'Mark all as read'}).click();
    await page.locator('[data-toast-region]').getByText('All notifications marked as read.').waitFor();
    await page.setViewportSize({width:1440,height:900});
    await page.goto(app+'/venues/'+slug+'/calendar');
    const website=page.locator('.site-header').getByRole('link',{name:'Venue website'});
    assert.equal(await website.getAttribute('href'),book+'/'+slug);
    // Fixture-only uploaded venue logo. Use the same image endpoint on the
    // website and booking pages, and verify the browser can decode it.
    execFileSync('php',['artisan','tinker','--execute='+[
        '$venue=App\\Models\\Venue::where("slug","pickleball-lite-courts")->firstOrFail();',
        '$png="iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII=";',
        '$venue->media()->updateOrCreate(["kind"=>"logo"],["mime"=>"image/png","version"=>hash("sha256",base64_decode($png)),"content"=>$png]);',
    ].join(' ')],{stdio:'pipe'});
    await page.goto(book+'/'+slug);
    const venueLogo=await page.locator('.site-navigation .site-logo img').getAttribute('src');
    const venueName=await page.locator('.site-navigation .site-logo strong').textContent();
    assert.ok(venueLogo.startsWith(book+'/'+slug+'/branding/logo/'));
    await page.getByRole('heading',{name:'Your next game starts here.'}).waitFor();
    await page.locator('.site-navigation').getByRole('link',{name:'Book now'}).click();
    await page.waitForURL(book+'/'+slug+'/book');
    await page.getByRole('heading',{name:'What would you like to book?'}).waitFor();
    const publicHeader=page.locator('.public-booking-header');
    assert.equal(await publicHeader.locator('.site-logo img').getAttribute('src'),venueLogo);
    assert.equal(await publicHeader.locator('.site-logo strong').textContent(),venueName);
    await publicHeader.locator('.site-logo img').evaluate(img=>img.decode());
    assert.equal(await publicHeader.locator('.site-logo').getAttribute('href'),book+'/'+slug);
    assert.equal(await publicHeader.getByRole('link',{name:'Rates',exact:true}).getAttribute('href'),book+'/'+slug+'#rates');
    assert.equal(await publicHeader.getByRole('link',{name:'Open Play',exact:true}).getAttribute('href'),book+'/'+slug+'/open-play');
    await page.setViewportSize({width:320,height:740});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile booking header must not overflow');
    await publicHeader.getByRole('link',{name:'Home',exact:true}).click();
    await page.waitForURL(book+'/'+slug);
    await page.locator('.site-navigation').getByRole('link',{name:'Book now',exact:true}).click();
    await page.waitForURL(book+'/'+slug+'/book');
    await page.getByRole('link',{name:/Book a court/}).click();
    await page.getByRole('heading',{name:'When do you want to play?'}).waitFor();
    assert.equal(await publicHeader.locator('.site-logo img').getAttribute('src'),venueLogo);
    await publicHeader.getByRole('link',{name:'Open Play',exact:true}).click();
    await page.waitForURL(book+'/'+slug+'/open-play');
    assert.equal(await publicHeader.getByRole('link',{name:'Open Play',exact:true}).getAttribute('aria-current'),'page');
    assert.equal(await publicHeader.locator('.site-logo img').getAttribute('src'),venueLogo);
    await page.setViewportSize({width:1440,height:900});
    console.log('Public booking header passed: uploaded venue logo, correct venue-scoped navigation, mobile fit, court booking and Open Play.');
    const cookies=await context.cookies();
    assert.ok(cookies.some(c=>c.name.startsWith('remember_web_')&&c.domain==='pickle-app.test'&&c.httpOnly&&c.expires>0));
    assert.ok(!cookies.some(c=>c.name.startsWith('remember_web_')&&c.domain!=='pickle-app.test'));
    for(const [domain,name] of [['pickle-web.test','pickle_web_session'],['pickle-app.test','pickle_app_test_session'],['pickle-book.test','pickle_book_session']]) {
        assert.ok(cookies.some(c=>c.name===name&&c.domain===domain&&c.httpOnly), 'Missing host-only session cookie for '+domain);
        assert.ok(!cookies.some(c=>c.name===name&&c.domain!==domain), 'Session cookie leaked across service hosts');
    }
    await page.goto(app);
    await page.waitForURL(app+'/venues/'+slug+'/calendar');
    await page.goto(app+'/venues');
    await page.getByRole('link',{name:/Manage venue/}).waitFor();
    const signOut=page.locator('[data-sign-out-open]');
    const confirmation=page.getByRole('dialog',{name:'Sign out?'});
    const venuesUrl=page.url();
    let logoutPosts=0;
    page.on('request',request=>{if(request.method()==='POST' && request.url()===app+'/logout')logoutPosts++;});
    await signOut.click();
    await confirmation.waitFor();
    assert.equal(await page.locator('[data-sign-out-cancel]').evaluate(el=>el===document.activeElement),true);
    await confirmation.getByRole('button',{name:'Cancel',exact:true}).click();
    await confirmation.waitFor({state:'hidden'});
    assert.equal(page.url(),venuesUrl);
    assert.equal(logoutPosts,0);
    assert.equal(await signOut.evaluate(el=>el===document.activeElement),true);
    await signOut.click();
    await confirmation.waitFor();
    await page.keyboard.press('Escape');
    await confirmation.waitFor({state:'hidden'});
    assert.equal(logoutPosts,0);
    await page.setViewportSize({width:320,height:740});
    await page.getByRole('button',{name:'Toggle navigation'}).click();
    await signOut.click();
    await confirmation.waitFor();
    const bounds=await confirmation.boundingBox();
    assert.ok(bounds && bounds.x>=0 && bounds.x+bounds.width<=320 && bounds.y>=0 && bounds.y+bounds.height<=740);
    await confirmation.getByRole('button',{name:'Cancel',exact:true}).click();
    await confirmation.waitFor({state:'hidden'});
    await signOut.click();
    await confirmation.getByRole('button',{name:'Sign out',exact:true}).click();
    await page.waitForURL(app+'/login');
    assert.equal(logoutPosts,1);
    await page.goto(app+'/venues');
    await page.waitForURL(app+'/login');
    console.log('Sign-out confirmation passed on desktop and mobile, including cancel, Escape, focus restoration and session invalidation.');
    console.log('Three dedicated service browser checks passed: responsive login, password visibility, invalid credentials, remember me, direct venue redirect, public booking and isolated cookies.');
} finally {
    await browser.close();
}
