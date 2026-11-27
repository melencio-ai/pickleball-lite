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
        media.addEventListener('change',refresh);refresh();
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
        for(const close of walkthrough.querySelectorAll('[data-walkthrough-close]')) {
            close.addEventListener('click',()=>walkthrough.close());
        }
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
            player?.querySelector('iframe')?.remove();
            if(play)play.hidden=false;
            (opener || document.querySelector('[data-walkthrough-open]'))?.focus();
            opener=null;
        });
        window.addEventListener('pageshow',event=>{if(event.persisted && walkthrough.open)walkthrough.close();});
        if(walkthrough.hasAttribute('data-walkthrough-auto'))show();
    }
    const countLink=document.querySelector('[data-notification-count-url]');
    const count=document.querySelector('[data-unread-count]');
    let loading=false;
    async function refreshCount() {
        if (!countLink || !count || document.hidden || loading) return;
        loading=true;
        try {
            const res=await fetch(countLink.dataset.notificationCountUrl,{headers:{Accept:'application/json'},cache:'no-store'});
            if(!res.ok || res.redirected) return;
            const data=await res.json();const n=Number(data.unread);
            count.textContent=n>99?'99+':String(n);count.hidden=!(n>0);
            countLink.setAttribute('aria-label','Notifications' + (n>0?' ('+n+' unread)':''));
        } catch {} finally {loading=false;}
    }
    if(countLink) {refreshCount();setInterval(refreshCount,30000);document.addEventListener('visibilitychange',refreshCount);}
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
