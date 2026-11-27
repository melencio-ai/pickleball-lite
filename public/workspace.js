(() => {
    const region = document.querySelector('[data-toast-region]');
    window.PickleToast = (message, type = 'success') => {
        if (!region || !message) return;
        const toast = document.createElement('div');
        toast.className = 'app-toast ' + (['success','error','warning','info'].includes(type) ? type : 'info');
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const text = document.createElement('span');
        text.textContent = String(message);
        const close = document.createElement('button');
        close.type = 'button'; close.textContent = '×'; close.setAttribute('aria-label','Dismiss notification');
        close.addEventListener('click', () => toast.remove());
        toast.append(text, close);
        region.append(toast);
        while (region.children.length > 4) region.firstElementChild.remove();
        if (type === 'success' || type === 'info') setTimeout(() => toast.remove(), 4000);
    };
    for (const notice of document.querySelectorAll('[data-toast-success]')) {
        if (region && notice.textContent.trim()) {
            window.PickleToast(notice.textContent.trim());
            notice.classList.add('toast-enhanced');
        }
    }
    const body = document.body;
    const toggle = document.querySelector('[data-workspace-toggle]');
    const backdrop = document.querySelector('[data-workspace-close]');
    const nav = document.getElementById('workspace-navigation');
    const media = matchMedia('(max-width: 850px)');
    if (toggle && nav) {
        const key = 'pickle-sidebar-' + (body.dataset.workspaceUser || '');
        try { if (localStorage.getItem(key) === 'collapsed') body.classList.add('workspace-collapsed'); } catch {}
        const close = (restoreFocus = false) => {
            body.classList.remove('workspace-open');
            if (backdrop) backdrop.hidden = true;
            toggle.setAttribute('aria-expanded', String(!media.matches && !body.classList.contains('workspace-collapsed')));
            if (restoreFocus) toggle.focus();
        };
        const refresh = () => {
            close();
            nav.inert = media.matches;
        };
        toggle.addEventListener('click', () => {
            if (media.matches) {
                const open = !body.classList.contains('workspace-open');
                body.classList.toggle('workspace-open', open);
                backdrop.hidden = !open;
                nav.inert = !open;
                toggle.setAttribute('aria-expanded', String(open));
                if (open) nav.querySelector('a,button')?.focus();
            } else {
                const collapsed = body.classList.toggle('workspace-collapsed');
                toggle.setAttribute('aria-expanded', String(!collapsed));
                try { localStorage.setItem(key,collapsed?'collapsed':'expanded'); } catch {}
            }
        });
        backdrop?.addEventListener('click', () => { close(true); nav.inert = true; });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && body.classList.contains('workspace-open')) {close(true);nav.inert=true;}
        });
        nav.addEventListener('click',event=>{
            const link=event.target.closest('a.workspace-nav-link');
            if(!media.matches || !link || event.button!==0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey ||
                link.matches('[data-notification-open],[data-rewards-open]'))return;
            // Transient drawers preserve navigation context; normal navigation
            // closes the mobile menu, including links opened in another tab.
            close(link.target==='_blank');nav.inert=true;
        });
        media.addEventListener('change',refresh);refresh();
    }
    const switcher=document.querySelector('[data-workspace-switcher]');
    const switchTrigger=document.querySelector('[data-workspace-switch-open]');
    if(switcher && switchTrigger && typeof switcher.showModal==='function') {
        const close=switcher.querySelector('[data-workspace-switch-close]');
        let pointerOutside=false;
        const outside=e=>{const rect=switcher.getBoundingClientRect();return e.clientX<rect.left || e.clientX>rect.right || e.clientY<rect.top || e.clientY>rect.bottom;};
        switchTrigger.addEventListener('click',event=>{
            if(event.button!==0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey)return;
            event.preventDefault();
            if(switcher.open)return;
            switcher.showModal();switchTrigger.setAttribute('aria-expanded','true');close.focus();
        });
        close.addEventListener('click',()=>switcher.close());
        switcher.addEventListener('keydown',event=>{if(event.key==='Escape')event.stopPropagation();});
        switcher.addEventListener('pointerdown',event=>{pointerOutside=event.target===switcher && outside(event);});
        switcher.addEventListener('click',event=>{if(pointerOutside && event.target===switcher && outside(event))switcher.close();pointerOutside=false;});
        switcher.addEventListener('close',()=>{
            switchTrigger.setAttribute('aria-expanded','false');
            if(nav?.inert)toggle?.focus();else switchTrigger.focus({preventScroll:true});
        });
        window.addEventListener('pageshow',event=>{if(event.persisted && switcher.open)switcher.close();});
    }
    const signOutDialog=document.querySelector('[data-sign-out-dialog]');
    if(signOutDialog) {
        const form=signOutDialog.querySelector('[data-sign-out-form]');
        const cancel=signOutDialog.querySelector('[data-sign-out-cancel]');
        const confirm=signOutDialog.querySelector('[data-sign-out-confirm]');
        let opener=null, submitting=false, pointerOutside=false;
        const outside=e=>{
            const rect=signOutDialog.getBoundingClientRect();
            return e.clientX<rect.left || e.clientX>rect.right || e.clientY<rect.top || e.clientY>rect.bottom;
        };
        const reset=()=>{
            submitting=false;
            cancel.disabled=false;confirm.disabled=false;
            confirm.textContent='Sign out';
            form.removeAttribute('aria-busy');
        };
        for(const trigger of document.querySelectorAll('[data-sign-out-open]')) {
            trigger.addEventListener('click',()=>{
                if(signOutDialog.open)return;
                opener=trigger;reset();signOutDialog.showModal();cancel.focus();
            });
        }
        cancel.addEventListener('click',()=>{if(!submitting)signOutDialog.close();});
        signOutDialog.addEventListener('keydown',e=>{if(e.key==='Escape')e.stopPropagation();});
        signOutDialog.addEventListener('cancel',e=>{if(submitting)e.preventDefault();});
        signOutDialog.addEventListener('pointerdown',e=>{pointerOutside=e.target===signOutDialog && outside(e);});
        signOutDialog.addEventListener('click',e=>{
            if(pointerOutside && e.target===signOutDialog && outside(e) && !submitting)signOutDialog.close();
            pointerOutside=false;
        });
        signOutDialog.addEventListener('close',()=>{reset();opener?.focus();opener=null;});
        form.addEventListener('submit',e=>{
            if(submitting || !signOutDialog.open){e.preventDefault();return;}
            submitting=true;form.setAttribute('aria-busy','true');
            confirm.disabled=true;cancel.disabled=true;confirm.textContent='Signing out…';
        });
        window.addEventListener('pageshow',()=>{if(signOutDialog.open)signOutDialog.close();reset();});
    }
    const walkthrough=document.querySelector('[data-walkthrough-dialog]');
    if(walkthrough && typeof walkthrough.showModal==='function') {
        const player=walkthrough.querySelector('[data-walkthrough-player]');
        const play=walkthrough.querySelector('[data-walkthrough-play]');
        let opener=null;
        const show=trigger=>{
            if(walkthrough.open)return;
            opener=trigger || null;
            walkthrough.showModal();
        };
        for(const trigger of document.querySelectorAll('[data-walkthrough-open]')) {
            trigger.addEventListener('click',()=>show(trigger));
        }
        const stopVideo=()=>{
            player?.querySelector('iframe')?.remove();
            if(play)play.hidden=false;
        };
        const closeWalkthrough=()=>{
            stopVideo();
            if(walkthrough.open)walkthrough.close();
        };
        for(const close of walkthrough.querySelectorAll('[data-walkthrough-close]')) {
            close.addEventListener('click',closeWalkthrough);
        }
        walkthrough.addEventListener('cancel',event=>{event.preventDefault();closeWalkthrough();});
        play?.addEventListener('click',()=>{
            if(!player || player.querySelector('iframe'))return;
            const iframe=document.createElement('iframe');
            iframe.title='Pickle Lite full walkthrough';
            iframe.allow='autoplay; encrypted-media; fullscreen; picture-in-picture';
            iframe.allowFullscreen=true;
            iframe.referrerPolicy='strict-origin-when-cross-origin';
            iframe.src=play.dataset.videoSrc;
            play.hidden=true;
            player.append(iframe);
            iframe.focus();
        });
        walkthrough.addEventListener('keydown',event=>{if(event.key==='Escape')event.stopPropagation();});
        walkthrough.addEventListener('close',()=>{
            if(walkthrough.open)return;
            stopVideo();
            (opener || document.querySelector('[data-walkthrough-open]'))?.focus();
            opener=null;
        });
        window.addEventListener('pageshow',event=>{if(event.persisted && walkthrough.open)closeWalkthrough();});
        if(walkthrough.hasAttribute('data-walkthrough-auto'))show();
    }
    const countLink=document.querySelector('[data-notification-count-url]');
    const count=document.querySelector('[data-unread-count]');
    let loading=false, countRevision=0;
    function setUnread(value) {
        const n=Number(value);
        if(!Number.isSafeInteger(n) || n<0)return;
        countRevision++;
        if(count && countLink) {
            count.textContent=n>99?'99+':String(n);count.hidden=!(n>0);
            countLink.setAttribute('aria-label','Notifications'+(n>0?' ('+n+' unread)':''));
        }
    }
    async function refreshCount() {
        if (!countLink || !count || document.hidden || loading) return;
        loading=true;
        const revision=countRevision;
        try {
            const res=await fetch(countLink.dataset.notificationCountUrl,{headers:{Accept:'application/json'},cache:'no-store'});
            if(!res.ok || res.redirected) return;
            const data=await res.json();
            if(revision===countRevision)setUnread(data.unread);
        } catch {} finally {loading=false;}
    }
    if(countLink) {refreshCount();setInterval(refreshCount,30000);document.addEventListener('visibilitychange',refreshCount);}
    const drawer=document.querySelector('[data-notification-drawer]');
    if(drawer && typeof drawer.showModal==='function') {
        const triggers=Array.from(document.querySelectorAll('[data-notification-open]'));
        const content=drawer.querySelector('[data-drawer-content]');
        const status=drawer.querySelector('[data-drawer-status]');
        const countLabel=drawer.querySelector('[data-drawer-count]');
        const markAll=drawer.querySelector('[data-drawer-read-all]');
        const refresh=drawer.querySelector('[data-drawer-refresh]');
        const close=drawer.querySelector('[data-drawer-close]');
        const skeleton=drawer.querySelector('[data-drawer-loading]');
        const firstUrl=drawer.dataset.drawerUrl;
        let currentUrl=firstUrl, opener=null, epoch=0, busy=false, loaded=false, unread=0, getAbort=null, pointerOutside=false;
        const active=id=>id===epoch && drawer.open;
        const message=(text='',error=false)=>{
            status.textContent=text;status.hidden=!text;status.classList.toggle('error',error);
        };
        const syncButtons=()=>{
            content.setAttribute('aria-busy',String(busy));
            markAll.disabled=busy || !loaded || unread===0;
            refresh.disabled=busy;
            content.querySelectorAll('[data-notification-action] button').forEach(button=>button.disabled=busy);
            content.querySelectorAll('[data-notification-page]').forEach(link=>link.setAttribute('aria-disabled',String(busy)));
        };
        const setBusy=value=>{busy=value;syncButtons();};
        const setDrawerUnread=value=>{
            const n=Number(value);
            if(!Number.isSafeInteger(n) || n<0)throw Error('Invalid notification count');
            unread=n;countLabel.textContent=n+' unread · Your authorized venues';setUnread(n);
        };
        const sameOrigin=url=>new URL(url,location.href).origin===location.origin;
        async function load(url,id,keepScroll=false) {
            if(!sameOrigin(url))throw Error('Invalid notification URL');
            getAbort?.abort();
            const controller=new AbortController();getAbort=controller;
            const response=await fetch(url,{headers:{Accept:'text/html'},credentials:'same-origin',cache:'no-store',redirect:'error',signal:controller.signal});
            if(!response.ok || response.headers.get('X-Pickle-Notification-Drawer')!=='1')throw Error('Could not load notifications');
            const html=await response.text();
            if(!active(id))return;
            const scroll=keepScroll?content.scrollTop:0;
            content.innerHTML=html;
            const state=content.querySelector('[data-drawer-unread]');
            if(!state)throw Error('Invalid notification response');
            setDrawerUnread(state.dataset.drawerUnread);
            loaded=true;currentUrl=url;content.scrollTop=scroll;syncButtons();
        }
        async function navigate(url,focus=false) {
            if(busy)return;
            const id=epoch;
            message();setBusy(true);
            try {
                await load(url,id);
                if(focus && active(id))content.focus({preventScroll:true});
            } catch(error) {
                if(active(id) && error.name!=='AbortError') {
                    if(!loaded)content.replaceChildren();
                    message('Could not load notifications. Use Refresh to try again.',true);
                }
            } finally {if(active(id))setBusy(false);}
        }
        function open(trigger) {
            if(drawer.open)return;
            opener=trigger;epoch++;loaded=false;unread=0;busy=false;currentUrl=firstUrl;
            countLabel.textContent='Your authorized venues';
            content.replaceChildren(skeleton.content.cloneNode(true));
            drawer.showModal();triggers.forEach(el=>el.setAttribute('aria-expanded','true'));close.focus();
            navigate(firstUrl);
        }
        function dismiss() {if(drawer.open)drawer.close();}
        for(const trigger of triggers) {
            trigger.addEventListener('click',event=>{
                if(event.button!==0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)return;
                event.preventDefault();open(trigger);
            });
        }
        close.addEventListener('click',dismiss);
        refresh.addEventListener('click',()=>navigate(currentUrl));
        drawer.addEventListener('keydown',event=>{if(event.key==='Escape')event.stopPropagation();});
        drawer.addEventListener('cancel',event=>{event.preventDefault();dismiss();});
        const outside=event=>{
            const rect=drawer.getBoundingClientRect();
            return event.clientX<rect.left || event.clientX>rect.right || event.clientY<rect.top || event.clientY>rect.bottom;
        };
        drawer.addEventListener('pointerdown',event=>{pointerOutside=event.target===drawer && outside(event);});
        drawer.addEventListener('click',event=>{
            if(pointerOutside && event.target===drawer && outside(event))dismiss();
            pointerOutside=false;
            const link=event.target.closest('[data-notification-page]');
            if(link) {
                event.preventDefault();
                if(!busy)navigate(link.href,true);
            }
        });
        drawer.addEventListener('close',()=>{
            if(drawer.open)return;
            epoch++;getAbort?.abort();content.replaceChildren();message();busy=false;loaded=false;
            triggers.forEach(el=>el.setAttribute('aria-expanded','false'));
            opener?.focus({preventScroll:true});opener=null;
        });
        drawer.addEventListener('submit',async event=>{
            const form=event.target;
            if(!form.matches('[data-notification-action]'))return;
            event.preventDefault();
            if(busy || !loaded || !sameOrigin(form.action))return;
            const id=epoch, data=new FormData(form);
            let saved=false;
            message();setBusy(true);
            try {
                const response=await fetch(form.action,{method:'POST',body:data,headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store',redirect:'error'});
                if(!response.ok)throw Error('Notification update failed');
                const result=await response.json();
                saved=true;
                setUnread(result.unread);
                if(!active(id))return;
                setDrawerUnread(result.unread);
                await load(currentUrl,id,true);
                if(active(id)) {
                    message(result.message || 'Notifications updated.');
                    content.focus({preventScroll:true});
                }
            } catch {
                if(active(id))message(saved?'Saved. Refresh to update the notification list.':'Could not update notifications. Please try again.',true);
            } finally {if(active(id))setBusy(false);}
        });
        window.addEventListener('pageshow',event=>{if(event.persisted)dismiss();});
    }
    const rewardsDrawer=document.querySelector('[data-rewards-drawer]');
    if(rewardsDrawer && typeof rewardsDrawer.showModal==='function') {
        let opener=null,pointerOutside=false;
        const outside=e=>{const rect=rewardsDrawer.getBoundingClientRect();return e.clientX<rect.left || e.clientX>rect.right || e.clientY<rect.top || e.clientY>rect.bottom;};
        document.querySelectorAll('[data-rewards-open]').forEach(trigger=>trigger.addEventListener('click',event=>{
            if(event.button!==0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || rewardsDrawer.open)return;
            event.preventDefault();opener=trigger;rewardsDrawer.showModal();rewardsDrawer.querySelector('[data-rewards-close]').focus();
        }));
        rewardsDrawer.querySelector('[data-rewards-close]').addEventListener('click',()=>rewardsDrawer.close());
        rewardsDrawer.addEventListener('keydown',event=>{if(event.key==='Escape')event.stopPropagation();});
        rewardsDrawer.addEventListener('pointerdown',event=>{pointerOutside=event.target===rewardsDrawer && outside(event);});
        rewardsDrawer.addEventListener('click',event=>{if(pointerOutside && event.target===rewardsDrawer && outside(event))rewardsDrawer.close();pointerOutside=false;});
        rewardsDrawer.addEventListener('close',()=>{opener?.focus({preventScroll:true});opener=null;});
    }
    const method=document.querySelector('[data-onboard-payment]');
    const qr=document.querySelector('[data-onboard-qr]');
    if(method && qr) {
        const update=()=>{qr.hidden=method.value!=='qr';qr.querySelectorAll('input,textarea').forEach(el=>{el.disabled=qr.hidden;el.required=!qr.hidden;});};
        method.addEventListener('change',update);update();
    }
    const rateNotice=document.querySelector('[data-email-rate-limit]');
    const cooldownAction=rateNotice?.dataset.emailRateLimit;
    const seconds=Number(rateNotice?.dataset.retrySeconds || 0);
    const deadline=Date.now()+Math.max(0,seconds)*1000;
    const coolingDown=form=>Boolean(cooldownAction && form.dataset.emailAction===cooldownAction && Date.now()<deadline);
    if(rateNotice && seconds>0) {
        window.PickleToast(rateNotice.textContent.trim(),'warning');
        const button=Array.from(document.querySelectorAll('[data-email-action]')).find(form=>form.dataset.emailAction===cooldownAction)?.querySelector('[data-app-submit]');
        const countdown=rateNotice.querySelector('[data-retry-countdown]');
        let timer;
        const update=()=>{
            const remaining=Math.max(0,Math.ceil((deadline-Date.now())/1000));
            if(countdown)countdown.textContent=String(remaining);
            if(button)button.disabled=remaining>0;
            if(!remaining){rateNotice.hidden=true;clearInterval(timer);}
        };
        timer=setInterval(update,1000);update();
    }
    for(const form of document.querySelectorAll('[data-app-form]')) {
        const btn=form.querySelector('[data-app-submit]');if(!btn)continue;
        let submitted=false;
        form.addEventListener('submit',e=>{if(submitted || coolingDown(form)){e.preventDefault();return;}submitted=true;btn.disabled=true;form.setAttribute('aria-busy','true');});
        window.addEventListener('pageshow',()=>{submitted=false;btn.disabled=coolingDown(form);form.removeAttribute('aria-busy');});
    }
})();
