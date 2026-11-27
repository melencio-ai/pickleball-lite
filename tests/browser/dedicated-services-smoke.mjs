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
    assert.equal(await navigation.locator('[data-nav-context]').getAttribute('data-nav-context'),'venue');
    assert.deepEqual(await navigation.locator('.workspace-nav-caption').allTextContents(),['Operations','Online booking','Membership','Administration']);
    assert.equal(await navigation.locator('[data-nav-item="calendar"]').getAttribute('aria-current'),'page');
    assert.equal(await navigation.locator('[data-nav-item^="platform-"]').count(),0);
    const switchTrigger=page.locator('[data-workspace-switch-open]');
    const switchDialog=page.getByRole('dialog',{name:'Switch workspace',exact:true});
    await switchTrigger.click();
    await switchDialog.waitFor();
    assert.equal(await switchDialog.locator('[data-workspace-choice="'+slug+'"]').getAttribute('aria-current'),'true');
    assert.equal(await switchDialog.locator('[data-workspace-choice="platform"]').count(),0);
    await page.keyboard.press('Escape');
    await switchDialog.waitFor({state:'hidden'});
    assert.equal(await switchTrigger.evaluate(el=>el===document.activeElement),true);
    await page.getByRole('button',{name:'Toggle navigation'}).click();
    assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('workspace-collapsed')));
    assert.ok(await navigation.getByRole('link',{name:'Calendar',exact:true}).isVisible());
    assert.ok(await switchTrigger.locator('.workspace-switch-mark,.workspace-switch-logo').isVisible(),'Collapsed switcher retains its icon or logo');
    await switchTrigger.click();
    await switchDialog.waitFor();
    await switchDialog.getByRole('button',{name:'Close workspace switcher'}).click();
    await switchDialog.waitFor({state:'hidden'});
    await page.getByRole('button',{name:'Toggle navigation'}).click();
    assert.equal(await page.locator('[data-mobile-navigation]').isVisible(),false);
    await page.setViewportSize({width:320,height:740});
    await page.waitForFunction(()=>getComputedStyle(document.querySelector('#workspace-navigation')).visibility==='hidden');
    const mobileNav=page.locator('[data-mobile-navigation]');
    const more=mobileNav.locator('[data-mobile-more]');
    await mobileNav.waitFor();
    assert.deepEqual(await mobileNav.locator('.workspace-tab > span:last-child').allTextContents().then(items=>items.map(s=>s.trim())),['Calendar','Open Play','Alerts','More']);
    assert.equal(await mobileNav.locator('[data-mobile-tab="calendar"]').getAttribute('aria-current'),'page');
    const bar=await mobileNav.boundingBox();
    assert.ok(bar && Math.abs(bar.y+bar.height-740)<2 && bar.width<=320);
    for(const tab of await mobileNav.locator('.workspace-tab').all()){const box=await tab.boundingBox();assert.ok(box && box.width>=48 && box.height>=48);}
    assert.equal(await page.getByRole('button',{name:'Toggle navigation'}).isVisible(),false);
    await page.locator('[data-mobile-workspace-switch]').click();
    await switchDialog.waitFor();
    await switchDialog.getByRole('button',{name:'Close workspace switcher'}).click();
    await switchDialog.waitFor({state:'hidden'});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile management page must not overflow');
    await more.click();
    await navigation.waitFor({state:'visible'});
    await navigation.evaluate(el=>Promise.all(el.getAnimations().map(a=>a.finished.catch(()=>{}))));
    const sheetBounds=await navigation.boundingBox();
    assert.ok(sheetBounds && sheetBounds.width<=320 && sheetBounds.y>=0 && Math.abs(sheetBounds.y+sheetBounds.height-bar.y)<2,'More sheet must fit above the tab bar');
    assert.equal(await more.getAttribute('aria-expanded'),'true');
    assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('workspace-open')));
    await switchTrigger.click();
    await switchDialog.waitFor();
    const switchBounds=await switchDialog.boundingBox();
    assert.ok(switchBounds && switchBounds.x>=0 && switchBounds.x+switchBounds.width<=320 && switchBounds.y+switchBounds.height<=740);
    await page.keyboard.press('Escape');
    await switchDialog.waitFor({state:'hidden'});
    assert.ok(await page.locator('body').evaluate(el=>el.classList.contains('workspace-open')));
    await mobileNav.getByRole('link',{name:'Open Play',exact:true}).click();
    await page.waitForURL(app+'/venues/'+slug+'/open-play');
    await page.waitForFunction(()=>!document.body.classList.contains('workspace-open'));
    assert.equal(await mobileNav.locator('[data-mobile-tab="open-play"]').getAttribute('aria-current'),'page');
    await page.goto(app+'/venues/'+slug+'/calendar');
    await more.click();
    await navigation.getByRole('link',{name:'Booking setup',exact:true}).click();
    await page.waitForURL(app+'/venues/'+slug+'/booking-setup');
    assert.equal(await more.getAttribute('aria-current'),'true');
    await page.goto(app+'/venues/'+slug+'/calendar');
    await more.click();
    await page.keyboard.press('Escape');
    await page.waitForFunction(()=>!document.body.classList.contains('workspace-open'));
    // Seed only this isolated CI database with two unread items.
    execFileSync('php',['artisan','tinker','--execute='+[
        '$venue=App\\Models\\Venue::where("slug","pickleball-lite-courts")->firstOrFail();',
        'App\\Services\\VenueNotifications::emit($venue,"booking","drawer-one","Drawer test booking","Court booking received");',
        'App\\Services\\VenueNotifications::emit($venue,"payment","drawer-two","Drawer test payment","Payment received");',
    ].join(' ')],{stdio:'pipe'});
    const calendarUrl=page.url();
    const mobileAlerts=mobileNav.locator('[data-mobile-tab="alerts"]');
    await mobileAlerts.click();
    const drawer=page.getByRole('dialog',{name:'Notifications',exact:true});
    await drawer.waitFor();
    await drawer.getByText('Drawer test booking',{exact:true}).waitFor();
    await page.waitForFunction(()=>!document.querySelector('.workspace-mobile-alerts [data-unread-count]').hidden);
    assert.equal(page.url(),calendarUrl);
    await drawer.evaluate(el=>Promise.all(el.getAnimations().map(animation=>animation.finished.catch(()=>{}))));
    const drawerBounds=await drawer.boundingBox();
    assert.ok(drawerBounds && drawerBounds.x>=0 && drawerBounds.x+drawerBounds.width<=320 && drawerBounds.height<=741);
    await drawer.locator('[data-notification-record]').filter({hasText:'Drawer test booking'}).getByRole('button',{name:'Mark notification as read'}).click();
    await drawer.getByText('Notification marked as read.',{exact:true}).waitFor();
    assert.equal(await drawer.locator('[data-notification-record]').filter({hasText:'Drawer test booking'}).getByRole('button',{name:'Mark notification as read'}).count(),0);
    await drawer.getByRole('button',{name:'Mark all as read'}).click();
    await drawer.getByText('All notifications marked as read.',{exact:true}).waitFor();
    await page.waitForFunction(()=>[...document.querySelectorAll('[data-unread-count]')].every(el=>el.hidden));
    assert.equal(await drawer.locator('.notification-row.unread').count(),0);
    assert.equal(page.url(),calendarUrl);
    await drawer.getByRole('button',{name:'Close notifications'}).click();
    await drawer.waitFor({state:'hidden'});
    assert.equal(await mobileAlerts.evaluate(el=>el===document.activeElement),true);
    await page.setViewportSize({width:1440,height:900});
    await page.locator('.workspace-notifications').click();
    await drawer.getByText('Drawer test booking',{exact:true}).waitFor();
    await drawer.evaluate(el=>Promise.all(el.getAnimations().map(animation=>animation.finished.catch(()=>{}))));
    const desktopBounds=await drawer.boundingBox();
    assert.ok(desktopBounds && desktopBounds.width<=440 && Math.abs(desktopBounds.x+desktopBounds.width-1440)<2,'Drawer must be anchored to right edge');
    await page.keyboard.press('Escape');
    await drawer.waitFor({state:'hidden'});
    assert.equal(page.url(),calendarUrl);
    await page.locator('.workspace-bell').click();
    await drawer.getByText('Drawer test payment',{exact:true}).waitFor();
    await drawer.getByRole('link',{name:/View all notifications/}).click();
    await page.waitForURL(app+'/notifications');
    await page.getByRole('heading',{name:'Notification preferences'}).waitFor();
    console.log('Notification drawer passed: desktop and mobile, both entry points, read actions, badge sync, preserved calendar URL, close and preferences.');
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
    assert.equal(await page.locator('[data-mobile-navigation]').count(),0);
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
    const signedBrand=page.locator('.workspace-venue-brand');
    await signedBrand.waitFor();
    assert.equal(await signedBrand.locator('.workspace-brand-name').textContent(),venueName);
    assert.equal(await signedBrand.locator('img').getAttribute('src'),venueLogo);
    await signedBrand.locator('img').evaluate(img=>img.decode());
    assert.equal(await page.locator('.workspace-switch-logo').getAttribute('src'),venueLogo);
    assert.equal(await page.locator('link[rel="icon"]').getAttribute('href'),venueLogo);
    assert.equal(await signedBrand.getAttribute('href'),app+'/venues/'+slug+'/calendar');
    for(const label of ['Previous day','Next day','Refresh calendar','New']) {
        const control=page.locator('[aria-label="'+label+'"]').or(page.getByRole('link',{name:label,exact:true})).first();
        const icon=control.locator('svg.booking-icon');
        assert.ok(await icon.evaluate(el=>el.querySelector('path,rect,circle,line,polyline')!==null && el.getBBox().width>0 && el.getBBox().height>0),'Missing inline icon: '+label);
    }
    await page.setViewportSize({width:320,height:740});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Mobile workspace branding must not overflow');
    await page.setViewportSize({width:1440,height:900});
    await switchTrigger.click();
    await switchDialog.getByRole('link',{name:/My venues/}).click();
    await page.waitForURL(app+'/venues');
    await page.getByRole('link',{name:/Manage venue/}).waitFor();
    assert.equal(await navigation.locator('[data-nav-context]').getAttribute('data-nav-context'),'account');
    assert.equal(await navigation.locator('[data-nav-item="calendar"]').count(),0);
    await switchTrigger.click();
    await switchDialog.locator('[data-workspace-choice="'+slug+'"]').click();
    await page.waitForURL(app+'/venues/'+slug+'/calendar');
    await page.goto(app+'/venues');
    console.log('Grouped navigation passed: active routes, context isolation, collapsed and mobile switcher, venue selection, and mobile navigation dismissal.');
    assert.equal(await page.locator('.workspace-venue-brand').count(),0);
    assert.equal(await page.locator('.site-header .brand').textContent().then(s=>s.trim()),'Pickle Lite');
    console.log('Signed-in venue branding passed: logo, name, favicon, sidebar, mobile fit, platform branding isolation, and inline calendar icons.');
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
    await more.click();
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
