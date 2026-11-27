import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

const web='http://pickle-web.test',app='http://pickle-app.test',book='http://pickle-book.test/pickleball-lite-courts';
const browser=await chromium.launch({headless:true,args:['--no-proxy-server','--host-resolver-rules=MAP pickle-web.test 127.0.0.1:8001, MAP pickle-app.test 127.0.0.1:8002, MAP pickle-book.test 127.0.0.1:8003']});
const page=await browser.newPage({viewport:{width:1440,height:900}});
try {
    await page.goto(web+'/terms');
    await page.getByRole('heading',{name:'Policy not yet published'}).waitFor();
    await page.goto(app+'/login');
    await page.getByLabel('Email address',{exact:true}).fill('browser-calendar@example.test');
    await page.getByLabel('Password',{exact:true}).fill('browser-test-password-123');
    await page.getByRole('button',{name:'Sign in',exact:true}).click();
    await page.waitForURL(app+'/admin');
    const platformNav=page.locator('#workspace-navigation');
    assert.equal(await platformNav.locator('[data-nav-context]').getAttribute('data-nav-context'),'platform');
    assert.deepEqual(await platformNav.locator('.workspace-nav-caption').allTextContents(),['Platform','Configuration','Compliance']);
    assert.equal(await platformNav.locator('[data-nav-item="calendar"]').count(),0);
    await page.locator('[data-workspace-switch-open]').click();
    const platformSwitcher=page.getByRole('dialog',{name:'Switch workspace',exact:true});
    await platformSwitcher.waitFor();
    assert.equal(await platformSwitcher.locator('[data-workspace-choice="platform"]').getAttribute('aria-current'),'true');
    await platformSwitcher.getByRole('button',{name:'Close workspace switcher'}).click();
    await platformSwitcher.waitFor({state:'hidden'});
    await platformNav.getByRole('link',{name:'Legal policies',exact:true}).click();
    await page.waitForURL(app+'/admin/legal');
    await page.getByRole('heading',{name:'Legal policies',exact:true}).waitFor();
    await page.locator('input[name="legal_name"]').fill('Browser Test Operator');
    await page.locator('input[name="business_address"]').fill('Test address');
    await page.locator('input[name="support_email"]').fill('support@example.test');
    await page.locator('input[name="privacy_email"]').fill('privacy@example.test');
    await page.locator('textarea[name="retention"]').fill('Test-only retention schedule.');
    await page.locator('textarea[name="commercial_conditions"]').fill('Test-only commercial conditions.');
    await page.locator('#publish-confirmed').check();
    await page.getByRole('button',{name:'Publish all four policies'}).click();
    await page.waitForURL(app+'/accept-terms');
    await page.locator('[data-sign-out-open]').click();
    const confirmation=page.getByRole('dialog',{name:'Sign out?'});
    await confirmation.waitFor();
    await confirmation.getByRole('button',{name:'Cancel',exact:true}).click();
    await confirmation.waitFor({state:'hidden'});
    await page.locator('#legal-accepted').check();
    await page.getByRole('button',{name:'Accept and continue'}).click();
    await page.waitForURL(app+'/admin');
    await page.goto(web+'/privacy');
    await page.getByRole('heading',{name:'Privacy Policy',exact:true}).waitFor();
    await page.getByText('Test-only retention schedule.',{exact:false}).first().waitFor();
    await page.setViewportSize({width:390,height:844});
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Policy must fit mobile screen');
    const day=new Date(Date.now()+2*86400000).toISOString().slice(0,10);
    await page.goto(book+'/book?step=5&date='+day+'&duration=60&start=19%3A00&court=1');
    await page.getByRole('heading',{name:'Add your details',exact:true}).waitFor();
    const checkbox=page.locator('#legal-accepted');
    assert.equal(await checkbox.isChecked(),false);
    assert.ok(await checkbox.getAttribute('required')!==null);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Booking form must fit mobile screen');
    await page.getByLabel('Full name').fill('Browser Legal Player');
    await page.getByLabel('Mobile number').fill('09170000000');
    await checkbox.check();
    await page.locator('[data-booking-submit]').click();
    await page.waitForURL(book+'/bookings/confirmed');
    await page.getByText('Terms of Use (v1)',{exact:true}).waitFor();
    // Registration is enabled only in this CI fixture and the policies above
    // have been published. Use a separate guest context and never submit email.
    const registration=await browser.newPage({viewport:{width:1440,height:900}});
    await registration.goto(app+'/register');
    await registration.getByRole('heading',{name:'Start your free trial'}).waitFor();
    assert.ok(await registration.locator('body').evaluate(el=>el.classList.contains('auth-register')));
    const name=registration.locator('#signup-name'),email=registration.locator('#signup-email');
    const password=registration.locator('#signup-password'),confirm=registration.locator('#signup-confirm');
    const n=await name.boundingBox(),e=await email.boundingBox(),p=await password.boundingBox(),c=await confirm.boundingBox();
    assert.ok(n && e && p && c && Math.abs(n.y-e.y)<2 && e.x>n.x && Math.abs(p.y-c.y)<2 && c.x>p.x,'Desktop fields must form two aligned columns');
    assert.ok(await registration.locator('.auth-aside').isVisible());
    await registration.setViewportSize({width:1024,height:460});
    const scroll=await registration.evaluate(()=>{
        const right=document.querySelector('.auth-main'),left=document.querySelector('.auth-aside');
        const before=left.getBoundingClientRect().top;
        right.scrollTop=right.scrollHeight;
        return {overflow:right.scrollHeight>right.clientHeight,top:right.scrollTop,leftBefore:before,leftAfter:left.getBoundingClientRect().top,bodyTop:window.scrollY,bodyFits:document.documentElement.scrollHeight<=innerHeight+1};
    });
    assert.ok(scroll.overflow && scroll.top>0 && scroll.leftBefore===scroll.leftAfter && scroll.bodyTop===0 && scroll.bodyFits,'Right side scrolls independently of the pinned left panel');
    assert.ok(await registration.getByRole('button',{name:'Create free account'}).isVisible());
    await registration.locator('.auth-footer').scrollIntoViewIfNeeded();
    await registration.setViewportSize({width:390,height:844});
    assert.equal(await registration.locator('.auth-aside').isVisible(),false);
    assert.ok(await registration.locator('.auth-mobile-brand').isVisible());
    const mn=await name.boundingBox(),me=await email.boundingBox();
    assert.ok(mn && me && Math.abs(mn.x-me.x)<2 && me.y>mn.y,'Mobile fields must stack vertically');
    await registration.setViewportSize({width:320,height:568});
    assert.ok(await registration.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Registration must fit a narrow phone');
    await name.fill('Layout Test');
    await email.fill('layout-test@example.test');
    await password.fill('layout-test-password');
    await confirm.fill('layout-test-password');
    await registration.locator('#legal-accepted').check();
    await registration.locator('#subscription-accepted').check();
    await registration.getByRole('button',{name:'Create free account'}).scrollIntoViewIfNeeded();
    assert.ok(await registration.getByRole('button',{name:'Create free account'}).isVisible());
    assert.equal(await registration.locator('.auth-form').evaluate(form=>form.checkValidity()),true);
    await registration.close();

    // Test-only fixture avoids actually sending a verification email. All
    // launch validations and the real completion endpoint remain in use.
    execFileSync('php',['artisan','tinker','--execute='+[
        '$venue=App\\Models\\Venue::create(["name"=>"Walkthrough Test Courts","slug"=>"walkthrough-test-courts","address"=>"Test venue address","phone"=>"09171234567","timezone"=>"Asia/Manila","status"=>"onboarding","published"=>false,"self_service"=>true]);',
        '$owner=$venue->users()->create(["name"=>"Walkthrough Owner","email"=>"walkthrough-owner@example.test","password"=>"browser-test-password-123","role"=>"owner"]);',
        '$owner->forceFill(["self_registered"=>true,"email_verified_at"=>now()])->save();',
        'Illuminate\\Support\\Facades\\DB::table("venue_subscriptions")->insert(["venue_id"=>$venue->id,"status"=>"onboarding","created_at"=>now(),"updated_at"=>now()]);',
        'App\\Services\\TrialAccess::start($venue);',
        'Illuminate\\Support\\Facades\\DB::table("venue_onboarding")->insert(["venue_id"=>$venue->id,"step"=>7,"created_at"=>now(),"updated_at"=>now()]);',
        '$venue->courts()->create(["name"=>"Court 1","hourly_rate"=>40000,"active"=>true]);',
        'foreach(range(0,6) as $day) $venue->hours()->create(["weekday"=>$day,"opens_at"=>"07:00","closes_at"=>"22:00","closed"=>false]);',
        'App\\Services\\LegalPolicies::record(App\\Services\\LegalPolicies::context(subscription:true),"user",$owner->id,$venue->id,$owner->id);',
    ].join(' ')],{stdio:'pipe'});
    const walkthrough=await browser.newPage({viewport:{width:390,height:844}});
    let loads=0;
    await walkthrough.route('https://www.youtube-nocookie.com/**',async route=>{
        loads++;
        await route.fulfill({contentType:'text/html',body:'<html><body>Video test fixture</body></html>'});
    });
    await walkthrough.goto(web);
    await walkthrough.getByRole('heading',{name:'Watch a 5-minute full walkthrough'}).waitFor();
    assert.equal(await walkthrough.locator('#get-started').getByRole('link',{name:'Create a free account'}).getAttribute('href'),app+'/register');
    assert.equal(await walkthrough.locator('#get-started input').count(),0);
    await walkthrough.goto(app+'/login');
    await walkthrough.getByLabel('Email address',{exact:true}).fill('walkthrough-owner@example.test');
    await walkthrough.getByLabel('Password',{exact:true}).fill('browser-test-password-123');
    await walkthrough.getByRole('button',{name:'Sign in',exact:true}).click();
    await walkthrough.waitForURL(app+'/venues/walkthrough-test-courts/onboarding');
    await walkthrough.getByRole('heading',{name:'Review and launch'}).waitFor();
    await walkthrough.getByLabel('Booking and cancellation policy').fill('Contact the venue to change a booking.');
    await walkthrough.locator('input[name="launch_confirmed"]').check();
    await walkthrough.getByRole('button',{name:'Launch my venue'}).click();
    await walkthrough.waitForURL(app+'/venues/walkthrough-test-courts/calendar');
    const tour=walkthrough.getByRole('dialog',{name:'Your 5-minute walkthrough'});
    await tour.waitFor();
    assert.equal(await tour.locator('iframe').count(),0);
    assert.equal(loads,0);
    const rect=await tour.boundingBox();
    assert.ok(rect && rect.x>=0 && rect.x+rect.width<=390 && rect.y>=0 && rect.y+rect.height<=844,'Walkthrough modal fits mobile viewport');
    await tour.getByRole('button',{name:'Play walkthrough'}).click();
    await walkthrough.frameLocator('#venue-walkthrough iframe').getByText('Video test fixture').waitFor();
    assert.equal(loads,1);
    await tour.getByRole('button',{name:'Go to calendar'}).click();
    await tour.waitFor({state:'hidden'});
    assert.equal(await walkthrough.locator('#venue-walkthrough iframe').count(),0,'Closing stops the embedded video');
    await walkthrough.reload();
    await tour.waitFor({state:'hidden'});
    assert.equal(await walkthrough.locator('[data-walkthrough-auto]').count(),0,'Onboarding modal is one-time');
    await walkthrough.getByRole('button',{name:'Watch walkthrough'}).click();
    await tour.waitFor();
    await walkthrough.keyboard.press('Escape');
    await tour.waitFor({state:'hidden'});
    const calendarUrl=walkthrough.url();
    const widget=walkthrough.locator('.rewards-widget');
    await widget.getByText('1-day streak',{exact:true}).waitFor();
    await widget.click();
    const rewardDrawer=walkthrough.getByRole('dialog',{name:'Pickle Lite Rewards'});
    await rewardDrawer.waitFor();
    await rewardDrawer.evaluate(el=>Promise.all(el.getAnimations().map(a=>a.finished.catch(()=>{}))));
    const rewardBounds=await rewardDrawer.boundingBox();
    assert.ok(rewardBounds && rewardBounds.width<=390 && rewardBounds.x>=0 && rewardBounds.x+rewardBounds.width<=391);
    await rewardDrawer.getByText('100-day streak',{exact:true}).waitFor();
    await rewardDrawer.getByText('300-day streak',{exact:true}).waitFor();
    assert.equal(walkthrough.url(),calendarUrl);
    await walkthrough.keyboard.press('Escape');
    await rewardDrawer.waitFor({state:'hidden'});
    await walkthrough.setViewportSize({width:1440,height:900});
    await walkthrough.locator('#workspace-navigation').getByRole('link',{name:'Rewards',exact:true}).click();
    await rewardDrawer.waitFor();
    await rewardDrawer.evaluate(el=>Promise.all(el.getAnimations().map(a=>a.finished.catch(()=>{}))));
    const desktopRewards=await rewardDrawer.boundingBox();
    assert.ok(desktopRewards && desktopRewards.width<=440 && Math.abs(desktopRewards.x+desktopRewards.width-1440)<2);
    await rewardDrawer.getByRole('link',{name:/View rewards, submit testimonial/}).click();
    await walkthrough.waitForURL(app+'/venues/walkthrough-test-courts/rewards');
    await walkthrough.getByRole('heading',{name:'Pickle Lite Rewards',exact:true}).waitFor();
    await walkthrough.getByText('Unlock after 15 active days.',{exact:false}).waitFor();
    assert.ok(await walkthrough.getByText('Optional Facebook review',{exact:true}).isVisible());
    await walkthrough.setViewportSize({width:320,height:740});
    assert.ok(await walkthrough.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
    await walkthrough.close();
    console.log('Platform rewards passed: qualifying launch-day activity, one-day streak, mobile and desktop drawer, milestones and optional review.');
    console.log('Walkthrough passed: landing CTA, completed onboarding, one-time responsive modal, deferred video, close and replay.');
    console.log('Registration layout passed: two desktop columns, pinned left panel, independent right scroll, mobile stacked fields and reachable legal consent.');
    console.log('Legal browser smoke passed: draft visibility, admin publish, staff terms review, mobile legal page, booking consent and recorded version.');
} finally { await browser.close(); }
